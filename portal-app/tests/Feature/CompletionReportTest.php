<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\Visit;
use App\Models\WorkOrder;
use App\Services\CompletionReport;
use Illuminate\Support\Facades\Storage;

class CompletionReportTest extends PortalTestCase
{
    private function completedJob(?Organisation $partner = null): WorkOrder
    {
        $contract = Contract::withoutGlobalScopes()->where('name', 'Castle Point')->first();
        $site = Site::withoutGlobalScopes()->create(['account_id' => $this->account->id, 'contract_id' => $contract->id, 'name' => 'Waterside Farm Leisure Centre', 'address' => 'Canvey Island', 'postcode' => 'SS8', 'zone' => 'A']);
        $wo = WorkOrder::withoutGlobalScopes()->create([
            'account_id' => $this->account->id, 'number' => 1001, 'client_id' => $this->wates->id, 'contract_id' => $contract->id, 'site_id' => $site->id,
            'client_ref' => 'WTS-55120', 'client_order_no' => 'PO-771402', 'source' => 'phone', 'priority' => 'emergency', 'rate_band' => 'ooh',
            'location' => 'Pool hall', 'description' => 'Water leaking from ceiling void onto poolside.', 'status' => 'review',
            'approved_at' => now(), 'received_at' => now()->subDay(), 'assigned_org_id' => $partner?->id,
        ]);
        $wo->trades()->create(['trade' => 'building', 'est_hours' => 2, 'is_primary' => true]);
        $engineer = $this->makeUser($this->compass, 'engineer', ['name' => 'Dan Okafor']);
        $v = Visit::create(['work_order_id' => $wo->id, 'user_id' => $engineer->id, 'arrived_at' => now()->subHours(3), 'departed_at' => now()->subHour(),
            'arrival_lat' => 51.5303, 'arrival_lng' => 0.5713, 'safety_checklist' => ['signin' => true, 'asbestos' => true, 'rams' => true, 'permit' => true, 'safe' => true],
            'notes' => "Traced leak to failed 28mm valve.\nIsolated and capped.", 'outcome' => 'complete', 'signed_by' => 'Jo Harding (Duty Manager)']);
        $v->materials()->create(['description' => '28mm end caps x2', 'cost' => 18.40]);
        // A small real image as a "before" photo
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('local')->put('photos/test-before.png', $png);
        $v->attachments()->create(['account_id' => $this->account->id, 'kind' => 'photo_before', 'path' => 'photos/test-before.png', 'original_name' => 'before.png', 'mime' => 'image/png', 'size' => strlen($png), 'meta' => ['caption' => 'Leak above poolside', 'lat' => 51.5303, 'lng' => 0.5713]]);

        return $wo;
    }

    public function test_report_is_created_and_attached_when_job_is_completed(): void
    {
        Storage::fake('local');
        $wo = $this->completedJob();
        $this->assertSame(0, $wo->attachments()->where('kind', CompletionReport::KIND)->count());
        $wo->update(['status' => 'complete']);
        $report = $wo->attachments()->where('kind', CompletionReport::KIND)->first();
        $this->assertNotNull($report);
        $this->assertSame('P-1001-completion-report.pdf', $report->original_name);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->path));
    }

    public function test_client_and_compass_can_download_but_others_cannot(): void
    {
        Storage::fake('local');
        $partner = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Eastway']);
        $otherPartner = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Someone else']);
        $otherClient = Organisation::create(['account_id' => $this->account->id, 'type' => 'client', 'name' => 'Other client']);
        $wo = $this->completedJob($partner);
        $wo->update(['status' => 'complete']);
        $url = "/jobs/{$wo->id}/completion-report.pdf";

        $this->signIn($this->makeUser($this->compass, 'helpdesk'))->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->signIn($this->makeUser($this->wates, 'viewer'))->get($url)->assertOk();
        $this->signIn($this->makeUser($partner, 'partner_manager'))->get($url)->assertOk();
        $this->signIn($this->makeUser($otherPartner, 'partner_manager'))->get($url)->assertForbidden();
        $this->signIn($this->makeUser($otherClient, 'client_admin'))->get($url)->assertForbidden();
    }

    public function test_no_report_before_completion(): void
    {
        Storage::fake('local');
        $wo = $this->completedJob();
        $this->signIn($this->makeUser($this->compass, 'admin'))->get("/jobs/{$wo->id}/completion-report.pdf")->assertNotFound();
    }
}
