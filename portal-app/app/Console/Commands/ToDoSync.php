<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\Microsoft\MicrosoftApp;
use App\Services\Microsoft\ToDo;
use Illuminate\Console\Command;
use Throwable;

/** Two-way sync of portal tasks with Microsoft To Do for everyone who has connected it. Runs every 5 minutes. */
class ToDoSync extends Command
{
    protected $signature = 'portal:todo-sync';

    protected $description = 'Sync portal tasks with Microsoft To Do';

    public function handle(): int
    {
        if (! MicrosoftApp::isConfigured()) {
            $this->line('Microsoft 365 is not set up; nothing to do.');

            return self::SUCCESS;
        }
        // 1. Send portal changes that failed to go out earlier.
        $pushed = 0;
        Task::withoutGlobalScopes()->where('todo_pending', true)->limit(200)->get()->each(function ($t) use (&$pushed) {
            $pushed += ToDo::push($t) ? 1 : 0;
        });
        // 2. Bring in changes made in To Do.
        $failed = 0;
        foreach (User::withoutGlobalScopes()->whereNotNull('microsoft_refresh_token')->where('status', User::ACTIVE)->get() as $user) {
            try {
                [$updated, $created] = ToDo::pull($user);
                if ($updated || $created) {
                    $this->line("{$user->name}: {$updated} updated, {$created} new from To Do.");
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$user->name}: ".$e->getMessage());
            }
        }
        $this->info("To Do sync done. {$pushed} sent to To Do.".($failed ? " {$failed} person(s) failed." : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
