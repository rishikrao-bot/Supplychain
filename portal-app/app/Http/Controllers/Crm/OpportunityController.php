<?php

namespace App\Http\Controllers\Crm;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Organisation;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pipeline: enquiries, tenders and frameworks. */
class OpportunityController extends CrmController
{
    public function index(Request $request)
    {
        $this->guard($request);
        $view = in_array($request->query('show'), ['open', 'won', 'lost', 'all'], true) ? $request->query('show') : 'open';
        $mine = $request->boolean('mine');
        $items = Opportunity::with(['organisation', 'owner'])
            ->when($view === 'open', fn ($q) => $q->whereIn('stage', Opportunity::openStages()))
            ->when(in_array($view, ['won', 'lost'], true), fn ($q) => $q->where('stage', $view))
            ->when($mine, fn ($q) => $q->where('owner_id', $request->user()->id))
            ->orderByRaw('return_date is null')->orderBy('return_date')->latest('id')->get();
        $summary = Opportunity::whereIn('stage', Opportunity::openStages())->selectRaw('stage, count(*) as n, sum(value) as total, sum(value * coalesce(probability, 50) / 100) as weighted')
            ->groupBy('stage')->get()->keyBy('stage');
        $year = Opportunity::whereIn('stage', ['won', 'lost'])->where('closed_at', '>=', now()->subYear())->selectRaw('stage, count(*) as n, sum(value) as total')->groupBy('stage')->get()->keyBy('stage');

        return view('crm.pipeline.index', compact('items', 'summary', 'year', 'view', 'mine'));
    }

    public function show(Request $request, Opportunity $opportunity)
    {
        $this->guard($request);
        $opportunity->load(['organisation', 'contact', 'owner', 'tasks' => fn ($t) => $t->with('assignee')->orderBy('status')->orderBy('due_on')]);

        return view('crm.pipeline.show', [
            'opp' => $opportunity,
            'activities' => $opportunity->activities()->with(['user', 'contact'])->limit(100)->get(),
            'staff' => $this->staff(),
        ]);
    }

    private function options(): array
    {
        return [
            'companies' => Organisation::where('type', '!=', Organisation::OPERATOR)->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::with('organisation:id,name')->orderBy('first_name')->get(),
            'staff' => $this->staff(),
        ];
    }

    public function create(Request $request)
    {
        $this->guard($request);
        $contact = $request->integer('contact') ? Contact::find($request->integer('contact')) : null;

        return view('crm.pipeline.form', ['opp' => new Opportunity([
            'organisation_id' => $request->integer('company') ?: $contact?->organisation_id, 'contact_id' => $contact?->id,
            'kind' => $request->query('kind', 'tender'), 'stage' => 'lead', 'owner_id' => $request->user()->id,
        ])] + $this->options());
    }

    public function edit(Request $request, Opportunity $opportunity)
    {
        $this->guard($request);

        return view('crm.pipeline.form', ['opp' => $opportunity] + $this->options());
    }

    private function data(Request $request, ?Opportunity $opp = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'organisation_id' => $this->exists($request, 'organisations'),
            'contact_id' => $this->exists($request, 'contacts'),
            'kind' => ['required', Rule::in(array_keys(Opportunity::KINDS))],
            'stage' => ['required', Rule::in(array_keys(Opportunity::STAGES))],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'return_date' => ['nullable', 'date'],
            'decision_date' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:190'],
            'owner_id' => $this->staffRule(),
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $closed = in_array($data['stage'], ['won', 'lost'], true);
        $data['closed_at'] = $closed ? ($opp?->closed_at ?? now()) : null;
        if ($data['stage'] === 'won') {
            $data['probability'] = 100;
        } elseif ($data['stage'] === 'lost') {
            $data['probability'] = 0;
        }

        return $data;
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $opp = Opportunity::create($this->data($request));
        Activity::log('crm.opportunity_created', "{$request->user()->name} added {$opp->title} to the pipeline.", $opp);

        return redirect()->route('pipeline.show', $opp)->with('status', "{$opp->title} added.");
    }

    public function update(Request $request, Opportunity $opportunity)
    {
        $this->guard($request);
        $before = $opportunity->stage;
        $opportunity->update($this->data($request, $opportunity));
        if ($before !== $opportunity->stage) {
            Activity::log('crm.opportunity_stage', "{$request->user()->name} moved {$opportunity->title} to {$opportunity->stageLabel()}.", $opportunity);
            // A won tender turns a prospect into a client.
            if ($opportunity->stage === 'won' && $opportunity->organisation?->type === Organisation::PROSPECT) {
                $opportunity->organisation->update(['type' => Organisation::CLIENT]);
            }
        }

        return redirect()->route('pipeline.show', $opportunity)->with('status', 'Saved.');
    }
}
