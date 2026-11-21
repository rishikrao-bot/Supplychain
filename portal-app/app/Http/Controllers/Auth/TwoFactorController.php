<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Activity;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Two-step login with an authenticator app (Microsoft Authenticator, Google Authenticator, etc.). */
class TwoFactorController extends Controller
{
    public function setup(Request $request, Google2FA $g2fa)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('two-factor.challenge');
        }
        $secret = $request->session()->get('two_factor_pending') ?: $g2fa->generateSecretKey(32);
        $request->session()->put('two_factor_pending', $secret);
        $url = $g2fa->getQRCodeUrl(config('portal.name'), $user->email, $secret);
        $qr = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd())))->writeString($url);

        return view('auth.two-factor-setup', ['qr' => $qr, 'secret' => trim(chunk_split($secret, 4, ' '))]);
    }

    public function confirm(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $secret = $request->session()->get('two_factor_pending');
        $code = preg_replace('/\D/', '', $request->input('code'));
        if (! $secret || ! $g2fa->verifyKey($secret, $code, 1)) {
            return back()->withErrors(['code' => 'That code did not match. Check the time on your phone is correct and try the newest code.']);
        }
        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('two_factor_pending');
        $request->session()->put('two_factor_passed', true);
        $user->forceFill(['last_login_at' => now()])->save();
        Activity::log('auth.2fa_setup', $user->name.' set up two-step login.', $user);

        return view('auth.recovery-codes', ['codes' => $codes]);
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        if ($request->session()->get('two_factor_passed')) {
            return redirect()->route('home');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, Google2FA $g2fa)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $key = '2fa|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $input = trim($request->input('code'));
        $ok = false;
        if (preg_match('/^\d[\d\s]{5,}$/', $input)) {
            $ok = $g2fa->verifyKey($user->two_factor_secret, preg_replace('/\D/', '', $input), 1);
        } else {
            // Recovery code: each one works once.
            $hash = hash('sha256', Str::upper($input));
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array($hash, $codes, true)) {
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();
                $ok = true;
                Activity::log('auth.recovery_code', $user->name.' used a recovery code.', $user);
            }
        }
        if (! $ok) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'That code did not match. Use the newest code in your authenticator app, or a recovery code.']);
        }
        RateLimiter::clear($key);
        $request->session()->put('two_factor_passed', true);
        $user->forceFill(['last_login_at' => now()])->save();
        Activity::log('auth.login', $user->name.' logged in.', $user);

        return redirect()->intended(route('home'));
    }
}
