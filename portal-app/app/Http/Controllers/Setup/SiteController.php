<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Site;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless($r->user()->isOperatorStaff(), 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);
        $q = trim((string) $request->query('q'));
        $contracts = Contract::with(['client', 'sites' => fn ($s) => $s->when($q, fn ($s) => $s->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('postcode', 'like', "%$q%")->orWhere('address', 'like', "%$q%")))->orderBy('name')])->orderBy('name')->get();

        return view('setup.sites.index', compact('contracts', 'q'));
    }

    public function create(Request $request)
    {
        $this->guard($request);

        return view('setup.sites.form', ['site' => new Site(['zone' => 'A']), 'contracts' => Contract::with('client')->orderBy('name')->get()]);
    }

    public function edit(Request $request, Site $site)
    {
        $this->guard($request);

        return view('setup.sites.form', ['site' => $site, 'contracts' => Contract::with('client')->orderBy('name')->get()]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'contract_id' => ['required', Rule::exists('contracts', 'id')->where('account_id', $request->user()->account_id)],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'zone' => ['required', Rule::in(['A', 'B', 'C'])],
            'site_type' => ['nullable', 'string', 'max:80'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'asbestos_notes' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $site = Site::create($this->validated($request));
        Activity::log('site.created', "{$request->user()->name} added site {$site->name}.", $site);

        return redirect()->route('sites.index')->with('status', "{$site->name} added.");
    }

    public function update(Request $request, Site $site)
    {
        $this->guard($request);
        $site->update($this->validated($request));
        Activity::log('site.updated', "{$request->user()->name} updated site {$site->name}.", $site);

        return redirect()->route('sites.index')->with('status', "{$site->name} saved.");
    }
}
