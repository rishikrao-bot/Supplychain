<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity;
use App\Services\Microsoft\MicrosoftApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Sign in with Microsoft" for Compass staff (OpenID Connect, authorisation code with PKCE).
 *
 * Only accounts in Compass's own Microsoft 365 tenant can use it, and only if Compass MEP has
 * already given them portal access (matched on email the first time, then on their Microsoft ID).
 * Microsoft's own multi-factor sign-in replaces the portal's authenticator-app step for these logins,
 * so the tenant must require MFA (Security Defaults or Conditional Access).
 * Clients and partners keep email, password and authenticator app.
 */
class MicrosoftLoginController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('services.microsoft.sign_in') && MicrosoftApp::isConfigured();
    }

    private function callbackUrl(): string
    {
        return route('login.microsoft.callback');
    }

    public function redirect(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('ms_login', compact('state', 'nonce', 'verifier'));

        $query = http_build_query([
            'client_id' => MicrosoftApp::config('client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->callbackUrl(),
            'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);

        return redirect()->away(MicrosoftApp::authority().'/oauth2/v2.0/authorize?'.$query);
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $saved = $request->session()->pull('ms_login');
        $fail = fn (string $msg) => redirect()->route('login')->withErrors(['email' => $msg]);

        if ($request->filled('error')) {
            return $fail('Microsoft sign-in was cancelled or refused: '.Str::limit((string) $request->input('error_description', $request->input('error')), 200));
        }
        if (! $saved || ! $request->filled('code') || ! hash_equals($saved['state'], (string) $request->input('state'))) {
            return $fail('Microsoft sign-in expired. Please try again.');
        }

        // The ID token comes straight from Microsoft's token endpoint over HTTPS, which (per OpenID
        // Connect Core 3.1.3.7) stands in for checking its signature. Its claims are still checked below.
        $res = Http::asForm()->timeout(20)->post(MicrosoftApp::authority().'/oauth2/v2.0/token', [
            'grant_type' => 'authorization_code',
            'client_id' => MicrosoftApp::config('client_id'),
            'client_secret' => MicrosoftApp::config('client_secret'),
            'code' => $request->input('code'),
            'redirect_uri' => $this->callbackUrl(),
            'code_verifier' => $saved['verifier'],
            'scope' => 'openid profile email',
        ]);
        $claims = $res->successful() ? self::claims((string) $res->json('id_token')) : null;
        if (! $claims) {
            Log::warning('Microsoft sign-in token exchange failed: '.MicrosoftApp::error($res->json()));

            return $fail('Microsoft sign-in did not complete. Please try again, or use your email and password.');
        }

        $tenant = (string) MicrosoftApp::config('tenant_id');
        $valid = ($claims['aud'] ?? null) === MicrosoftApp::config('client_id')
            && strcasecmp((string) ($claims['tid'] ?? ''), $tenant) === 0
            && ($claims['iss'] ?? null) === "https://login.microsoftonline.com/{$claims['tid']}/v2.0"
            && hash_equals($saved['nonce'], (string) ($claims['nonce'] ?? ''))
            && ($claims['exp'] ?? 0) > time() - 60
            && filled($claims['oid'] ?? null);
        if (! $valid) {
            return $fail('That Microsoft account is not from Compass MEP. Use your email and password instead.');
        }

        $email = Str::lower((string) ($claims['email'] ?? $claims['preferred_username'] ?? ''));
        $user = User::withoutGlobalScopes()->where('microsoft_oid', $claims['oid'])->first()
            ?? ($email !== '' ? User::withoutGlobalScopes()->where('email', $email)->whereNull('microsoft_oid')->first() : null);

        if (! $user || ! $user->isCompass()) {
            return $fail("{$email} does not have portal access yet. Ask a Compass MEP admin to add you.");
        }
        if ($user->status === User::DISABLED) {
            return $fail('Your access has been removed. Contact Compass MEP if you think this is wrong.');
        }

        // An invited Compass user who signs in with Microsoft has proved their email: their invite is done.
        $user->forceFill([
            'microsoft_oid' => $claims['oid'],
            'status' => User::ACTIVE,
            'invite_token_hash' => null,
            'last_login_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);
        $request->session()->put('login_method', 'microsoft');
        Activity::log('auth.login', $user->name.' logged in with Microsoft.', $user);

        return redirect()->intended(route('home'));
    }

    /** The claims (payload) of a JWT, or null if it isn't one. */
    public static function claims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $json = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($json) ? $json : null;
    }
}
