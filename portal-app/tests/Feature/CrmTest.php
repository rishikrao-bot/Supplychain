<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Organisation;
use App\Models\Task;
use App\Models\User;
use App\Services\Microsoft\ToDo;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

class CrmTest extends PortalTestCase
{
    private const TENANT = '11111111-2222-3333-4444-555555555555';
    private const CLIENT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.microsoft' => [
            'tenant_id' => self::TENANT, 'client_id' => self::CLIENT, 'client_secret' => 'test-secret', 'sign_in' => true,
            'sharepoint_site' => null, 'sharepoint_library' => 'Documents', 'sharepoint_folder' => 'Portal',
            'dataverse_url' => 'https://example.crm11.dynamics.com',
        ]]);
    }

    private function staff(string $role = 'helpdesk', array $attrs = []): User
    {
        return $this->makeUser($this->compass, $role, $attrs);
    }

    private function connected(User $u): User
    {
        $u->forceFill(['microsoft_refresh_token' => 'refresh-'.$u->id, 'microsoft_connected_at' => now()])->save();

        return $u;
    }

    // ---- Access -------------------------------------------------------------------------------

    public function test_only_compass_office_staff_use_the_crm(): void
    {
        foreach (['admin', 'helpdesk', 'finance'] as $role) {
            $this->signIn($this->staff($role))->get('/crm/companies')->assertOk();
        }
        $this->signIn($this->staff('engineer'))->get('/crm/companies')->assertForbidden();
        $this->signIn($this->makeUser($this->wates, 'client_admin'))->get('/crm/companies')->assertForbidden();
        $this->signIn($this->makeUser($this->wates, 'client_admin'))->get('/tasks')->assertForbidden();
    }

    // ---- Companies, contacts, pipeline, history -------------------------------------------------

    public function test_add_a_prospect_with_a_contact_and_see_it_together(): void
    {
        $me = $this->staff();
        $this->signIn($me)->post('/crm/companies', ['name' => 'Kier', 'type' => 'prospect', 'email' => 'info@kier.example', 'owner_id' => $me->id])
            ->assertRedirect();
        $kier = Organisation::where('name', 'Kier')->firstOrFail();
        $this->assertSame('prospect', $kier->type);

        $this->post('/crm/contacts', ['organisation_id' => $kier->id, 'first_name' => 'Sam', 'last_name' => 'Patel', 'email' => 'sam@kier.example'])->assertRedirect();
        $this->get("/crm/companies/{$kier->id}")->assertOk()->assertSee('Sam Patel')->assertSee('Prospect');
        $this->get('/crm/contacts?q=patel')->assertOk()->assertSee('Sam Patel')->assertSee('Kier');
    }

    public function test_crm_cannot_create_supply_chain_partners_and_crm_companies_get_no_logins(): void
    {
        $this->signIn($this->staff('admin'))->post('/crm/companies', ['name' => 'Sneaky', 'type' => 'partner'])->assertSessionHasErrors('type');
        $prospect = Organisation::create(['account_id' => $this->account->id, 'type' => 'prospect', 'name' => 'Prospect Ltd']);
        $this->get('/users')->assertDontSee('Prospect Ltd');
        $this->post('/users', ['organisation_id' => $prospect->id, 'name' => 'X', 'email' => 'x@prospect.test', 'role' => 'viewer'])->assertSessionHasErrors('organisation_id');
    }

    public function test_winning_a_prospects_tender_makes_them_a_client(): void
    {
        $me = $this->staff();
        $co = Organisation::create(['account_id' => $this->account->id, 'type' => 'prospect', 'name' => 'Morgan Sindall']);
        $this->signIn($me)->post('/crm/pipeline', ['title' => 'Reactive M&E 2027', 'organisation_id' => $co->id, 'kind' => 'tender', 'stage' => 'submitted', 'value' => 250000, 'probability' => 40])->assertRedirect();
        $opp = Opportunity::firstOrFail();
        $this->get('/crm/pipeline')->assertOk()->assertSee('Reactive M&amp;E 2027', false)->assertSee('£250,000');

        $this->put("/crm/pipeline/{$opp->id}", ['title' => $opp->title, 'organisation_id' => $co->id, 'kind' => 'tender', 'stage' => 'won', 'value' => 250000])->assertRedirect();
        $this->assertSame('won', $opp->fresh()->stage);
        $this->assertSame(100, (int) $opp->fresh()->probability);
        $this->assertNotNull($opp->fresh()->closed_at);
        $this->assertSame('client', $co->fresh()->type);
    }

    public function test_history_notes_show_on_company_and_contact_and_only_author_can_remove(): void
    {
        $me = $this->staff();
        $contact = Contact::create(['account_id' => $this->account->id, 'organisation_id' => $this->wates->id, 'first_name' => 'Jo', 'last_name' => 'Harding']);
        $this->signIn($me)->post('/crm/activities', ['contact_id' => $contact->id, 'type' => 'call', 'subject' => 'Rates review', 'body' => 'Agreed to meet next week.'])->assertRedirect();
        $a = CrmActivity::firstOrFail();
        $this->assertSame($this->wates->id, $a->organisation_id); // filled in from the contact
        $this->get("/crm/companies/{$this->wates->id}")->assertSee('Agreed to meet next week.');
        $this->get("/crm/contacts/{$contact->id}")->assertSee('Rates review');

        $this->signIn($this->staff())->delete("/crm/activities/{$a->id}")->assertForbidden();
        $this->signIn($me)->delete("/crm/activities/{$a->id}")->assertRedirect();
        $this->assertSame(0, CrmActivity::count());
    }

    // ---- Tasks and Microsoft To Do --------------------------------------------------------------

    /** A fake Microsoft: token endpoint, one To Do list and its tasks. */
    private function fakeMicrosoft(array &$todo, array &$calls = []): void
    {
        Http::fake(function (HttpRequest $r) use (&$todo, &$calls) {
            $url = $r->url();
            $calls[] = $r->method().' '.$url;
            if (str_contains($url, '/oauth2/v2.0/token')) {
                return Http::response(['access_token' => 'tok', 'refresh_token' => 'rotated', 'expires_in' => 3600]);
            }
            if (preg_match('#/me/todo/lists$#', $url)) {
                return $r->method() === 'POST' ? Http::response(['id' => 'list-1', 'displayName' => 'Compass MEP Portal'], 201) : Http::response(['value' => []]);
            }
            if (preg_match('#/me/todo/lists/list-1$#', $url)) {
                return Http::response(['id' => 'list-1']);
            }
            if (preg_match('#/me/todo/lists/list-1/tasks(\?.*)?$#', $url)) {
                if ($r->method() === 'POST') {
                    $id = 'todo-'.(count($todo) + 1);
                    $todo[$id] = $r->data() + ['id' => $id, 'lastModifiedDateTime' => now()->toIso8601String()];

                    return Http::response($todo[$id], 201);
                }

                return Http::response(['value' => array_values($todo)]);
            }
            if (preg_match('#/me/todo/lists/list-1/tasks/([\w-]+)$#', $url, $m)) {
                if (! isset($todo[$m[1]])) {
                    return Http::response(['error' => ['message' => 'not found']], 404);
                }
                if ($r->method() === 'DELETE') {
                    unset($todo[$m[1]]);

                    return Http::response(null, 204);
                }
                $todo[$m[1]] = $r->data() + $todo[$m[1]];
                $todo[$m[1]]['lastModifiedDateTime'] = now()->toIso8601String();

                return Http::response($todo[$m[1]]);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$r->method().' '.$url]], 500);
        });
    }

    public function test_tasks_without_microsoft_stay_in_the_portal(): void
    {
        Http::fake();
        $me = $this->staff();
        $this->signIn($me)->post('/tasks', ['title' => 'Chase Wates PO', 'organisation_id' => $this->wates->id, 'due_on' => now()->addDay()->toDateString()])->assertRedirect();
        $t = Task::firstOrFail();
        $this->assertSame($me->id, $t->assigned_to);
        $this->get('/tasks')->assertSee('Chase Wates PO')->assertSee('Connect');
        $this->post("/tasks/{$t->id}/toggle");
        $this->assertTrue($t->fresh()->isDone());
        Http::assertNothingSent();
    }

    public function test_new_task_goes_to_the_assignees_microsoft_to_do(): void
    {
        $todo = [];
        $this->fakeMicrosoft($todo);
        $me = $this->connected($this->staff());
        $this->signIn($me)->post('/tasks', ['title' => 'Send Castle Point valuation', 'due_on' => '2026-12-01', 'organisation_id' => $this->wates->id]);

        $t = Task::firstOrFail();
        $this->assertSame('todo-1', $t->todo_task_id);
        $this->assertSame($me->id, $t->todo_user_id);
        $this->assertFalse($t->todo_pending);
        $this->assertSame('Send Castle Point valuation', $todo['todo-1']['title']);
        $this->assertSame('2026-12-01T00:00:00', $todo['todo-1']['dueDateTime']['dateTime']);
        $this->assertStringContainsString('Wates', $todo['todo-1']['body']['content']);
        $this->assertSame('list-1', $me->fresh()->todo_list_id);
        $this->assertSame('rotated', $me->fresh()->microsoft_refresh_token); // Microsoft's new refresh token is kept

        // Ticking it off in the portal ticks it off in To Do.
        $this->post("/tasks/{$t->id}/toggle");
        $this->assertSame('completed', $todo['todo-1']['status']);
    }

    public function test_reassigning_moves_the_task_between_people(): void
    {
        $todo = [];
        $this->fakeMicrosoft($todo);
        $a = $this->connected($this->staff());
        $b = $this->staff(); // not connected
        $this->signIn($a)->post('/tasks', ['title' => 'Site survey']);
        $t = Task::firstOrFail();
        $this->assertArrayHasKey('todo-1', $todo);

        $this->put("/tasks/{$t->id}", ['title' => 'Site survey', 'assigned_to' => $b->id]);
        $this->assertArrayNotHasKey('todo-1', $todo);
        $this->assertNull($t->fresh()->todo_task_id);
    }

    public function test_changes_in_to_do_come_back_to_the_portal(): void
    {
        $todo = [];
        $this->fakeMicrosoft($todo);
        $me = $this->connected($this->staff());
        $this->signIn($me)->post('/tasks', ['title' => 'Book asbestos survey']);
        $this->post('/tasks', ['title' => 'Will be deleted in To Do']);
        $t = Task::where('title', 'Book asbestos survey')->firstOrFail();

        // In To Do: tick one off and rename it, delete the other, and add a brand new one.
        $this->travel(2)->minutes();
        $todo['todo-1']['status'] = 'completed';
        $todo['todo-1']['title'] = 'Book asbestos R&D survey';
        $todo['todo-1']['lastModifiedDateTime'] = now()->toIso8601String();
        unset($todo['todo-2']);
        $todo['todo-new'] = ['id' => 'todo-new', 'title' => 'Ring Eastway about Monday', 'status' => 'notStarted', 'lastModifiedDateTime' => now()->toIso8601String(), 'dueDateTime' => ['dateTime' => '2026-11-30T00:00:00.0000000', 'timeZone' => 'UTC']];

        $this->artisan('portal:todo-sync')->assertSuccessful();

        $t->refresh();
        $this->assertTrue($t->isDone());
        $this->assertSame('Book asbestos R&D survey', $t->title);
        $this->assertNull(Task::where('title', 'Will be deleted in To Do')->first()->todo_task_id);
        $new = Task::where('title', 'Ring Eastway about Monday')->firstOrFail();
        $this->assertSame($me->id, $new->assigned_to);
        $this->assertSame('2026-11-30', $new->due_on->toDateString());
        $this->assertSame('todo-new', $new->todo_task_id);

        // A second sync with no changes does nothing.
        $this->artisan('portal:todo-sync')->assertSuccessful();
        $this->assertSame(3, Task::count());
    }

    public function test_to_do_failure_is_kept_and_retried(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Service unavailable']], 503)]);
        $me = $this->connected($this->staff());
        $this->signIn($me)->post('/tasks', ['title' => 'Retry me']);
        $t = Task::firstOrFail();
        $this->assertTrue($t->todo_pending);
        $this->assertStringContainsString('Service unavailable', $t->todo_error);
        $this->get('/tasks')->assertSee('Not in To Do yet');

        $todo = [];
        Http::swap(new \Illuminate\Http\Client\Factory);
        cache()->flush();
        $this->fakeMicrosoft($todo);
        $this->artisan('portal:todo-sync')->assertSuccessful();
        $this->assertSame('todo-1', $t->fresh()->todo_task_id);
        $this->assertFalse($t->fresh()->todo_pending);
    }

    public function test_revoked_microsoft_access_disconnects_the_person(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'AADSTS70008: The refresh token has expired.'], 400)]);
        $me = $this->connected($this->staff());
        $this->signIn($me)->post('/tasks', ['title' => 'Anything']);
        $this->assertFalse($me->fresh()->hasMicrosoft());
        $this->signIn($me->fresh())->get('/tasks')->assertSee('Connect Outlook');
    }

    // ---- Outlook -------------------------------------------------------------------------------

    public function test_outlook_emails_with_a_contact_show_and_can_be_saved_to_the_record(): void
    {
        $msg = ['id' => 'AAMk-1', 'subject' => 'Castle Point PO', 'from' => ['emailAddress' => ['name' => 'Jo Harding', 'address' => 'jo@wates.example']],
            'toRecipients' => [['emailAddress' => ['name' => 'Rishi', 'address' => 'r@compass.example']]], 'receivedDateTime' => '2026-11-20T10:15:00Z',
            'bodyPreview' => 'PO attached for the leak repair.', 'webLink' => 'https://outlook.office365.com/owa/?ItemID=AAMk-1'];
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/me/messages/*' => Http::response($msg),
            'graph.microsoft.com/v1.0/me/messages?*' => Http::response(['value' => [$msg]]),
        ]);
        $me = $this->connected($this->staff());
        $contact = Contact::create(['account_id' => $this->account->id, 'organisation_id' => $this->wates->id, 'first_name' => 'Jo', 'email' => 'jo@wates.example']);

        $this->signIn($me)->get("/crm/contacts/{$contact->id}")->assertOk()->assertSee('Castle Point PO')->assertSee('Save to record');
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'participants:jo@wates.example'));

        $this->post('/crm/activities/email', ['contact_id' => $contact->id, 'message_id' => 'AAMk-1'])->assertRedirect();
        $a = CrmActivity::firstOrFail();
        $this->assertSame('email', $a->type);
        $this->assertSame($this->wates->id, $a->organisation_id);
        $this->assertStringContainsString('PO attached', $a->body);
        // Saving twice doesn't duplicate it.
        $this->post('/crm/activities/email', ['contact_id' => $contact->id, 'message_id' => 'AAMk-1']);
        $this->assertSame(1, CrmActivity::count());
    }

    public function test_connect_outlook_and_to_do_while_logged_in(): void
    {
        $me = $this->staff('helpdesk', ['email' => 'jo@compass-mep.com']);
        $this->signIn($me)->get('/login/microsoft')->assertRedirectContains('login_hint=jo%40compass-mep.com');
        $saved = session('ms_login');
        $this->assertTrue($saved['connect']);
        $b64 = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $jwt = fn ($email, $saved) => $b64(['alg' => 'none']).'.'.$b64(['aud' => self::CLIENT, 'tid' => self::TENANT, 'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'exp' => time() + 3600, 'oid' => 'oid-jo', 'nonce' => $saved['nonce'], 'preferred_username' => $email]).'.sig';

        // Signed in to Microsoft as someone else: refused.
        Http::fake(['*' => Http::response(['id_token' => $jwt('boss@compass-mep.com', $saved), 'refresh_token' => 'r1'])]);
        $this->makeUser($this->compass, 'admin', ['email' => 'boss@compass-mep.com']);
        $this->get('/login/microsoft/callback?code=x&state='.$saved['state'])->assertRedirect('/tasks')->assertSessionHasErrors('microsoft');
        $this->assertFalse($me->fresh()->hasMicrosoft());

        // Their own account: connected.
        $this->get('/login/microsoft');
        $saved = session('ms_login');
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response(['id_token' => $jwt('jo@compass-mep.com', $saved), 'refresh_token' => 'r2'])]);
        $this->get('/login/microsoft/callback?code=x&state='.$saved['state'])->assertRedirect('/tasks')->assertSessionHasNoErrors();
        $this->assertTrue($me->fresh()->hasMicrosoft());
        $this->assertSame('r2', $me->fresh()->microsoft_refresh_token);
        $this->assertAuthenticatedAs($me);
    }

    // ---- Import from Dataverse -----------------------------------------------------------------

    public function test_import_from_dataverse_and_rerun_without_duplicates(): void
    {
        $owner = $this->staff('admin', ['email' => 'rishi.rao@compass-mep.com']);
        $base = 'example.crm11.dynamics.com/api/data/v9.2/';
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            $base.'systemusers*' => Http::response(['value' => [['systemuserid' => 'su-1', 'internalemailaddress' => 'Rishi.Rao@compass-mep.com']]]),
            $base.'accounts*' => Http::response(['value' => [
                ['accountid' => 'acc-wates', 'name' => 'wates', 'telephone1' => '01372 861000', 'customertypecode' => 3, '_ownerid_value' => 'su-1'],
                ['accountid' => 'acc-kier', 'name' => 'Kier Group', 'emailaddress1' => 'info@kier.example', 'customertypecode' => null, '_ownerid_value' => 'su-1'],
            ]]),
            $base.'contacts*' => Http::response(['value' => [
                ['contactid' => 'con-1', 'firstname' => 'Sam', 'lastname' => 'Patel', 'emailaddress1' => 'sam@kier.example', '_parentcustomerid_value' => 'acc-kier'],
            ]]),
            $base.'opportunities*' => Http::response(['value' => [
                ['opportunityid' => 'opp-1', 'name' => 'Kier M&E framework', 'estimatedvalue' => 120000, 'statecode' => 0, '_parentaccountid_value' => 'acc-kier', '_parentcontactid_value' => 'con-1', '_ownerid_value' => 'su-1'],
            ]]),
            $base.'tasks*' => Http::response(['value' => [
                ['activityid' => 'task-1', 'subject' => 'Follow up with Sam', 'scheduledend' => '2026-12-02T09:00:00Z', 'statecode' => 0, '_regardingobjectid_value' => 'con-1', '_ownerid_value' => 'su-1'],
            ]]),
            $base.'phonecalls*' => Http::response(['value' => [
                ['activityid' => 'call-1', 'subject' => 'Intro call', 'description' => 'Interested in reactive cover.', 'actualend' => '2026-10-01T10:00:00Z', '_regardingobjectid_value' => 'acc-kier', '_ownerid_value' => 'su-1'],
            ]]),
            $base.'appointments*' => Http::response(['value' => []]),
            $base.'annotations*' => Http::response(['value' => [
                ['annotationid' => 'note-1', 'subject' => null, 'notetext' => 'Prefers email.', 'createdon' => '2026-10-02T10:00:00Z', '_objectid_value' => 'con-1'],
            ]]),
        ]);

        $this->artisan('portal:crm-import --dry-run')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame(0, Contact::withoutGlobalScopes()->count());

        $this->artisan('portal:crm-import')->assertSuccessful();
        $this->artisan('portal:crm-import')->assertSuccessful(); // re-run

        $this->assertSame('acc-wates', $this->wates->fresh()->dataverse_id); // matched the existing Wates by name
        $this->assertSame('client', $this->wates->fresh()->type);
        $kier = Organisation::withoutGlobalScopes()->where('dataverse_id', 'acc-kier')->sole();
        $this->assertSame('prospect', $kier->type);
        $this->assertSame($owner->id, $kier->owner_id);
        $sam = Contact::withoutGlobalScopes()->sole();
        $this->assertSame($kier->id, $sam->organisation_id);
        $opp = Opportunity::withoutGlobalScopes()->sole();
        $this->assertSame([$kier->id, $sam->id, 'qualifying'], [$opp->organisation_id, $opp->contact_id, $opp->stage]);
        $task = Task::withoutGlobalScopes()->sole();
        $this->assertSame([$owner->id, $sam->id, $kier->id, '2026-12-02'], [$task->assigned_to, $task->contact_id, $task->organisation_id, $task->due_on->toDateString()]);
        $this->assertSame(2, CrmActivity::withoutGlobalScopes()->count());
        $this->assertSame('call', CrmActivity::withoutGlobalScopes()->where('dataverse_id', 'call-1')->value('type'));
    }

    public function test_dashboard_shows_my_tasks_and_deadlines(): void
    {
        $me = $this->staff();
        Task::create(['account_id' => $this->account->id, 'title' => 'Ring the Castle Point duty manager', 'assigned_to' => $me->id, 'status' => 'open', 'due_on' => now()->addDay()]);
        Opportunity::create(['account_id' => $this->account->id, 'title' => 'Basildon schools tender', 'stage' => 'pricing', 'kind' => 'tender', 'return_date' => now()->addDays(5)]);
        $this->signIn($me)->get('/')->assertOk()->assertSee('Ring the Castle Point duty manager')->assertSee('Basildon schools tender');
        $this->signIn($this->makeUser($this->wates, 'client_admin'))->get('/')->assertOk()->assertDontSee('Basildon schools tender');
    }
}
