<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** Someone follows the link in their invite email and sets their password. */
class InviteController extends Controller
{
    public const VALID_DAYS = 7;

    private function findInvite(string $token): ?User
    {
        $user = User::withoutGlobalScopes()->where('invite_token_hash', hash('sha256', $token))->where('status', User::INVITED)->first();

        return $user && $user->invited_at && $user->invited_at->gt(now()->subDays(self::VALID_DAYS)) ? $user : null;
    }

    public function show(string $token)
    {
        $user = $this->findInvite($token);
        if (! $user) {
            return view('auth.invite-invalid');
        }

        return view('auth.invite', ['user' => $user, 'token' => $token]);
    }

    public function accept(Request $request, string $token)
    {
        $user = $this->findInvite($token);
        if (! $user) {
            return view('auth.invite-invalid');
        }
        $request->validate(['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        $user->forceFill([
            'password' => $request->input('password'),
            'status' => User::ACTIVE,
            'invite_token_hash' => null,
            'email_verified_at' => now(),
        ])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', false);
        Activity::log('user.activated', $user->name.' accepted their invite.', $user);

        return redirect()->route('two-factor.setup');
    }
}
