<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    use BelongsToAccount;

    public const OPERATOR = 'operator';
    public const CLIENT = 'client';
    public const PARTNER = 'partner';
    // CRM-only companies: nobody from them logs in to the portal.
    public const PROSPECT = 'prospect';
    public const SUPPLIER = 'supplier';
    public const OTHER = 'other';

    /** Types that can have portal users. */
    public const PORTAL_TYPES = [self::OPERATOR, self::CLIENT, self::PARTNER];

    public const CRM_TYPES = [self::CLIENT => 'Client', self::PROSPECT => 'Prospect', self::PARTNER => 'Supply chain partner',
        self::SUPPLIER => 'Supplier', self::OTHER => 'Other'];

    protected $fillable = ['account_id', 'type', 'name', 'contact_name', 'contact_phone', 'contact_email', 'address',
        'partner_type', 'zones', 'job_limit', 'vat_registered', 'cis_status', 'rates', 'suspended_at', 'settings', 'notes',
        'phone', 'email', 'website', 'postcode', 'sector', 'owner_id', 'dataverse_id'];

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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class)->orderBy('first_name');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->latest('occurred_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function typeLabel(): string
    {
        return self::CRM_TYPES[$this->type] ?? ucfirst($this->type);
    }

    public function isOperator(): bool { return $this->type === self::OPERATOR; }
    public function isClient(): bool { return $this->type === self::CLIENT; }
    public function isPartner(): bool { return $this->type === self::PARTNER; }
}
