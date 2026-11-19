<?php

namespace App\Models\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps each company's data separate. When someone is logged in, every query on a
 * model using this trait is limited to their account, and new records are stamped
 * with it automatically.
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $query) {
            if (Auth::hasUser()) {
                $query->where($query->getModel()->getTable().'.account_id', Auth::user()->account_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->account_id && Auth::hasUser()) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
