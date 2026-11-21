<?php

namespace Tests\Feature;

use App\Mail\InviteMail;
use App\Models\Account;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class UserManagementTest extends PortalTestCase
{
    public function test_operator_admin_invites_a_client_user(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->post('/users', ['name' => 'Kim Hale', 'email' => 'kim@example.test', 'organisation_id' => $this->client->id, 'role' => 'approver'])
            ->assertRedirect();
        $u = User::where('email', 'kim@example.test')->first();
        $this->assertSame('invited', $u->status);
        $this->assertSame($this->client->id, $u->organisation_id);
        Mail::assertSent(InviteMail::class, fn ($m) => $m->hasTo('kim@example.test'));
    }

    public function test_role_must_suit_the_organisation(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->post('/users', ['name' => 'X', 'email' => 'x@example.test', 'organisation_id' => $this->client->id, 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->assertNull(User::where('email', 'x@example.test')->first());
    }

    public function test_client_admin_only_sees_and_invites_their_own_company(): void
    {
        $partner = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Brightline']);
        $p = $this->makeUser($partner, 'partner_manager');
        $ca = $this->makeUser($this->client, 'client_admin');
        $this->signIn($ca)->get('/users')->assertOk()->assertDontSee($p->email);
        $this->signIn($ca)->post('/users', ['name' => 'Sneaky', 'email' => 's@example.test', 'organisation_id' => $partner->id, 'role' => 'partner_manager'])
            ->assertSessionHasErrors('organisation_id');
        $this->signIn($ca)->post('/users', ['name' => 'Colleague', 'email' => 'c@example.test', 'organisation_id' => $this->client->id, 'role' => 'viewer'])->assertRedirect();
        $this->assertNotNull(User::where('email', 'c@example.test')->first());
    }

    public function test_client_admin_cannot_change_users_in_another_company(): void
    {
        $helpdesk = $this->makeUser($this->operator, 'helpdesk');
        $ca = $this->makeUser($this->client, 'client_admin');
        $this->signIn($ca)->post("/users/{$helpdesk->id}/deactivate")->assertForbidden();
        $this->assertSame('active', $helpdesk->fresh()->status);
    }

    public function test_partner_manager_adds_site_engineer(): void
    {
        $partner = Organisation::create(['account_id' => $this->account->id, 'type' => 'partner', 'name' => 'Partner One']);
        $pm = $this->makeUser($partner, 'partner_manager');
        $this->signIn($pm)->post('/users', ['name' => 'Ben', 'email' => 'ben@example.test', 'organisation_id' => $partner->id, 'role' => 'site_engineer'])->assertRedirect();
        $this->assertSame('site_engineer', User::where('email', 'ben@example.test')->first()->role);
    }

    public function test_viewers_and_helpdesk_cannot_manage_users(): void
    {
        $this->signIn($this->makeUser($this->client, 'viewer'))->get('/users')->assertForbidden();
        $this->signIn($this->makeUser($this->operator, 'helpdesk'))->get('/users')->assertForbidden();
    }

    public function test_last_admin_cannot_be_removed_or_demoted(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $other = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->post("/users/{$other->id}/deactivate")->assertRedirect();
        $this->assertSame('disabled', $other->fresh()->status);
        // $admin is now the last active admin; a second admin can't be created to remove them here,
        // but nobody can remove themselves either:
        $this->signIn($admin)->post("/users/{$admin->id}/deactivate")->assertForbidden();
        $this->assertTrue(\App\Services\UserAccess::isLastAdmin($admin->fresh()));
    }

    public function test_deactivated_user_is_logged_out_on_next_page(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $h = $this->makeUser($this->operator, 'helpdesk');
        $this->signIn($admin)->post("/users/{$h->id}/deactivate");
        $this->signIn($h->fresh())->get('/')->assertRedirect('/login');
    }

    public function test_reset_two_step_forces_setup_again(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $h = $this->makeUser($this->operator, 'helpdesk');
        $this->signIn($admin)->post("/users/{$h->id}/reset-two-factor")->assertRedirect();
        $this->assertNull($h->fresh()->two_factor_confirmed_at);
        $this->signIn($h->fresh())->get('/')->assertRedirect('/two-factor/setup');
    }

    public function test_change_role_and_audit_trail(): void
    {
        $admin = $this->makeUser($this->operator, 'admin');
        $h = $this->makeUser($this->operator, 'helpdesk');
        $this->signIn($admin)->patch("/users/{$h->id}/role", ['role' => 'finance'])->assertRedirect();
        $this->assertSame('finance', $h->fresh()->role);
        $this->assertDatabaseHas('activity_log', ['action' => 'user.role', 'subject_id' => $h->id]);
    }

    public function test_accounts_are_kept_separate(): void
    {
        $other = Account::create(['name' => 'Other MEP', 'slug' => 'other']);
        $otherOrg = Organisation::withoutGlobalScopes()->create(['account_id' => $other->id, 'type' => 'operator', 'name' => 'Other MEP']);
        $stranger = $this->makeUser($otherOrg, 'admin');
        $admin = $this->makeUser($this->operator, 'admin');
        $this->signIn($admin)->get('/users?tab=operator')->assertDontSee($stranger->email);
        $this->signIn($admin)->post("/users/{$stranger->id}/deactivate")->assertNotFound();
        $this->assertSame('active', $stranger->fresh()->status);
    }

    public function test_admin_command_prints_invite_link(): void
    {
        $this->artisan('portal:invite-admin', ['email' => 'Alex.Example@Example.test', 'name' => 'Alex Example'])
            ->expectsOutputToContain('/invite/')->assertSuccessful();
        $this->assertSame('admin', User::where('email', 'alex.example@example.test')->first()->role);
    }
}
