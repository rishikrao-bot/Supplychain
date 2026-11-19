<?php

namespace App\Services;

use App\Mail\InviteMail;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Who may manage whom:
 * - Compass admins manage everyone in the account.
 * - Client admins manage people in their own client organisation.
 * - Partner managers manage people in their own partner organisation (their site staff).
 * Nobody changes their own role or deactivates themselves, and the last active
 * Compass admin cannot be removed.
 */
class UserAccess
{
    public static function manageableOrganisations(User $actor): Collection
    {
        if (! $actor->canManageUsers()) {
            return collect();
        }
        if ($actor->isCompassAdmin()) {
            return Organisation::orderByRaw("CASE type WHEN 'compass' THEN 0 WHEN 'client' THEN 1 ELSE 2 END")->orderBy('name')->get();
        }

        return Organisation::whereKey($actor->organisation_id)->get();
    }

    public static function canManage(User $actor, User $target): bool
    {
        if (! $actor->isActive() || ! $actor->canManageUsers() || $actor->account_id !== $target->account_id) {
            return false;
        }

        return $actor->isCompassAdmin() || $actor->organisation_id === $target->organisation_id;
    }

    public static function rolesFor(User $actor, Organisation $org): array
    {
        $roles = Roles::forOrgType($org->type);
        // Client admins and partner managers can't create Compass roles, obviously; partner
        // managers may add managers and site engineers in their own company only.
        return $roles;
    }

    public static function isLastAdmin(User $target): bool
    {
        return $target->role === Roles::ADMIN && $target->status === User::ACTIVE
            && User::where('role', Roles::ADMIN)->where('status', User::ACTIVE)
                ->whereHas('organisation', fn ($q) => $q->where('type', Organisation::COMPASS))->count() <= 1;
    }

    /** Creates (or re-sends) an invite and emails the link. Returns the link. */
    public static function sendInvite(User $user, ?User $by = null): string
    {
        $token = Str::random(48);
        $user->forceFill(['invite_token_hash' => hash('sha256', $token), 'invited_at' => now(), 'invited_by' => $by?->id, 'status' => User::INVITED])->save();
        $link = route('invite.show', $token);
        Mail::to($user->email)->send(new InviteMail($user, $link, $by?->name));

        return $link;
    }
}
