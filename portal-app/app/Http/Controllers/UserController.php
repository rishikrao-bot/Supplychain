<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\User;
use App\Services\Activity;
use App\Services\UserAccess;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->canManageUsers(), 403);
        $orgs = UserAccess::manageableOrganisations($actor);
        $users = User::with('organisation')->whereIn('organisation_id', $orgs->pluck('id'))->orderBy('name')->get();
        $groups = $actor->isOperatorAdmin()
            ? ['operator' => config('portal.company'), 'client' => 'Clients', 'partner' => 'Supply chain partners']
            : [$actor->orgType() => $actor->organisation->name];
        $tab = array_key_exists($request->query('tab'), $groups) ? $request->query('tab') : array_key_first($groups);

        return view('users.index', compact('actor', 'orgs', 'users', 'groups', 'tab'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor->canManageUsers(), 403);
        $orgIds = UserAccess::manageableOrganisations($actor)->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'organisation_id' => ['required', Rule::in($orgIds)],
            'role' => ['required', 'string'],
        ], ['email.unique' => 'Someone with that email already has access.']);
        $org = Organisation::findOrFail($data['organisation_id']);
        if (! Roles::validFor($data['role'], $org->type)) {
            return back()->withErrors(['role' => 'That role is not available for '.$org->name.'.'])->withInput();
        }
        $user = User::create([
            'account_id' => $actor->account_id, 'organisation_id' => $org->id, 'name' => $data['name'],
            'email' => Str::lower($data['email']), 'phone' => $data['phone'] ?? null, 'role' => $data['role'], 'status' => User::INVITED,
        ]);
        $link = UserAccess::sendInvite($user, $actor);
        Activity::log('user.invited', "{$actor->name} invited {$user->name} ({$user->email}) as ".Roles::label($user->role)." at {$org->name}.", $user);

        return redirect()->route('users.index', ['tab' => $org->type])
            ->with('status', UserAccess::showLink() ? "Invite created for {$user->email}, but email isn't being sent. Copy the link below and send it to them." : "Invite sent to {$user->email}.")
            ->with('invite_link', UserAccess::showLink() ? $link : null);
    }

    public function updateRole(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(UserAccess::canManage($actor, $user) && $actor->id !== $user->id, 403);
        $role = $request->validate(['role' => ['required', 'string']])['role'];
        if (! Roles::validFor($role, $user->orgType())) {
            return back()->withErrors(['role' => 'That role is not available for this organisation.']);
        }
        if ($role !== Roles::ADMIN && UserAccess::isLastAdmin($user)) {
            return back()->withErrors(['role' => 'Keep at least one active admin.']);
        }
        $old = $user->roleLabel();
        $user->update(['role' => $role]);
        Activity::log('user.role', "{$actor->name} changed {$user->name} from {$old} to {$user->roleLabel()}.", $user);

        return back()->with('status', "{$user->name} is now {$user->roleLabel()}.");
    }

    public function deactivate(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(UserAccess::canManage($actor, $user) && $actor->id !== $user->id, 403);
        if (UserAccess::isLastAdmin($user)) {
            return back()->withErrors(['user' => 'Keep at least one active admin.']);
        }
        $user->forceFill(['status' => User::DISABLED, 'disabled_at' => now(), 'invite_token_hash' => null])->save();
        Activity::log('user.deactivated', "{$actor->name} removed access for {$user->name}.", $user);

        return back()->with('status', "{$user->name} can no longer log in.");
    }

    public function reactivate(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(UserAccess::canManage($actor, $user), 403);
        if ($user->password) {
            $user->forceFill(['status' => User::ACTIVE, 'disabled_at' => null])->save();
            $msg = "{$user->name} can log in again.";
        } else {
            UserAccess::sendInvite($user, $actor);
            $msg = "{$user->name} had never set a password, so a new invite was sent.";
        }
        Activity::log('user.reactivated', "{$actor->name} restored access for {$user->name}.", $user);

        return back()->with('status', $msg);
    }

    public function resendInvite(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(UserAccess::canManage($actor, $user) && $user->status === User::INVITED, 403);
        $link = UserAccess::sendInvite($user, $actor);
        Activity::log('user.invite_resent', "{$actor->name} re-sent the invite to {$user->name}.", $user);

        return back()->with('status', UserAccess::showLink() ? "New invite link created for {$user->email}, but email isn't being sent. Copy the link below." : "Invite sent again to {$user->email}.")
            ->with('invite_link', UserAccess::showLink() ? $link : null);
    }

    public function resetTwoFactor(Request $request, User $user)
    {
        $actor = $request->user();
        abort_unless(UserAccess::canManage($actor, $user) && $actor->id !== $user->id, 403);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Activity::log('user.2fa_reset', "{$actor->name} reset two-step login for {$user->name}.", $user);

        return back()->with('status', "{$user->name} will set up two-step login again next time they log in.");
    }
}
