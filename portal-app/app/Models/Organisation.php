<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use BelongsToAccount;

    public const COMPASS = 'compass';
    public const CLIENT = 'client';
    public const PARTNER = 'partner';

    protected $fillable = ['account_id', 'type', 'name', 'contact_name', 'contact_phone', 'contact_email', 'address',
        'partner_type', 'zones', 'job_limit', 'vat_registered', 'cis_status', 'rates', 'suspended_at', 'settings', 'notes'];

    protected $casts = ['zones' => 'array', 'rates' => 'array', 'settings' => 'array', 'vat_registered' => 'boolean',
        'suspended_at' => 'datetime', 'job_limit' => 'decimal:2'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function competencies(): BelongsToMany
    {
        return $this->belongsToMany(Competency::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'client_id');
    }

    public function isCompass(): bool { return $this->type === self::COMPASS; }
    public function isClient(): bool { return $this->type === self::CLIENT; }
    public function isPartner(): bool { return $this->type === self::PARTNER; }
}
