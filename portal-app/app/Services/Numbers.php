<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/** Hands out running numbers per account (job numbers, quote numbers) without gaps or repeats. */
class Numbers
{
    public static function next(int $accountId, string $name, int $startAt = 1): int
    {
        return DB::transaction(function () use ($accountId, $name, $startAt) {
            $seq = NumberSequence::withoutGlobalScopes()->where('account_id', $accountId)->where('name', $name)->lockForUpdate()->first();
            if (! $seq) {
                $seq = NumberSequence::create(['account_id' => $accountId, 'name' => $name, 'next_value' => $startAt]);
            }
            $value = $seq->next_value;
            $seq->next_value = $value + 1;
            $seq->save();

            return $value;
        });
    }
}
