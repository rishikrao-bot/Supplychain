<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\CompletionReport;
use App\Services\Microsoft\Dataverse;
use App\Services\Microsoft\SharePoint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MicrosoftTest extends PortalTestCase
{
    private const TENANT = '11111111-2222-3333-4444-555555555555';
    private const CLIENT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.microsoft' => [
            'tenant_id' => self::TENANT, 'client_id' => self::CLIENT, 'client_secret' => 'test-secret', 'sign_in' => true,
            'sharepoint_site' => 'compassmep.sharepoint.com:/sites/Operations', 'sharepoint_library' => 'Documents',
            'sharepoint_folder' => 'Portal/Completion reports', 'dataverse_url' => 'https://compassmep.crm11.dynamics.com',
        ]]);
    }

    private function idToken(array $claims): string
    {
        $b64 = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');

        return $b64(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$b64($claims + [
            'aud' => self::CLIENT, 'tid' => self::TENANT, 'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'exp' => time() + 3600, 'oid' => 'oid-123',
        ]).'.sig';
    }

    /** Go to Microsoft, come back with a code, and Microsoft's token endpoint returns these claims. */
    private function microsoftLogin(array $claims)
    {
        $this->get('/login/microsoft')->assertRedirectContains('login.microsoftonline.com/'.self::TENANT);
        $saved = session('ms_login');
        Http::fake(['login.microsoftonline.com/*' => Http::response(['id_token' => $this->idToken($claims + ['nonce' => $saved['nonce']])])]);

        return $this->get('/login/microsoft/callback?code=abc&state='.$saved['state']);
    }

    public function test_login_page_shows_microsoft_button_only_when_enabled(): void
    {
        $this->get('/login')->assertSee('sign in with Microsoft');
        config(['services.microsoft.sign_in' => false]);
        $this->get('/login')->assertDontSee('sign in with Microsoft');
        $this->get('/login/microsoft')->assertNotFound();
    }

    public function test_compass_staff_sign_in_with_microsoft(): void
    {
        $u = $this->makeUser($this->compass, 'helpdesk', ['email' => 'jo@compass-mep.com']);
        $this->microsoftLogin(['preferred_username' => 'Jo@Compass-MEP.com'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Welcome');
        $this->assertSame('oid-123', $u->fresh()->microsoft_oid);
    }

    public function test_invited_compass_user_without_authenticator_can_sign_in_with_microsoft(): void
    {
        $u = $this->makeUser($this->compass, 'admin', ['email' => 'new@compass-mep.com', 'status' => User::INVITED]);
        $u->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save();
        $this->microsoftLogin(['email' => 'new@compass-mep.com'])->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->assertSame(User::ACTIVE, $u->fresh()->status);
    }

    public function test_clients_cannot_use_microsoft_sign_in(): void
    {
        $this->makeUser($this->wates, 'client_admin', ['email' => 'pm@wates.co.uk']);
        $this->microsoftLogin(['preferred_username' => 'pm@wates.co.uk'])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_other_tenants_wrong_nonce_and_unknown_people_are_refused(): void
    {
        $this->makeUser($this->compass, 'admin', ['email' => 'jo@compass-mep.com']);
        $this->microsoftLogin(['preferred_username' => 'jo@compass-mep.com', 'tid' => 'someone-else'])->assertSessionHasErrors('email');
        $this->microsoftLogin(['preferred_username' => 'stranger@compass-mep.com'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get('/login/microsoft');
        $state = session('ms_login')['state'];
        Http::fake(['*' => Http::response(['id_token' => $this->idToken(['preferred_username' => 'jo@compass-mep.com', 'nonce' => 'wrong'])])]);
        $this->get('/login/microsoft/callback?code=abc&state='.$state)->assertSessionHasErrors('email');
        $this->get('/login/microsoft/callback?code=abc&state=forged')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_users_cannot_sign_in_with_microsoft(): void
    {
        $this->makeUser($this->compass, 'admin', ['email' => 'gone@compass-mep.com', 'status' => User::DISABLED]);
        $this->microsoftLogin(['preferred_username' => 'gone@compass-mep.com'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function fakeGraph(array &$uploads): void
    {
        Http::fake(function (HttpRequest $r) use (&$uploads) {
            $url = $r->url();
            if (str_contains($url, '/oauth2/v2.0/token')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600]);
            }
            if (str_contains($url, '/sites/compassmep.sharepoint.com:/sites/Operations')) {
                return Http::response(['id' => 'site-1', 'webUrl' => 'https://compassmep.sharepoint.com/sites/Operations']);
            }
            if (str_contains($url, '/sites/site-1/drives')) {
                return Http::response(['value' => [['id' => 'drive-1', 'name' => 'Documents', 'webUrl' => 'https://compassmep.sharepoint.com/sites/Operations/Shared Documents']]]);
            }
            if ($r->method() === 'PUT' && str_contains($url, '/drives/drive-1/root:/')) {
                $uploads[] = $url;

                return Http::response(['id' => 'item-1', 'name' => 'P-1001-completion-report.pdf', 'webUrl' => 'https://compassmep.sharepoint.com/x.pdf'], 201);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$url]], 500);
        });
    }

    private function job(): WorkOrder
    {
        $contract = Contract::withoutGlobalScopes()->where('name', 'Castle Point')->first();
        $wo = WorkOrder::withoutGlobalScopes()->create([
            'account_id' => $this->account->id, 'number' => 1001, 'client_id' => $this->wates->id, 'contract_id' => $contract->id,
            'source' => 'phone', 'priority' => 'routine', 'description' => 'Dripping tap', 'status' => 'review', 'approved_at' => now(),
        ]);
        $wo->trades()->create(['trade' => 'building', 'est_hours' => 1, 'is_primary' => true]);

        return $wo;
    }

    public function test_completed_job_report_is_copied_to_sharepoint(): void
    {
        Storage::fake('local');
        $uploads = [];
        $this->fakeGraph($uploads);
        $wo = $this->job();
        $wo->update(['status' => 'complete']);

        $this->assertCount(1, $uploads);
        $this->assertStringContainsString('/root:/Portal/Completion%20reports/Wates/Castle%20Point/P-1001-completion-report.pdf:/content', $uploads[0]);
        $report = $wo->attachments()->where('kind', CompletionReport::KIND)->first();
        $this->assertSame('https://compassmep.sharepoint.com/x.pdf', $report->meta['sharepoint']['webUrl']);
    }

    public function test_sharepoint_failure_does_not_block_completion_and_sync_retries(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['error' => ['message' => 'Access denied']], 403)]);
        $wo = $this->job();
        $wo->update(['status' => 'complete']);
        $this->assertSame('complete', $wo->fresh()->status);
        $report = Attachment::where('kind', CompletionReport::KIND)->first();
        $this->assertStringContainsString('Access denied', $report->meta['sharepoint_error']['message']);

        // Fixed: the sync command copies it.
        cache()->flush();
        Http::swap(new \Illuminate\Http\Client\Factory);
        $uploads = [];
        $this->fakeGraph($uploads);
        $this->artisan('portal:sharepoint-sync')->assertSuccessful();
        $this->assertCount(1, $uploads);
        $this->assertArrayNotHasKey('sharepoint_error', $report->fresh()->meta);
    }

    public function test_nothing_goes_to_sharepoint_when_not_set_up(): void
    {
        Storage::fake('local');
        config(['services.microsoft.sharepoint_site' => null]);
        Http::fake();
        $this->job()->update(['status' => 'complete']);
        Http::assertNothingSent();
        $this->assertFalse(SharePoint::isConfigured());
    }

    public function test_dataverse_reads_and_creates_rows(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'compassmep.crm11.dynamics.com/api/data/v9.2/WhoAmI' => Http::response(['UserId' => 'u-1']),
            'compassmep.crm11.dynamics.com/api/data/v9.2/accounts?*' => Http::response(['value' => [['name' => 'Wates']]]),
            'compassmep.crm11.dynamics.com/api/data/v9.2/accounts' => Http::response(null, 204, ['OData-EntityId' => 'https://compassmep.crm11.dynamics.com/api/data/v9.2/accounts(01234567-89ab-cdef-0123-456789abcdef)']),
        ]);
        $this->assertSame('u-1', Dataverse::whoAmI()['UserId']);
        $this->assertSame('Wates', Dataverse::rows('accounts', ['$select' => 'name'])[0]['name']);
        $this->assertSame('01234567-89ab-cdef-0123-456789abcdef', Dataverse::create('accounts', ['name' => 'New client']));
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/token')
            && $r['scope'] === 'https://compassmep.crm11.dynamics.com/.default');
    }

    public function test_only_compass_admins_see_the_microsoft_page(): void
    {
        $this->signIn($this->makeUser($this->compass, 'admin'))->get('/microsoft-365')->assertOk()->assertSee('Test connections');
        $this->signIn($this->makeUser($this->compass, 'helpdesk'))->get('/microsoft-365')->assertForbidden();
        $this->signIn($this->makeUser($this->wates, 'client_admin'))->get('/microsoft-365')->assertForbidden();
    }

    public function test_connection_test_reports_problems_in_plain_words(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_client', 'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: x"], 401)]);
        $this->signIn($this->makeUser($this->compass, 'admin'))
            ->from('/microsoft-365')->post('/microsoft-365/test')->assertRedirect('/microsoft-365');
        $this->get('/microsoft-365')->assertSee('Invalid client secret provided.')->assertDontSee('Trace ID');
    }
}
