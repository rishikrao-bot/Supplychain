<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Project;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Reads and writes the weekly plan. */
class ResourcePlan
{
    /** Days per person per week: [staff_id][Y-m-d] => days. */
    public static function totals(array $weeks): array
    {
        $out = [];
        Allocation::whereBetween('week', [reset($weeks)->toDateString(), end($weeks)->toDateString()])
            ->selectRaw('staff_id, week, sum(days) as d')->groupBy('staff_id', 'week')->get()
            ->each(function ($r) use (&$out) {
                $out[$r->staff_id][Carbon::parse($r->week)->toDateString()] = (float) $r->d;
            });

        return $out;
    }

    /** Allocations in the window: [project_id][staff_id][Y-m-d] => days. */
    public static function grid(array $weeks, ?int $staffId = null, ?int $projectId = null): array
    {
        $out = [];
        Allocation::whereBetween('week', [reset($weeks)->toDateString(), end($weeks)->toDateString()])
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->get()->each(function ($a) use (&$out) {
                $out[$a->project_id][$a->staff_id][$a->week->toDateString()] = (float) $a->days;
            });

        return $out;
    }

    /** Set one cell. Blank or 0 removes it. Returns true if something changed. */
    public static function set(Project $project, Staff $staff, Carbon $week, ?float $days): bool
    {
        $key = ['project_id' => $project->id, 'staff_id' => $staff->id, 'week' => $week->toDateString()];
        $existing = Allocation::where($key)->first();
        if (! $days || $days <= 0) {
            return $existing ? (bool) $existing->delete() : false;
        }
        $days = round(min($days, 7) * 2) / 2; // half-day steps
        if ($existing && (float) $existing->days === $days) {
            return false;
        }
        Allocation::updateOrCreate($key, ['account_id' => $project->account_id, 'days' => $days]);

        return true;
    }

    /** Same number of days every week from..to. With $overwrite false, weeks already filled are left alone. */
    public static function fill(Project $project, Staff $staff, Carbon $from, Carbon $to, float $days, bool $overwrite = true): int
    {
        $n = 0;
        for ($w = $from->copy(); $w->lte($to); $w->addWeek()) {
            if (! $overwrite && Allocation::where(['project_id' => $project->id, 'staff_id' => $staff->id, 'week' => $w->toDateString()])->exists()) {
                continue;
            }
            $n += self::set($project, $staff, $w, $days) ? 1 : 0;
        }

        return $n;
    }

    /** Days planned per project: total, up to and including this week, and from next week on. */
    public static function projectDays(): Collection
    {
        $now = \App\Support\Weeks::ending()->toDateString();

        return Allocation::selectRaw('project_id, sum(days) as total, sum(case when week <= ? then days else 0 end) as to_date', [$now])
            ->groupBy('project_id')->get()->keyBy('project_id');
    }

    /** CSS class for a utilisation percentage. */
    public static function band(?float $pct): string
    {
        return match (true) {
            $pct === null => '',
            $pct > 100 => 'u-over',
            $pct >= 90 => 'u-full',
            $pct >= 50 => 'u-part',
            $pct > 0 => 'u-low',
            default => 'u-none',
        };
    }
}
