<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkOrder;

/** Who can see a job: your own staff, people at the job's client, and the partner it was given to. */
class WorkOrderAccess
{
    public static function canView(User $user, WorkOrder $wo): bool
    {
        if (! $user->isActive() || $user->account_id !== $wo->account_id) {
            return false;
        }
        return match ($user->orgType()) {
            'operator' => true,
            'client' => $wo->client_id === $user->organisation_id,
            'partner' => $wo->assigned_org_id === $user->organisation_id,
            default => false,
        };
    }
}
