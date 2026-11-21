<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Services\Activity;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Management reports: Power BI reports shown inside the portal ("secure embed"). Viewers sign in to
 * Power BI with their own Microsoft account and need a Pro/E5 licence; Power BI's own permissions and
 * row-level security still apply. Compass admins choose which reports appear and who sees them.
 */
class ReportsController extends Controller
{
    private const ROLES = [Roles::ADMIN => 'Admins', Roles::FINANCE => 'Finance', Roles::HELPDESK => 'Helpdesk'];

    public function index(Request $request)
    {
        $me = $request->user();
        $dashboards = Dashboard::orderBy('sort')->orderBy('title')->get();
        $visible = $dashboards->filter(fn ($d) => $d->visibleTo($me));
        abort_unless($me->isCompassAdmin() || $visible->isNotEmpty(), 403);

        return view('reports.index', ['dashboards' => $me->isCompassAdmin() ? $dashboards : $visible, 'roles' => self::ROLES, 'canManage' => $me->isCompassAdmin()]);
    }

    public function show(Request $request, Dashboard $dashboard)
    {
        abort_unless($dashboard->visibleTo($request->user()), 403);
        $others = Dashboard::orderBy('sort')->orderBy('title')->get()->filter(fn ($d) => $d->visibleTo($request->user()));

        return view('reports.show', ['dashboard' => $dashboard, 'others' => $others]);
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'embed_url' => ['required', 'string', 'max:2000'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(array_keys(self::ROLES))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $url = Dashboard::normaliseUrl($data['embed_url']);
        if (! $url) {
            throw ValidationException::withMessages(['embed_url' => 'That isn\'t a Power BI report link. In Power BI open the report, then File → Embed report → Website or portal, and paste the link (or just copy the report\'s address from the browser).']);
        }
        $data['embed_url'] = $url;
        $data['sort'] ??= 0;

        return $data;
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isCompassAdmin(), 403);
        $d = Dashboard::create($this->data($request) + ['account_id' => $request->user()->account_id]);
        Activity::log('reports.added', "{$request->user()->name} added the report {$d->title}.", $d);

        return redirect()->route('reports.show', $d)->with('status', "{$d->title} added.");
    }

    public function update(Request $request, Dashboard $dashboard)
    {
        abort_unless($request->user()->isCompassAdmin(), 403);
        $dashboard->update($this->data($request));

        return redirect()->route('reports.index')->with('status', 'Saved.');
    }

    public function destroy(Request $request, Dashboard $dashboard)
    {
        abort_unless($request->user()->isCompassAdmin(), 403);
        $dashboard->delete();

        return redirect()->route('reports.index')->with('status', 'Removed from the portal (the report itself is still in Power BI).');
    }
}
