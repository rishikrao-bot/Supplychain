<?php

namespace App\Services\Microsoft;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-way sync between portal tasks and each person's Microsoft To Do, in a list called
 * "your company Portal" (created automatically).
 *
 *  - Portal → To Do: when a task is added or changed, it is created/updated in the assignee's list.
 *    Reassigning moves it to the new person's list.
 *  - To Do → portal (every 5 minutes): ticking off, renaming or changing the due date in To Do updates
 *    the portal; new tasks added to that list in To Do appear in the portal, assigned to that person.
 *  - Whichever side changed last wins.
 */
class ToDo
{
    public static function listName(): string
    {
        return (string) config('portal.name');
    }

    /** The person's "your company Portal" list id, created if missing. */
    public static function listId(User $user): string
    {
        $graph = MicrosoftUser::graph($user);
        if ($user->todo_list_id) {
            $res = $graph->get('/me/todo/lists/'.$user->todo_list_id);
            if ($res->successful()) {
                return $user->todo_list_id;
            }
            if ($res->status() !== 404) {
                MicrosoftUser::check($res, 'To Do');
            }
        }
        $lists = MicrosoftUser::check($graph->get('/me/todo/lists'), 'To Do')['value'] ?? [];
        $found = collect($lists)->firstWhere('displayName', self::listName());
        $id = $found['id'] ?? MicrosoftUser::check($graph->post('/me/todo/lists', ['displayName' => self::listName()]), 'To Do')['id'];
        $user->forceFill(['todo_list_id' => $id])->saveQuietly();

        return $id;
    }

    private static function payload(Task $task, bool $new): array
    {
        $url = route('tasks.show', $task);
        $about = $task->about();
        $data = [
            'title' => $task->title,
            'body' => ['contentType' => 'text', 'content' => trim(($about ? $about."\n\n" : '').($task->notes ?? '')."\n\nOpen in the portal: ".$url)],
            'status' => $task->isDone() ? 'completed' : 'notStarted',
            'dueDateTime' => $task->due_on ? ['dateTime' => $task->due_on->format('Y-m-d').'T00:00:00', 'timeZone' => 'Europe/London'] : null,
        ];
        if ($new) {
            $data['linkedResources'] = [['webUrl' => $url, 'applicationName' => config('portal.name'), 'displayName' => 'Task #'.$task->id]];
        }

        return $data;
    }

    /** Send one portal task to To Do. Never throws: failures are kept on the task and retried. */
    public static function push(Task $task): bool
    {
        $task->load(['assignee', 'todoUser', 'organisation', 'contact', 'opportunity']);
        $assignee = $task->assignee;
        try {
            // Reassigned (or unassigned): remove it from the previous person's list.
            if ($task->todo_task_id && $task->todo_user_id && $task->todo_user_id !== $assignee?->id) {
                $old = $task->todoUser;
                if ($old?->hasMicrosoft() && $old->todo_list_id) {
                    $del = MicrosoftUser::graph($old)->delete('/me/todo/lists/'.$old->todo_list_id.'/tasks/'.$task->todo_task_id);
                    if (! $del->successful() && $del->status() !== 404) {
                        MicrosoftUser::check($del, 'To Do');
                    }
                }
                self::saveLink($task, null, null, null);
            }
            if (! $assignee?->hasMicrosoft()) {
                self::saveLink($task, $task->todo_user_id, $task->todo_task_id, $task->todo_modified_at, false, null);

                return false;
            }

            $list = self::listId($assignee);
            $graph = MicrosoftUser::graph($assignee);
            $res = null;
            if ($task->todo_task_id) {
                $res = $graph->patch("/me/todo/lists/{$list}/tasks/{$task->todo_task_id}", self::payload($task, false));
                if ($res->status() === 404) {
                    $res = null; // deleted in To Do: create it again
                }
            }
            $res ??= $graph->post("/me/todo/lists/{$list}/tasks", self::payload($task, true));
            $item = MicrosoftUser::check($res, 'To Do');
            self::saveLink($task, $assignee->id, $item['id'], self::time($item['lastModifiedDateTime'] ?? null), false, null);

            return true;
        } catch (Throwable $e) {
            self::saveLink($task, $task->todo_user_id, $task->todo_task_id, $task->todo_modified_at, true, mb_substr($e->getMessage(), 0, 255));
            Log::warning("To Do sync failed for task {$task->id}: ".$e->getMessage());

            return false;
        }
    }

