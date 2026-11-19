<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Competency;
use App\Models\Contract;
use App\Models\Organisation;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SetupTest extends PortalTestCase
{
    public function test_helpdesk_adds_and_edits_a_site(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $contract = Contract::withoutGlobalScopes()->where('name', 'Castle Point')->first();
        $this->signIn($h)->post('/sites', ['contract_id' => $contract->id, 'name' => 'Runnymede Pool', 'postcode' => 'SS7', 'zone' => 'A', 'access_notes' => 'Key safe'])->assertRedirect('/sites');
        $site = Site::where('name', 'Runnymede Pool')->first();
        $this->assertNotNull($site);
        $this->signIn($h)->put("/sites/{$site->id}", ['contract_id' => $contract->id, 'name' => 'Runnymede Pool', 'zone' => 'B'])->assertRedirect('/sites');
        $this->assertSame('B', $site->fresh()->zone);
        $this->signIn($h)->get('/sites')->assertOk()->assertSee('Runnymede Pool');
    }

    public function test_clients_partners_and_finance_cannot_open_setup(): void
    {
        $partner = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'P']);
        foreach ([$this->makeUser($this->wates, 'client_admin'), $this->makeUser($partner, 'partner_manager'), $this->makeUser($this->compass, 'finance')] as $u) {
            foreach (['/sites', '/contractors', '/competencies', '/rates-and-rules'] as $url) {
                $this->signIn($u)->get($url)->assertForbidden();
            }
        }
    }

    public function test_add_contractor_with_competencies_and_rates(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $comps = Competency::withoutGlobalScopes()->whereIn('name', ['Fire alarm', 'Access control'])->pluck('id')->all();
        $this->signIn($h)->post('/contractors', [
            'name' => 'Brightline Fire & Security Ltd', 'partner_type' => 'subcontractor', 'contact_name' => 'Dave Brooks', 'contact_email' => 'dave@example.test',
            'zones' => ['A', 'B'], 'job_limit' => 2500, 'vat_registered' => 1, 'rate_normal' => 52, 'rate_callout' => 30, 'competencies' => $comps,
        ])->assertRedirect('/contractors');
        $org = Organisation::where('name', 'Brightline Fire & Security Ltd')->first();
        $this->assertSame('partner', $org->type);
        $this->assertSame(['A', 'B'], $org->zones);
        $this->assertEquals(52, $org->rates['normal']);
        $this->assertCount(2, $org->competencies);
        $this->signIn($h)->get('/contractors')->assertOk()->assertSee('Fire alarm')->assertSee('✗ BAFE', false);
    }

    public function test_zones_are_required(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $this->signIn($h)->post('/contractors', ['name' => 'X', 'partner_type' => 'specialist'])->assertSessionHasErrors('zones');
    }

    public function test_document_upload_and_certificate_check(): void
    {
        Storage::fake('local');
        $h = $this->makeUser($this->compass, 'helpdesk');
        $org = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Brightline', 'zones' => ['A']]);
        $org->competencies()->attach(Competency::withoutGlobalScopes()->where('name', 'Fire alarm')->value('id'));
        $this->signIn($h)->post("/contractors/{$org->id}/documents", ['name' => 'BAFE SP203-1', 'expires_on' => now()->addYear()->toDateString(), 'blocks_offers' => 1, 'file' => UploadedFile::fake()->create('bafe.pdf', 50, 'application/pdf')])->assertRedirect();
        $doc = $org->documents()->first();
        $this->assertTrue($doc->blocks_offers);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->signIn($h)->get('/contractors?all=1')->assertSee('✓ BAFE', false);
        $this->signIn($h)->get("/documents/{$doc->id}/download")->assertOk();
    }

    public function test_only_expiring_documents_are_listed_by_default(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $org = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Eastway', 'zones' => ['A']]);
        $org->documents()->create(['account_id' => $this->account->id, 'name' => 'Employer liability', 'expires_on' => now()->addYear(), 'status' => 'approved']);
        $org->documents()->create(['account_id' => $this->account->id, 'name' => 'Public liability', 'expires_on' => now()->addDays(10), 'status' => 'approved']);
        $this->signIn($h)->get('/contractors')->assertSee('Public liability')->assertDontSee('Employer liability');
        $this->signIn($h)->get('/contractors?all=1')->assertSee('Employer liability');
    }

    public function test_suspend_and_lift(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $org = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Northgate', 'zones' => ['A']]);
        $this->signIn($h)->post("/contractors/{$org->id}/suspend");
        $this->assertNotNull($org->fresh()->suspended_at);
        $this->signIn($h)->post("/contractors/{$org->id}/suspend");
        $this->assertNull($org->fresh()->suspended_at);
    }

    public function test_cannot_edit_a_client_as_a_contractor(): void
    {
        $h = $this->makeUser($this->compass, 'helpdesk');
        $this->signIn($h)->get("/contractors/{$this->wates->id}/edit")->assertNotFound();
    }

    public function test_competency_added_by_admin_only(): void
    {
        $this->signIn($this->makeUser($this->compass, 'helpdesk'))->post('/competencies', ['name' => 'Solar PV', 'new_group' => 'Renewables'])->assertForbidden();
        $this->signIn($this->makeUser($this->compass, 'admin'))->post('/competencies', ['name' => 'Solar PV', 'new_group' => 'Renewables', 'certificate_required' => 'MCS'])->assertRedirect();
        $this->assertSame('MCS', Competency::where('name', 'Solar PV')->value('certificate_required'));
        $this->signIn($this->makeUser($this->compass, 'admin'))->post('/competencies', ['name' => 'Solar PV', 'new_group' => 'X'])->assertSessionHasErrors('name');
    }

    public function test_rules_page_and_admin_edit(): void
    {
        $s = Setting::withoutGlobalScopes()->where('key', 'approval_threshold')->first();
        $this->signIn($this->makeUser($this->compass, 'helpdesk'))->get('/rates-and-rules')->assertOk()->assertSee('TBC')->assertSee('£65.00/h');
        $this->signIn($this->makeUser($this->compass, 'helpdesk'))->patch("/rates-and-rules/{$s->id}", ['value' => '750', 'status' => 'agreed'])->assertForbidden();
        $this->signIn($this->makeUser($this->compass, 'admin'))->patch("/rates-and-rules/{$s->id}", ['value' => '750', 'status' => 'agreed'])->assertRedirect();
        $this->assertSame(750, $s->fresh()->value);
        $this->assertSame('agreed', $s->fresh()->status);
        $b = Setting::withoutGlobalScopes()->where('key', 'return_visit_callout')->first();
        $this->signIn($this->makeUser($this->compass, 'admin'))->patch("/rates-and-rules/{$b->id}", ['value' => 'yes', 'status' => 'agreed']);
        $this->assertTrue($b->fresh()->value);
    }

    public function test_other_accounts_sites_are_invisible(): void
    {
        $other = Account::create(['name' => 'Other', 'slug' => 'other']);
        $client = Organisation::withoutGlobalScopes()->create(['account_id' => $other->id, 'type' => 'client', 'name' => 'OtherClient']);
        $c = Contract::withoutGlobalScopes()->create(['account_id' => $other->id, 'client_id' => $client->id, 'name' => 'Secret contract']);
        $site = Site::withoutGlobalScopes()->create(['account_id' => $other->id, 'contract_id' => $c->id, 'name' => 'Secret site', 'zone' => 'A']);
        $h = $this->makeUser($this->compass, 'helpdesk');
        $this->signIn($h)->get('/sites')->assertDontSee('Secret site');
        $this->signIn($h)->get("/sites/{$site->id}/edit")->assertNotFound();
        $this->signIn($h)->post('/sites', ['contract_id' => $c->id, 'name' => 'Hijack', 'zone' => 'A'])->assertSessionHasErrors('contract_id');
    }
}
