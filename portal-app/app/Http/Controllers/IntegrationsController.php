<?php

namespace App\Http\Controllers;

use App\Console\Commands\MicrosoftCheck;
use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Models\ApiKey;
use App\Models\Attachment;
use App\Services\CompletionReport;
use App\Services\Microsoft\Dataverse;
use App\Services\Microsoft\MicrosoftApp;
use App\Services\Microsoft\ReportFiler;
use App\Services\Microsoft\SharePoint;
use Illuminate\Http\Request;

/** admins: see and test the Microsoft 365 connections. Settings themselves live in .env on the server. */
class IntegrationsController extends Controller
{
    private function authorise(Request $request): void
    {
        abort_unless($request->user()->isOperatorAdmin(), 403);
    }

    public function index(Request $request)
    {
        $this->authorise($request);
        $recent = Attachment::where('kind', CompletionReport::KIND)->latest('id')->limit(10)->get();

        return view('integrations.index', [
            'app' => MicrosoftApp::isConfigured(),
            'signIn' => MicrosoftLoginController::enabled(),
            'sharePoint' => SharePoint::isConfigured(),
            'dataverse' => Dataverse::isConfigured(),
            'site' => config('services.microsoft.sharepoint_site'),
            'folder' => config('services.microsoft.sharepoint_folder'),
            'dataverseUrl' => config('services.microsoft.dataverse_url'),
            'results' => session('results'),
            'recent' => $recent,
            'pending' => SharePoint::isConfigured() ? ReportFiler::pending(500)->count() : 0,
            'keys' => ApiKey::with('creator')->whereNull('revoked_at')->latest()->get(),
        ]);
    }

    public function test(Request $request)
    {
        $this->authorise($request);

        return back()->with('results', MicrosoftCheck::results());
    }

    public function sync(Request $request)
    {
        $this->authorise($request);
        abort_unless(SharePoint::isConfigured(), 404);
        $done = $failed = 0;
        foreach (ReportFiler::pending(20) as $report) {
            ReportFiler::file($report) ? $done++ : $failed++;
        }

        return back()->with('status', "Copied {$done} report(s) to SharePoint.".($failed ? " {$failed} failed: see the list below." : ''));
    }

    /** A key for Power BI, Power Automate or an agent to read the portal's data (shown once). */
    public function createKey(Request $request)
    {
        $this->authorise($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        [$key, $secret] = ApiKey::issue($request->user()->account_id, $data['name'], $request->user()->id);
        \App\Services\Activity::log('api.key_created', "{$request->user()->name} created API key \"{$key->name}\".", $key);

        return back()->with('new_api_key', ['name' => $key->name, 'secret' => $secret]);
    }

    public function revokeKey(Request $request, ApiKey $apiKey)
    {
        $this->authorise($request);
        $apiKey->forceFill(['revoked_at' => now()])->save();
        \App\Services\Activity::log('api.key_revoked', "{$request->user()->name} revoked API key \"{$apiKey->name}\".", $apiKey);

        return back()->with('status', "\"{$apiKey->name}\" can no longer read the portal's data.");
    }
}
