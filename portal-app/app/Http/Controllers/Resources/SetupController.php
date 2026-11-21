<?php

namespace App\Http\Controllers\Resources;

use App\Models\Organisation;
use App\Models\Project;
use App\Models\Staff;
use App\Services\Activity;
use App\Services\ResourcePlan;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Projects and people for the planner. */
class SetupController extends PlannerController
{
    public function projects(Request $request)
    {
        $this->view($request);
        $show = $request->query('show') === 'complete' ? 'complete' : 'active';
        $q = trim((string) $request->query('q'));
        $projects = Project::with(['client', 'lead'])->where('status', $show)
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('number', 'like', "%$q%")->orWhere('client_name', 'like', "%$q%")))
            ->orderByDesc('internal')->orderByRaw('number is null')->orderBy('number')->get();

        return view('resources.projects', ['projects' => $projects, 'days' => ResourcePlan::projectDays(), 'show' => $show, 'q' => $q,
            'canEdit' => $request->user()->isCompassAdmin()]);
    }

    private function projectForm(Project $project)
    {
        return view('resources.project-form', [
            'project' => $project,
            'clients' => Organisation::whereIn('type', [Organisation::CLIENT, Organisation::PROSPECT, Organisation::OTHER])->orderBy('name')->get(['id', 'name']),
            'staff' => Staff::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function createProject(Request $request)
    {
        $this->edit($request);

        return $this->projectForm(new Project(['status' => 'active', 'type' => 'TSM']));
    }

    public function editProject(Request $request, Project $project)
    {
        $this->edit($request);

        return $this->projectForm($project);
    }

    private function projectData(Request $request, ?Project $project = null): array
    {
        $acc = $request->user()->account_id;

        return $request->validate([
            'number' => ['nullable', 'string', 'max:20', Rule::unique('projects')->where('account_id', $acc)->ignore($project?->id)],
            'name' => ['required', 'string', 'max:190'],
            'organisation_id' => ['nullable', 'integer', Rule::exists('organisations', 'id')->where('account_id', $acc)],
            'client_name' => ['nullable', 'string', 'max:160'],
            'lead_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('account_id', $acc)],
            'type' => ['nullable', 'string', 'max:40'],
            'days_allowed' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'completion_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'complete'])],
            'internal' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]) + ['internal' => false];
    }

    public function storeProject(Request $request)
    {
        $this->edit($request);
        $p = Project::create($this->projectData($request) + ['account_id' => $request->user()->account_id]);
        Activity::log('resources.project_created', "{$request->user()->name} added project {$p->label()}.", $p);

        return redirect()->route('resources.project', $p)->with('status', "{$p->label()} added. Add people to it below.");
    }

    public function updateProject(Request $request, Project $project)
    {
        $this->edit($request);
        $project->update($this->projectData($request, $project));

        return redirect()->route('resources.project', $project)->with('status', 'Project saved.');
    }

    public function staff(Request $request)
    {
        $this->view($request);

        return view('resources.staff', ['staff' => Staff::with('user')->orderByDesc('active')->orderBy('team')->orderBy('name')->get(),
            'canEdit' => $request->user()->isCompassAdmin()]);
    }

    private function staffForm(Staff $staff)
    {
        $users = \App\Models\User::whereIn('role', [Roles::ADMIN, Roles::HELPDESK, Roles::FINANCE, Roles::ENGINEER])
            ->whereHas('organisation', fn ($q) => $q->where('type', Organisation::COMPASS))->orderBy('name')->get();

        return view('resources.staff-form', ['person' => $staff, 'users' => $users]);
    }

    public function createStaff(Request $request)
    {
        $this->edit($request);

        return $this->staffForm(new Staff(['days_per_week' => 5, 'active' => true, 'team' => 'Project Management']));
    }

    public function editStaff(Request $request, Staff $staff)
    {
        $this->edit($request);

        return $this->staffForm($staff);
    }

    private function staffData(Request $request, ?Staff $staff = null): array
    {
        $acc = $request->user()->account_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('staff')->where('account_id', $acc)->ignore($staff?->id)],
            'position' => ['nullable', 'string', 'max:120'],
            'team' => ['nullable', Rule::in(Staff::TEAMS)],
            'days_per_week' => ['required', 'numeric', 'min:0.5', 'max:7'],
            'starts_on' => ['nullable', 'date'],
            'leaves_on' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('account_id', $acc)],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => false];
    }

    public function storeStaff(Request $request)
    {
        $this->edit($request);
        $s = Staff::create($this->staffData($request) + ['account_id' => $request->user()->account_id]);
        Activity::log('resources.staff_created', "{$request->user()->name} added {$s->name} to the resource plan.", $s);

        return redirect()->route('resources.person', $s)->with('status', "{$s->name} added.");
    }

    public function updateStaff(Request $request, Staff $staff)
    {
        $this->edit($request);
        $staff->update($this->staffData($request, $staff));

        return redirect()->route('resources.staff')->with('status', "{$staff->name} saved.");
    }
}
