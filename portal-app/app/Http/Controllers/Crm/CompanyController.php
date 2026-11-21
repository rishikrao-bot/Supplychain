<?php

namespace App\Http\Controllers\Crm;

use App\Models\Opportunity;
use App\Models\Organisation;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends CrmController
{
    public function index(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q'));
        $type = $request->query('type');
        $companies = Organisation::where('type', '!=', Organisation::OPERATOR)
            ->when(isset(Organisation::CRM_TYPES[$type]), fn ($w) => $w->where('type', $type))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('postcode', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->withCount(['contacts', 'tasks as open_tasks_count' => fn ($t) => $t->where('status', Task::OPEN)])
            ->withSum(['opportunities as pipeline_value' => fn ($o) => $o->whereIn('stage', Opportunity::openStages())], 'value')
            ->with('owner')->orderBy('name')->paginate(50)->withQueryString();
        $counts = Organisation::where('type', '!=', Organisation::OPERATOR)->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return view('crm.companies.index', compact('companies', 'q', 'type', 'counts'));
    }

    public function show(Request $request, Organisation $company)
    {
        $this->guard($request);
        abort_if($company->isOperator(), 404);
        $company->load(['contacts', 'owner', 'opportunities' => fn ($o) => $o->with('owner')->latest(),
            'tasks' => fn ($t) => $t->with('assignee', 'contact')->orderBy('status')->orderByRaw('due_on is null')->orderBy('due_on')]);
        $activities = $company->crmActivities()->with(['user', 'contact', 'opportunity'])->limit(100)->get();
        $jobs = $company->isClient() ? WorkOrder::where('client_id', $company->id)->count() : null;

        return view('crm.companies.show', [
            'company' => $company, 'activities' => $activities, 'jobs' => $jobs,
            'emails' => $this->emails($request, array_merge([$company->email], $company->contacts->pluck('email')->all())),
            'staff' => $this->staff(),
        ]);
    }

    public function create(Request $request)
    {
        $this->guard($request);

        return view('crm.companies.form', ['company' => new Organisation(['type' => $request->query('type', Organisation::PROSPECT), 'owner_id' => $request->user()->id]), 'staff' => $this->staff()]);
    }

    public function edit(Request $request, Organisation $company)
    {
        $this->guard($request);
        abort_if($company->isOperator(), 404);

        return view('crm.companies.form', ['company' => $company, 'staff' => $this->staff()]);
    }

    private function validated(Request $request, ?Organisation $company = null): array
    {
        // Supply chain partners are set up under Contractors (compliance, rates); the CRM can't create them.
        $types = [Organisation::CLIENT, Organisation::PROSPECT, Organisation::SUPPLIER, Organisation::OTHER];
        if ($company?->isPartner()) {
            $types = [Organisation::PARTNER];
        }
        // A client with portal users or contracts stays a client.
        if ($company?->isClient() && ($company->users()->exists() || $company->contracts()->exists())) {
            $types = [Organisation::CLIENT];
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in($types)],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'sector' => ['nullable', 'string', 'max:80'],
            'owner_id' => $this->staffRule(),
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $company = Organisation::create($this->validated($request) + ['account_id' => $request->user()->account_id]);
        Activity::log('crm.company_created', "{$request->user()->name} added {$company->name} to the CRM.", $company);

        return redirect()->route('companies.show', $company)->with('status', "{$company->name} added.");
    }

    public function update(Request $request, Organisation $company)
    {
        $this->guard($request);
        abort_if($company->isOperator(), 404);
        $company->update($this->validated($request, $company));
        Activity::log('crm.company_updated', "{$request->user()->name} updated {$company->name}.", $company);

        return redirect()->route('companies.show', $company)->with('status', "{$company->name} saved.");
    }
}
