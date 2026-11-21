<?php

namespace Tests\Feature;

use App\Mail\InviteMail;
use App\Models\User;
use App\Services\UserAccess;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;

class AuthTest extends PortalTestCase
{
    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_password_alone_does_not_give_access(): void
    {
        $u = $this->makeUser($this->operator, 'admin');
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret-Passw0rd!'])->assertRedirect('/two-factor');
        $this->get('/')->assertRedirect('/two-factor');
    }

    public function test_correct_code_completes_login(): void
    {
        $u = $this->makeUser($this->operator, 'admin');
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret-Passw0rd!']);
        $code = (new Google2FA)->getCurrentOtp($u->fresh()->two_factor_secret);
        $this->post('/two-factor', ['code' => $code])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Welcome');
    }

    public function test_wrong_code_is_rejected(): void
    {
        $u = $this->makeUser($this->operator, 'admin');
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret-Passw0rd!']);
        $this->post('/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/')->assertRedirect('/two-factor');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $u = $this->makeUser($this->operator, 'admin');
        $this->post('/login', ['email' => $u->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in_and_is_logged_out(): void
    {
        $u = $this->makeUser($this->operator, 'helpdesk', ['status' => User::DISABLED]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Secret-Passw0rd!'])->assertSessionHasErrors('email');
        $this->signIn($u)->get('/')->assertRedirect('/login');
    }

    public function test_invite_sets_password_then_requires_two_step_setup(): void
    {
        $u = User::withoutGlobalScopes()->create(['account_id' => $this->account->id, 'organisation_id' => $this->client->id, 'name' => 'Sam', 'email' => 'paul@example.test', 'role' => 'client_admin', 'status' => User::INVITED]);
        $link = UserAccess::sendInvite($u);
        Mail::assertSent(InviteMail::class);
        $path = parse_url($link, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('Welcome, Sam');
        $this->post($path, ['password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->post($path, ['password' => 'Strong-Passw0rd!', 'password_confirmation' => 'Strong-Passw0rd!'])->assertRedirect('/two-factor/setup');
        $this->assertSame(User::ACTIVE, $u->fresh()->status);
        $this->get('/')->assertRedirect('/two-factor/setup');
        // Link can't be reused
        auth()->logout();
        $this->get($path)->assertSee("isn't valid", false);
    }

    public function test_two_step_setup_confirms_with_code_and_shows_recovery_codes(): void
    {
        $u = $this->makeUser($this->operator, 'admin');
        $u->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->actingAs($u)->get('/two-factor/setup')->assertOk()->assertSee('Set up two-step login');
        $secret = session('two_factor_pending');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/two-factor/setup', ['code' => $code])->assertOk()->assertSee('recovery codes');
        $this->assertNotNull($u->fresh()->two_factor_confirmed_at);
    }

    public function test_expired_invite_is_rejected(): void
    {
        $u = User::withoutGlobalScopes()->create(['account_id' => $this->account->id, 'organisation_id' => $this->client->id, 'name' => 'Old', 'email' => 'old@example.test', 'role' => 'viewer', 'status' => User::INVITED]);
        $link = UserAccess::sendInvite($u);
        $u->forceFill(['invited_at' => now()->subDays(8)])->save();
        $this->get(parse_url($link, PHP_URL_PATH))->assertSee("isn't valid", false);
    }
}
