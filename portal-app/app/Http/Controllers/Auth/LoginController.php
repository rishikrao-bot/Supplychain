<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        $user = User::withoutGlobalScopes()->where('email', Str::lower($data['email']))->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'That email and password do not match an account.'])->onlyInput('email');
        }
        if ($user->status !== User::ACTIVE) {
            return back()->withErrors(['email' => $user->status === User::INVITED
                ? 'Finish setting up your account using the link in your invite email first.'
                : 'Your access has been removed. Contact '.config('portal.company').' if you think this is wrong.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', false);

        return $user->hasTwoFactor() ? redirect()->route('two-factor.challenge') : redirect()->route('two-factor.setup');
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            Activity::log('auth.logout', $request->user()->name.' logged out.', $request->user());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have logged out.');
    }
}
