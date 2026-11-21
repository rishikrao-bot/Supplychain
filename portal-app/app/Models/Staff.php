<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** A Compass person whose time is planned (whether or not they log in to the portal). */
class Staff extends Model
{
    use BelongsToAccount;

    protected $table = 'staff';

    public const TEAMS = ['Project Management', 'Commercial', 'Design', 'Other'];

    protected $fillable = ['account_id', 'name', 'position', 'team', 'days_per_week', 'starts_on', 'leaves_on', 'user_id', 'active', 'sort'];

    protected $casts = ['days_per_week' => 'decimal:1', 'starts_on' => 'date', 'leaves_on' => 'date', 'active' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function allocations(): HasMany { return $this->hasMany(Allocation::class); }

    /** Not yet started, or already left, in the week ending $week. */
    public function absentIn(Carbon $week): ?string
    {
        if ($this->starts_on && $week->lt($this->starts_on)) {
            return 'Not started';
        }
        if ($this->leaves_on && $week->copy()->subDays(4)->gt($this->leaves_on)) {
            return 'Left';
        }

        return null;
    }
}
