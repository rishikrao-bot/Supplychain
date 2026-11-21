<?php

namespace App\Http\Controllers\Crm;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Organisation;
use App\Models\Task;
use App\Services\Microsoft\MicrosoftApp;
use App\Services\Microsoft\MicrosoftUser;
use App\Services\Microsoft\ToDo;
use Illuminate\Http\Request;
use Throwable;

class TaskController extends CrmController
{
    public function index(Request $request)
    {
        $this->guard($request);
        $me = $request->user();
        $show = in_array($request->query('show'), ['mine', 'all', 'done'], true) ? $request->query('show') : 'mine';
        $tasks = Task::with(['assignee', 'organisation', 'contact', 'opportunity'])
            ->when($show === 'mine', fn ($q) => $q->where('assigned_to', $me->id)->where('status', Task::OPEN))
            ->when($show === 'all', fn ($q) => $q->where('status', Task::OPEN))
            ->when($show === 'done', fn ($q) => $q->where('status', Task::DONE)->where('completed_at', '>=', now()->subDays(60)))
            ->when($show === 'done', fn ($q) => $q->latest('completed_at'), fn ($q) => $q->orderByRaw('due_on is null')->orderBy('due_on'))
            ->limit(300)->get();

        return view('crm.tasks.index', [
            'tasks' => $tasks, 'show' => $show, 'staff' => $this->staff(),
            'microsoftReady' => MicrosoftApp::isConfigured(),
            'counts' => ['mine' => Task::where('assigned_to', $me->id)->where('status', Task::OPEN)->count(), 'all' => Task::where('status', Task::OPEN)->count()],
        ]);
    }

    public function show(Request $request, Task $task)
    {
        $this->guard($request);

        return view('crm.tasks.form', ['task' => $task->load(['organisation', 'contact', 'opportunity', 'assignee'])] + $this->options());
    }

    private function options(): array
    {
        return [
            'staff' => $this->staff(),
            'companies' => Organisation::where('type', '!=', Organisation::COMPASS)->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::with('organisation:id,name')->orderBy('first_name')->get(),
            'opportunities' => Opportunity::whereIn('stage', Opportunity::openStages())->orderBy('title')->get(['id', 'title']),
        ];
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_on' => ['nullable', 'date'],
            'assigned_to' => $this->staffRule(),
            'organisation_id' => $this->exists($request, 'organisations'),
            'contact_id' => $this->exists($request, 'contacts'),
            'opportunity_id' => $this->exists($request, 'opportunities'),
        ]);
        // Fill in the company from the contact or opportunity, so the task shows on the company too.
        if (empty($data['organisation_id'])) {
            $data['organisation_id'] = ! empty($data['contact_id']) ? Contact::find($data['contact_id'])?->organisation_id
                : (! empty($data['opportunity_id']) ? Opportunity::find($data['opportunity_id'])?->organisation_id : null);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $task = Task::create($this->data($request) + ['status' => Task::OPEN, 'created_by' => $request->user()->id,
            'assigned_to' => $request->input('assigned_to') ?: $request->user()->id]);

        return back()->with('status', "Task added for {$task->assignee?->name}.");
    }

    public function update(Request $request, Task $task)
    {
        $this->guard($request);
        $task->update($this->data($request));

        return redirect()->route('tasks.index')->with('status', 'Task saved.');
    }

    /** Tick off (or reopen). */
    public function toggle(Request $request, Task $task)
    {
        $this->guard($request);
        $done = ! $task->isDone();
        $task->update(['status' => $done ? Task::DONE : Task::OPEN, 'completed_at' => $done ? now() : null]);

        return back()->with('status', $done ? "Done: {$task->title}" : "Reopened: {$task->title}");
    }

    public function destroy(Request $request, Task $task)
    {
        $this->guard($request);
        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Task deleted.');
    }

    /** Pull changes from my To Do now, rather than waiting for the 5-minute sync. */
    public function sync(Request $request)
    {
        $this->guard($request);
        $me = $request->user();
        abort_unless($me->hasMicrosoft(), 404);
        try {
            Task::where('assigned_to', $me->id)->where(fn ($q) => $q->whereNull('todo_task_id')->orWhere('todo_pending', true))->get()->each(fn ($t) => ToDo::push($t));
            [$updated, $created] = ToDo::pull($me);

            return back()->with('status', "Synced with To Do: {$updated} updated, {$created} new.");
        } catch (Throwable $e) {
            return back()->withErrors(['microsoft' => $e->getMessage()]);
        }
    }

    public function disconnect(Request $request)
    {
        $this->guard($request);
        MicrosoftUser::disconnect($request->user());

        return back()->with('status', 'Outlook and To Do are disconnected. Your tasks stay in the portal; the "Compass MEP Portal" list stays in To Do.');
    }
}
