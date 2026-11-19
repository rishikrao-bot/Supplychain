<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\PortalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

abstract class PortalTestCase extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected Organisation $compass;
    protected Organisation $wates;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(PortalSeeder::class);
        $this->account = Account::where('slug', 'compass-mep')->first();
        $this->compass = Organisation::withoutGlobalScopes()->where('type', 'compass')->first();
        $this->wates = Organisation::withoutGlobalScopes()->where('name', 'Wates')->first();
    }

    protected function makeUser(Organisation $org, string $role, array $attrs = []): User
    {
        $u = User::withoutGlobalScopes()->create(array_merge([
            'account_id' => $org->account_id, 'organisation_id' => $org->id, 'name' => ucfirst($role).' '.uniqid(),
            'email' => uniqid($role).'@example.test', 'role' => $role, 'status' => User::ACTIVE, 'password' => 'Secret-Passw0rd!',
        ], $attrs));
        $u->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }

    /** Log in fully: password and two-step already passed. */
    protected function signIn(User $u): static
    {
        return $this->actingAs($u)->withSession(['two_factor_passed' => true]);
    }
}
