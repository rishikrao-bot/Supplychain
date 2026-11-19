<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Writes a line to the audit trail. */
class Activity
{
    public static function log(string $action, string $message, ?Model $subject = null, array $data = [], ?int $accountId = null): void
    {
        $user = Auth::user();
        ActivityLog::create([
            'account_id' => $accountId ?? $user?->account_id ?? $subject?->account_id,
            'user_id' => $user?->id,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'message' => $message,
            'data' => $data ?: null,
        ]);
    }
}
