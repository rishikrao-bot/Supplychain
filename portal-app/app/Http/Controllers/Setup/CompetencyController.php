<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetencyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isCompassStaff(), 403);
        $groups = CompetencyGroup::with(['competencies' => fn ($q) => $q->withCount(['organisations' => fn ($o) => $o->whereNull('suspended_at')])->orderBy('name')])->orderBy('sort')->get();

        return view('setup.competencies', ['groups' => $groups, 'canEdit' => $request->user()->isCompassAdmin()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isCompassAdmin(), 403);
        $acct = $request->user()->account_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('competencies', 'name')->where('account_id', $acct)],
            'group_id' => ['nullable', Rule::exists('competency_groups', 'id')->where('account_id', $acct)],
            'new_group' => ['nullable', 'required_without:group_id', 'string', 'max:80'],
            'certificate_required' => ['nullable', 'string', 'max:80'],
        ], ['name.unique' => 'That competency is already on the list.', 'new_group.required_without' => 'Choose a group or name a new one.']);
        $groupId = $data['group_id'] ?? CompetencyGroup::create(['name' => $data['new_group'], 'sort' => (CompetencyGroup::max('sort') ?? 0) + 1])->id;
        $c = Competency::create(['competency_group_id' => $groupId, 'name' => $data['name'], 'certificate_required' => $data['certificate_required'] ?? null]);
        Activity::log('competency.created', "{$request->user()->name} added competency {$c->name}.", $c);

        return back()->with('status', "{$c->name} added. Tick it on any contractor who holds it.");
    }
}
