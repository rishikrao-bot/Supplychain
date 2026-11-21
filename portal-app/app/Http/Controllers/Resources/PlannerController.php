<?php

namespace App\Http\Controllers\Resources;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Staff;
use App\Services\Activity;
use App\Services\ResourcePlan;
use App\Support\Weeks;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The resource planner. Compass office staff can look; Compass admins (Mike) change the plan.
 */
class PlannerController extends Controller
{
    protected function view(Request $r): void
    {
        abort_unless($r->user()->canUseCrm(), 403);
    }

    protected function edit(Request $r): void
    {
        abort_unless($r->user()->canUseCrm() && $r->user()->isCompassAdmin(), 403);
    }

    /** Weeks shown: ?from=YYYY-MM-DD (any day; snapped to its Friday), ?weeks=N. Starts one week back by default. */
    protected function weeks(Request $r, int $default = 12): array
    {
        $from = $r->query('from') ? Weeks::ending((string) $r->query('from')) : Weeks::ending()->subWeek();
        $n = min(52, max(4, (int) $r->query('weeks', $default)));

        return Weeks::range($from, $n);
    }

    /** Everyone's utilisation, week by week (the old "Utilisation" sheet, but always complete). */
    public function index(Request $request)
    {
        $this->view($request);
        $weeks = $this->weeks($request);
        $staff = Staff::where('active', true)->orderBy('sort')->orderBy('name')->get()->toBase()->groupBy(fn ($s) => $s->team ?: 'Other');
        $teams = collect(Staff::TEAMS)->filter(fn ($t) => $staff->has($t))->mapWithKeys(fn ($t) => [$t => $staff[$t]])
            ->union($staff->except(Staff::TEAMS));

        return view('resources.index', [
            'weeks' => $weeks, 'teams' => $teams, 'totals' => ResourcePlan::totals($weeks),
            'thisWeek' => Weeks::ending()->toDateString(), 'canEdit' => $request->user()->isCompassAdmin(),
        ]);
    }

    /** One person: their projects (rows) by week (columns), editable. */
    public function person(Request $request, Staff $staff)
    {
        $this->view($request);
        $weeks = $this->weeks($request);
        $grid = ResourcePlan::grid($weeks, staffId: $staff->id);
        $ids = array_keys($grid);
        if ($add = $request->integer('add')) {
            $ids[] = $add;
        }
        $projects = Project::whereIn('id', $ids)->orderByDesc('internal')->orderBy('number')->get();

        return view('resources.person', [
            'staff' => $staff, 'weeks' => $weeks, 'grid' => $grid, 'projects' => $projects,
            'totals' => ResourcePlan::totals($weeks)[$staff->id] ?? [],
            'allProjects' => Project::where('status', 'active')->orderByDesc('internal')->orderBy('number')->get(),
            'thisWeek' => Weeks::ending()->toDateString(), 'canEdit' => $request->user()->isCompassAdmin(),
        ]);
    }

    /** One project: people (rows) by week (columns), editable. */
    public function project(Request $request, Project $project)
    {
        $this->view($request);
        $weeks = $this->weeks($request);
        $grid = ResourcePlan::grid($weeks, projectId: $project->id)[$project->id] ?? [];
        $ids = array_keys($grid);
        if ($add = $request->integer('add')) {
            $ids[] = $add;
        }
        $people = Staff::whereIn('id', $ids)->orderBy('name')->get();
        $days = ResourcePlan::projectDays()[$project->id] ?? null;

        return view('resources.project', [
            'project' => $project->load(['client', 'lead']), 'weeks' => $weeks, 'grid' => $grid, 'people' => $people, 'days' => $days,
            'totals' => ResourcePlan::totals($weeks),
            'allStaff' => Staff::where('active', true)->orderBy('name')->get(),
            'thisWeek' => Weeks::ending()->toDateString(), 'canEdit' => $request->user()->isCompassAdmin(),
        ]);
    }

    /** Save a grid: cells[projectId-staffId][Y-m-d] = days. */
    public function save(Request $request)
    {
        $this->edit($request);
        $request->validate(['cells' => ['array'], 'cells.*' => ['array'], 'cells.*.*' => ['nullable', 'numeric', 'min:0', 'max:7']]);
        $projects = Project::pluck('id')->flip();
        $staff = Staff::pluck('id')->flip();
        $changed = 0;
        foreach ($request->input('cells', []) as $pair => $weeks) {
            [$p, $s] = array_map('intval', explode('-', (string) $pair) + [0, 0]);
            abort_unless(isset($projects[$p], $staff[$s]), 422);
            $project = Project::find($p);
            $person = Staff::find($s);
            foreach ($weeks as $week => $days) {
                abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $week), 422);
                $changed += ResourcePlan::set($project, $person, Weeks::ending($week), $days === null || $days === '' ? null : (float) $days) ? 1 : 0;
            }
        }
        if ($changed) {
            Activity::log('resources.saved', "{$request->user()->name} changed {$changed} week(s) in the resource plan.");
        }

        return back()->with('status', $changed ? "Saved {$changed} change(s)." : 'No changes.');
    }

    /** "Put Paul on Westbury Hotel for 1 day a week from 7 Nov to 30 Jan." */
    public function fill(Request $request)
    {
        $this->edit($request);
        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('account_id', $request->user()->account_id)],
            'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where('account_id', $request->user()->account_id)],
            'days' => ['required', 'numeric', 'min:0', 'max:7'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $from = Weeks::ending($data['from']);
        $to = Weeks::ending($data['to']);
        abort_if($from->diffInWeeks($to) > 104, 422);
        $project = Project::findOrFail($data['project_id']);
        $person = Staff::findOrFail($data['staff_id']);
        $n = ResourcePlan::fill($project, $person, $from, $to, (float) $data['days'], (bool) ($data['overwrite'] ?? true));
        Activity::log('resources.filled', "{$request->user()->name} planned {$person->name} on {$project->label()} at {$data['days']} day(s) a week, w/e {$from->format('j M')} to {$to->format('j M Y')}.");

        return back()->with('status', (float) $data['days'] > 0
            ? "{$person->name}: {$data['days']} day(s) a week on {$project->label()} for {$n} week(s)."
            : "{$person->name} taken off {$project->label()} for {$n} week(s).");
    }

    /** The whole plan as a CSV file (opens in Excel). */
    public function export(Request $request)
    {
        $this->view($request);
        $weeks = $this->weeks($request, 26);
        $grid = ResourcePlan::grid($weeks);
        $projects = Project::whereIn('id', array_keys($grid))->get()->keyBy('id');
        $staff = Staff::get()->keyBy('id');
        $rows = [];
        foreach ($grid as $p => $byStaff) {
            foreach ($byStaff as $s => $byWeek) {
                $rows[] = [$staff[$s]->name ?? '?', $staff[$s]->team ?? '', $projects[$p]->number ?? '', $projects[$p]->name ?? '',
                    ...array_map(fn (Carbon $w) => $byWeek[$w->toDateString()] ?? '', $weeks)];
            }
        }
        usort($rows, fn ($a, $b) => [$a[0], $a[2]] <=> [$b[0], $b[2]]);
        $name = 'resource-plan-'.$weeks[0]->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows, $weeks) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Person', 'Team', 'Project no.', 'Project', ...array_map(fn ($w) => 'w/e '.$w->format('d/m/Y'), $weeks)]);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
