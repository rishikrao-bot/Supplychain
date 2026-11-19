<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every portal page: the person must be logged in, active (not deactivated)
 * and must have passed two-step login in this session.
 */
class SecurePortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your access has been removed. Contact Compass MEP if you think this is wrong.']);
        }
        if (! $user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        if (! $request->session()->get('two_factor_passed')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
