<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Planner weeks are identified by the Friday they end on, as in the old tracker. */
final class Weeks
{
    public static function ending(Carbon|string|null $date = null): Carbon
    {
        $d = $date instanceof Carbon ? $date->copy() : Carbon::parse($date ?? 'today');
        $d = $d->startOfDay();

        return $d->isFriday() ? $d : $d->next(Carbon::FRIDAY);
    }

    /** @return array<int, Carbon> */
    public static function range(Carbon $first, int $count): array
    {
        return array_map(fn ($i) => $first->copy()->addWeeks($i), range(0, max(1, $count) - 1));
    }
}
