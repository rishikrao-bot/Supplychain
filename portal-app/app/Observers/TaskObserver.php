<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\Microsoft\ToDo;

/** Portal-side changes to a task go out to the assignee's Microsoft To Do (after the page is sent). */
class TaskObserver
{
    public function saved(Task $task): void
    {
        $this->sync($task);
    }

    public function deleting(Task $task): void
    {
        // Treat a deleted task as unassigned so it's removed from To Do.
        if ($task->todo_task_id) {
            $task->assigned_to = null;
            ToDo::push($task);
        }
    }

    private function sync(Task $task): void
    {
        $task->load(['assignee', 'todoUser']);
        if (! $task->assignee?->hasMicrosoft() && ! $task->todoUser?->hasMicrosoft()) {
            return;
        }
        app()->runningInConsole() ? ToDo::push($task) : app()->terminating(fn () => ToDo::push($task->fresh() ?? $task));
    }
}
