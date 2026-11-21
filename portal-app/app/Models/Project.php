<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A project that people's time is planned against (or Holiday / Training / Overhead). */
class Project extends Model
{
    use BelongsToAccount;

    public const TYPES = ['TSM', 'Validations', 'QS', 'Design', 'Project Management', 'Other'];

    protected $fillable = ['account_id', 'number', 'name', 'organisation_id', 'client_name', 'lead_id', 'type', 'days_allowed',
        'completion_on', 'completion_note', 'status', 'internal', 'opportunity_id', 'notes'];

    protected $casts = ['days_allowed' => 'decimal:1', 'completion_on' => 'date', 'internal' => 'boolean'];

    public function client(): BelongsTo { return $this->belongsTo(Organisation::class, 'organisation_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Staff::class, 'lead_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function allocations(): HasMany { return $this->hasMany(Allocation::class); }

    public function label(): string
    {
        return trim(($this->number ? $this->number.' ' : '').$this->name);
    }

    public function clientLabel(): ?string
    {
        return $this->client?->name ?? $this->client_name;
    }
}
