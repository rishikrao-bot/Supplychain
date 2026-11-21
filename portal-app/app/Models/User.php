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

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invite_token_hash', 'microsoft_oid', 'microsoft_refresh_token'];

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
            'microsoft_refresh_token' => 'encrypted',
            'microsoft_connected_at' => 'datetime',
            'todo_synced_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function orgType(): string
    {
        return $this->organisation?->type ?? Organisation::OPERATOR;
    }

    public function roleLabel(): string
    {
        return Roles::label($this->role);
    }

    public function isActive(): bool { return $this->status === self::ACTIVE; }
    public function isOperator(): bool { return $this->orgType() === Organisation::OPERATOR; }
    public function isOperatorAdmin(): bool { return $this->isOperator() && $this->role === Roles::ADMIN; }
    public function hasTwoFactor(): bool { return $this->two_factor_confirmed_at !== null; }

    /** admin or helpdesk: may manage sites, contractors and other set-up data. */
    public function isOperatorStaff(): bool
    {
        return $this->isOperator() && in_array($this->role, [Roles::ADMIN, Roles::HELPDESK], true);
    }

    /** office staff (admin, helpdesk, finance) use the CRM. Engineers don't. */
    public function canUseCrm(): bool
    {
        return $this->isOperator() && in_array($this->role, [Roles::ADMIN, Roles::HELPDESK, Roles::FINANCE], true) && $this->isActive();
    }

    /** Outlook and To Do are connected for this person. */
    public function hasMicrosoft(): bool
    {
        return $this->microsoft_refresh_token !== null;
    }

    /** Can this person add or change users at all? */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [Roles::ADMIN, Roles::CLIENT_ADMIN, Roles::PARTNER_MANAGER], true);
    }
}
