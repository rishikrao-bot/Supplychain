<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Organisation;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only data for Power BI, Power Automate and agents. Every table answers
 * GET /api/v1/{table} with {"value": [ ...rows ]} (the same shape as OData, so Power Query reads it easily).
 * Costs and margins are included: keys are for Compass's own tools only.
 */
class DataController extends Controller
{
    public const TABLES = ['companies', 'contacts', 'opportunities', 'activities', 'tasks', 'jobs', 'projects', 'staff', 'allocations', 'utilisation'];

    private function accountId(Request $r): int
    {
        return $r->attributes->get('api_key')->account_id;
    }

    private function q(Request $r, string $model): Builder
    {
        return $model::withoutGlobalScopes()->where((new $model)->getTable().'.account_id', $this->accountId($r));
    }

    private function out(iterable $rows): JsonResponse
    {
        return response()->json(['value' => collect($rows)->values()], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function index()
    {
        return response()->json(['value' => array_map(fn ($t) => ['name' => $t, 'url' => url('/api/v1/'.$t)], self::TABLES)]);
    }

    public function companies(Request $r)
    {
        return $this->out($this->q($r, Organisation::class)->where('type', '!=', Organisation::COMPASS)->orderBy('id')->get()
            ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'type' => $o->type, 'sector' => $o->sector, 'phone' => $o->phone ?? $o->contact_phone,
                'email' => $o->email ?? $o->contact_email, 'postcode' => $o->postcode, 'owner_id' => $o->owner_id, 'created_at' => $o->created_at?->toIso8601String()]));
    }

    public function contacts(Request $r)
    {
        return $this->out($this->q($r, Contact::class)->orderBy('id')->get()
            ->map(fn ($c) => ['id' => $c->id, 'company_id' => $c->organisation_id, 'name' => $c->name(), 'job_title' => $c->job_title,
                'email' => $c->email, 'phone' => $c->phone, 'mobile' => $c->mobile, 'owner_id' => $c->owner_id]));
    }

    public function opportunities(Request $r)
    {
        return $this->out($this->q($r, Opportunity::class)->with('owner:id,name')->orderBy('id')->get()
            ->map(fn ($o) => ['id' => $o->id, 'title' => $o->title, 'company_id' => $o->organisation_id, 'contact_id' => $o->contact_id,
                'kind' => $o->kind, 'stage' => $o->stage, 'open' => $o->isOpen(), 'value' => $o->value !== null ? (float) $o->value : null,
                'probability' => $o->probability, 'weighted_value' => $o->value !== null ? round((float) $o->value * ($o->probability ?? 50) / 100, 2) : null,
                'return_date' => $o->return_date?->toDateString(), 'decision_date' => $o->decision_date?->toDateString(),
                'closed_at' => $o->closed_at?->toIso8601String(), 'lost_reason' => $o->lost_reason, 'owner' => $o->owner?->name,
                'created_at' => $o->created_at?->toIso8601String()]));
    }

    public function activities(Request $r)
    {
        return $this->out($this->q($r, CrmActivity::class)->with('user:id,name')->orderBy('id')->get()
            ->map(fn ($a) => ['id' => $a->id, 'type' => $a->type, 'subject' => $a->subject, 'company_id' => $a->organisation_id,
                'contact_id' => $a->contact_id, 'opportunity_id' => $a->opportunity_id, 'occurred_at' => $a->occurred_at?->toIso8601String(), 'by' => $a->user?->name]));
    }