    /** Bring changes made in one person's To Do list into the portal. Returns [updated, created]. */
    public static function pull(User $user): array
    {
        $list = self::listId($user);
        $graph = MicrosoftUser::graph($user);
        $items = [];
        $url = "/me/todo/lists/{$list}/tasks?\$top=100";
        while ($url) {
            $page = MicrosoftUser::check($graph->get(str_replace(MicrosoftUser::GRAPH, '', $url)), 'To Do');
            $items = array_merge($items, $page['value'] ?? []);
            $url = $page['@odata.nextLink'] ?? null;
        }

        $updated = $created = 0;
        $seen = [];
        foreach ($items as $item) {
            $seen[] = $item['id'];
            $modified = self::time($item['lastModifiedDateTime'] ?? null);
            $task = Task::withoutGlobalScopes()->where('todo_task_id', $item['id'])->where('todo_user_id', $user->id)->first();
            $done = ($item['status'] ?? '') === 'completed';
            $due = isset($item['dueDateTime']['dateTime']) ? Carbon::parse($item['dueDateTime']['dateTime'])->toDateString() : null;

            if (! $task) {
                if ($done) {
                    continue; // old completed items made in To Do aren't worth importing
                }
                $task = new Task(['account_id' => $user->account_id, 'title' => mb_substr($item['title'] ?? 'Task', 0, 255),
                    'due_on' => $due, 'status' => Task::OPEN, 'assigned_to' => $user->id, 'created_by' => $user->id,
                    'notes' => trim((string) ($item['body']['content'] ?? '')) ?: null]);
                $task->forceFill(['todo_user_id' => $user->id, 'todo_task_id' => $item['id'], 'todo_modified_at' => $modified]);
                $task->saveQuietly();
                $created++;

                continue;
            }
            if ($task->todo_modified_at && $modified && $modified->lte($task->todo_modified_at)) {
                continue; // nothing new on the To Do side
            }
            if ($task->updated_at && $modified && $task->updated_at->gt($modified) && $task->todo_pending) {
                continue; // the portal changed it more recently and that change is waiting to go out
            }
            $task->forceFill([
                'title' => mb_substr($item['title'] ?? $task->title, 0, 255),
                'due_on' => $due,
                'status' => $done ? Task::DONE : Task::OPEN,
                'completed_at' => $done ? ($task->completed_at ?? now()) : null,
                'todo_modified_at' => $modified,
            ])->saveQuietly();
            $updated++;
        }

        // Deleted in To Do: keep the portal task but unlink it (it'll be sent again if it changes).
        Task::withoutGlobalScopes()->where('todo_user_id', $user->id)->whereNotNull('todo_task_id')->whereNotIn('todo_task_id', $seen ?: [''])
            ->get()->each(fn (Task $t) => self::saveLink($t, null, null, null));

        $user->forceFill(['todo_synced_at' => now()])->saveQuietly();

        return [$updated, $created];
    }

    private static function saveLink(Task $task, ?int $userId, ?string $id, $modified, bool $pending = false, ?string $error = null): void
    {
        $task->forceFill(['todo_user_id' => $userId, 'todo_task_id' => $id, 'todo_modified_at' => $modified, 'todo_pending' => $pending, 'todo_error' => $error])->saveQuietly();
    }

    private static function time(?string $iso): ?Carbon
    {
        return $iso ? Carbon::parse($iso)->utc() : null;
    }
}
