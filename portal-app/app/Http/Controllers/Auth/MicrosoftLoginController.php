<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity;
use App\Services\Microsoft\MicrosoftApp;
use App\Services\Microsoft\MicrosoftUser;
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
 *
 * The same flow connects the person's own Outlook mail (read) and Microsoft To Do for the CRM: on sign-in,
 * or later from the CRM ("Connect Outlook & To Do") if they logged in with email and password.
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
        // Already logged in: this is "Connect Outlook & To Do" for the CRM (works even if Microsoft sign-in is off).
        $connect = Auth::check() && $request->session()->get('two_factor_passed');
        abort_unless($connect ? MicrosoftApp::isConfigured() : self::enabled(), 404);
        if (Auth::check() && ! $connect) {
            return redirect()->route('two-factor.challenge');
        }
        if ($connect && ! $request->user()->canUseCrm()) {
            abort(403);
        }
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('ms_login', compact('state', 'nonce', 'verifier', 'connect'));

        $query = http_build_query([
            'client_id' => MicrosoftApp::config('client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->callbackUrl(),
            'response_mode' => 'query',
            'scope' => MicrosoftUser::SCOPES,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ] + ($connect ? ['login_hint' => $request->user()->email] : ['prompt' => 'select_account']));

        return redirect()->away(MicrosoftApp::authority().'/oauth2/v2.0/authorize?'.$query);
    }

    public function callback(Request $request)
    {
        $saved = $request->session()->pull('ms_login');
        abort_unless(! empty($saved['connect']) ? MicrosoftApp::isConfigured() : self::enabled(), 404);
        $fail = fn (string $msg) => ! empty($saved['connect']) && Auth::check()
            ? redirect()->route('tasks.index')->withErrors(['microsoft' => $msg])
            : redirect()->route('login')->withErrors(['email' => $msg]);

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
            'scope' => MicrosoftUser::SCOPES,
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

        // "Connect Outlook & To Do" from inside the portal: must be the same person.
        if (! empty($saved['connect'])) {
            $me = $request->user();
            $back = redirect()->route('tasks.index');
            if (! $me || ! $user || $me->id !== $user->id) {
                return $back->withErrors(['microsoft' => "You signed in to Microsoft as {$email}, which isn't your portal account. Try again with your own Microsoft 365 account."]);
            }
            if (! $res->json('refresh_token')) {
                return $back->withErrors(['microsoft' => 'Microsoft did not give access to Outlook and To Do. Ask your Microsoft 365 admin to grant consent for the portal (MICROSOFT.md, step 1).']);
            }
            $me->forceFill(['microsoft_oid' => $claims['oid']])->save();
            MicrosoftUser::connect($me, $res->json('refresh_token'));
            Activity::log('microsoft.connected', $me->name.' connected Outlook and To Do.', $me);

            return $back->with('status', 'Outlook and To Do are connected. Your portal tasks will appear in To Do in a list called "Compass MEP Portal".');
        }

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

        if ($res->json('refresh_token') && $user->canUseCrm()) {
            MicrosoftUser::connect($user, $res->json('refresh_token'));
        }

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