    public function tasks(Request $r)
    {
        return $this->out($this->q($r, Task::class)->with('assignee:id,name')->orderBy('id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'status' => $t->status, 'due_on' => $t->due_on?->toDateString(),
                'overdue' => $t->isOverdue(), 'assigned_to' => $t->assignee?->name, 'company_id' => $t->organisation_id, 'contact_id' => $t->contact_id,
                'opportunity_id' => $t->opportunity_id, 'completed_at' => $t->completed_at?->toIso8601String(), 'created_at' => $t->created_at?->toIso8601String()]));
    }

    public function jobs(Request $r)
    {
        return $this->out($this->q($r, WorkOrder::class)->with(['client:id,name', 'contract:id,name', 'site:id,name,zone'])->orderBy('id')->get()
            ->map(fn ($w) => ['id' => $w->id, 'ref' => $w->ref(), 'client' => $w->client?->name, 'contract' => $w->contract?->name, 'site' => $w->site?->name,
                'zone' => $w->site?->zone, 'source' => $w->source, 'priority' => $w->priority, 'status' => $w->status, 'client_ref' => $w->client_ref,
                'client_order_no' => $w->client_order_no, 'estimate_total' => $w->estimate_total !== null ? (float) $w->estimate_total : null,
                'approved_amount' => $w->approved_amount !== null ? (float) $w->approved_amount : null,
                'received_at' => $w->received_at?->toIso8601String(), 'approved_at' => $w->approved_at?->toIso8601String()]));
    }

    public function projects(Request $r)
    {
        $days = Allocation::withoutGlobalScopes()->where('account_id', $this->accountId($r))->selectRaw('project_id, sum(days) as d')->groupBy('project_id')->pluck('d', 'project_id');

        return $this->out($this->q($r, Project::class)->with(['client:id,name', 'lead:id,name'])->orderBy('id')->get()
            ->map(fn ($p) => ['id' => $p->id, 'number' => $p->number, 'name' => $p->name, 'client' => $p->clientLabel(), 'type' => $p->type,
                'lead' => $p->lead?->name, 'status' => $p->status, 'internal' => $p->internal,
                'days_allowed' => $p->days_allowed !== null ? (float) $p->days_allowed : null, 'days_planned' => (float) ($days[$p->id] ?? 0),
                'completion_on' => $p->completion_on?->toDateString()]));
    }

    public function staff(Request $r)
    {
        return $this->out($this->q($r, Staff::class)->orderBy('id')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'position' => $s->position, 'team' => $s->team, 'days_per_week' => (float) $s->days_per_week,
                'active' => $s->active, 'starts_on' => $s->starts_on?->toDateString(), 'leaves_on' => $s->leaves_on?->toDateString()]));
    }

    /** One row per person, project and week. Optional ?from=YYYY-MM-DD&to=YYYY-MM-DD. */
    public function allocations(Request $r)
    {
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return $this->out($this->q($r, Allocation::class)
            ->when($r->query('from'), fn ($q, $d) => $q->where('week', '>=', $d))->when($r->query('to'), fn ($q, $d) => $q->where('week', '<=', $d))
            ->orderBy('week')->get(['staff_id', 'project_id', 'week', 'days'])
            ->map(fn ($a) => ['staff_id' => $a->staff_id, 'project_id' => $a->project_id, 'week_ending' => $a->week->toDateString(), 'days' => (float) $a->days]));
    }

    /** One row per person per week: days booked, days available and utilisation %. */
    public function utilisation(Request $r)
    {
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $staff = $this->q($r, Staff::class)->get()->keyBy('id');

        return $this->out($this->q($r, Allocation::class)
            ->when($r->query('from'), fn ($q, $d) => $q->where('week', '>=', $d))->when($r->query('to'), fn ($q, $d) => $q->where('week', '<=', $d))
            ->selectRaw('staff_id, week, sum(days) as d')->groupBy('staff_id', 'week')->orderBy('week')->get()
            ->map(function ($row) use ($staff) {
                $s = $staff[$row->staff_id];
                $cap = (float) $s->days_per_week;

                return ['staff_id' => $s->id, 'name' => $s->name, 'team' => $s->team, 'week_ending' => substr((string) $row->week, 0, 10),
                    'days_booked' => (float) $row->d, 'days_available' => $cap, 'utilisation_pct' => $cap > 0 ? round(100 * $row->d / $cap, 1) : null];
            }));
    }
}
