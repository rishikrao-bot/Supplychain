<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\CompetencyGroup;
use App\Models\ComplianceDocument;
use App\Models\Organisation;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Supply chain partners: details, coverage, competencies and compliance documents. */
class ContractorController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless($r->user()->isOperatorStaff(), 403);
    }

    private function partner(Organisation $org): Organisation
    {
        abort_unless($org->isPartner(), 404);

        return $org;
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $partners = Organisation::where('type', 'partner')->with(['competencies', 'documents.user', 'users'])->orderBy('name')->get();

        return view('setup.contractors.index', ['partners' => $partners, 'showAll' => (bool) $request->query('all')]);
    }

    public function create(Request $request)
    {
        $this->guard($request);

        return view('setup.contractors.form', ['org' => new Organisation(['type' => 'partner', 'partner_type' => 'subcontractor', 'zones' => ['A'], 'vat_registered' => true]), 'groups' => $this->groups(), 'selected' => []]);
    }

    public function edit(Request $request, Organisation $contractor)
    {
        $this->guard($request);
        $this->partner($contractor);

        return view('setup.contractors.form', ['org' => $contractor, 'groups' => $this->groups(), 'selected' => $contractor->competencies->pluck('id')->all()]);
    }

    private function groups()
    {
        return CompetencyGroup::with(['competencies' => fn ($q) => $q->orderBy('name')])->orderBy('sort')->get();
    }

    private function validated(Request $request): array
    {
        $acct = $request->user()->account_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'partner_type' => ['required', Rule::in(['subcontractor', 'specialist'])],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'zones' => ['required', 'array', 'min:1'],
            'zones.*' => [Rule::in(['A', 'B', 'C'])],
            'job_limit' => ['nullable', 'numeric', 'min:0'],
            'vat_registered' => ['nullable', 'boolean'],
            'rate_normal' => ['nullable', 'numeric', 'min:0'],
            'rate_ooh' => ['nullable', 'numeric', 'min:0'],
            'rate_sun' => ['nullable', 'numeric', 'min:0'],
            'rate_callout' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'competencies' => ['array'],
            'competencies.*' => [Rule::exists('competencies', 'id')->where('account_id', $acct)],
        ], ['zones.required' => 'Tick at least one zone.']);
        $data['vat_registered'] = (bool) ($data['vat_registered'] ?? false);
        $data['rates'] = $data['partner_type'] === 'subcontractor'
            ? ['normal' => $data['rate_normal'] ?? null, 'ooh' => $data['rate_ooh'] ?? null, 'sun' => $data['rate_sun'] ?? null, 'callout' => $data['rate_callout'] ?? null]
            : null;
        unset($data['rate_normal'], $data['rate_ooh'], $data['rate_sun'], $data['rate_callout']);

        return $data;
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $data = $this->validated($request);
        $comps = $data['competencies'] ?? [];
        unset($data['competencies']);
        $org = Organisation::create($data + ['type' => 'partner', 'account_id' => $request->user()->account_id]);
        $org->competencies()->sync($comps);
        Activity::log('contractor.created', "{$request->user()->name} added contractor {$org->name}.", $org);

        return redirect()->route('contractors.index')->with('status', "{$org->name} added. Add their documents, then invite their manager from Users & access.");
    }

    public function update(Request $request, Organisation $contractor)
    {
        $this->guard($request);
        $this->partner($contractor);
        $data = $this->validated($request);
        $comps = $data['competencies'] ?? [];
        unset($data['competencies']);
        $contractor->update($data);
        $contractor->competencies()->sync($comps);
        Activity::log('contractor.updated', "{$request->user()->name} updated contractor {$contractor->name}.", $contractor);

        return redirect()->route('contractors.index')->with('status', "{$contractor->name} saved.");
    }

    public function suspend(Request $request, Organisation $contractor)
    {
        $this->guard($request);
        $this->partner($contractor);
        $on = ! $contractor->suspended_at;
        $contractor->update(['suspended_at' => $on ? now() : null]);
        Activity::log($on ? 'contractor.suspended' : 'contractor.unsuspended', "{$request->user()->name} ".($on ? 'suspended' : 'lifted the suspension on')." {$contractor->name}.", $contractor);

        return back()->with('status', $on ? "{$contractor->name} suspended: no new offers until you lift it." : "{$contractor->name} can receive offers again.");
    }

    public function storeDocument(Request $request, Organisation $contractor)
    {
        $this->guard($request);
        $this->partner($contractor);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('organisation_id', $contractor->id)],
            'expires_on' => ['required', 'date'],
            'blocks_offers' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,heic,doc,docx'],
        ]);
        $path = $request->file('file')?->store('compliance/'.$contractor->account_id.'/'.$contractor->id, 'local');
        $doc = $contractor->documents()->create([
            'account_id' => $contractor->account_id, 'user_id' => $data['user_id'] ?? null, 'name' => $data['name'], 'expires_on' => $data['expires_on'],
            'blocks_offers' => (bool) ($data['blocks_offers'] ?? false), 'file_path' => $path, 'status' => 'approved',
            'uploaded_by' => $request->user()->id, 'approved_by' => $request->user()->id, 'approved_at' => now(),
        ]);
        Activity::log('document.added', "{$request->user()->name} added {$doc->name} for {$contractor->name} (expires {$doc->expires_on->format('j M Y')}).", $doc);

        return back()->with('status', "{$doc->name} saved for {$contractor->name}.");
    }

    public function downloadDocument(Request $request, ComplianceDocument $document)
    {
        $this->guard($request);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path);
    }
}
