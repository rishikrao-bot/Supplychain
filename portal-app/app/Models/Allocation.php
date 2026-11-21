<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Days a person spends on a project in one week (week = the Friday it ends on). */
class Allocation extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'project_id', 'staff_id', 'week', 'days'];

    protected $casts = ['week' => 'date', 'days' => 'decimal:1'];

    /** Always store the plain date, so a week matches itself in every database. */
    public function setWeekAttribute($value): void
    {
        $this->attributes['week'] = \Illuminate\Support\Carbon::parse($value)->toDateString();
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
}
