<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToAccount, HasFactory, Notifiable;

    public const INVITED = 'invited';
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';

    protected $fillable = ['account_id', 'organisation_id', 'name', 'email', 'phone', 'role', 'job_title', 'status',
        'password', 'invited_at', 'invited_by', 'last_login_at', 'disabled_at'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invite_token_hash', 'microsoft_oid'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function orgType(): string
    {
        return $this->organisation?->type ?? Organisation::COMPASS;
    }

    public function roleLabel(): string
    {
        return Roles::label($this->role);
    }

    public function isActive(): bool { return $this->status === self::ACTIVE; }
    public function isCompass(): bool { return $this->orgType() === Organisation::COMPASS; }
    public function isCompassAdmin(): bool { return $this->isCompass() && $this->role === Roles::ADMIN; }
    public function hasTwoFactor(): bool { return $this->two_factor_confirmed_at !== null; }

    /** Compass admin or helpdesk: may manage sites, contractors and other set-up data. */
    public function isCompassStaff(): bool
    {
        return $this->isCompass() && in_array($this->role, [Roles::ADMIN, Roles::HELPDESK], true);
    }

    /** Can this person add or change users at all? */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [Roles::ADMIN, Roles::CLIENT_ADMIN, Roles::PARTNER_MANAGER], true);
    }
}
