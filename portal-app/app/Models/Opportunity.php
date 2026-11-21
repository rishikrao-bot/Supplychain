<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pipeline: an enquiry, tender or framework bid, from first contact to won or lost. */
class Opportunity extends Model
{
    use BelongsToAccount;

    public const KINDS = ['enquiry' => 'Enquiry', 'tender' => 'Tender', 'framework' => 'Framework'];

    /** stage => [label, chip colour, open?] */
    public const STAGES = [
        'lead' => ['Lead', '', true],
        'qualifying' => ['Qualifying', 'info', true],
        'pricing' => ['Pricing', 'info', true],
        'submitted' => ['Submitted', 'warn', true],
        'on_hold' => ['On hold', '', true],
        'won' => ['Won', 'ok', false],
        'lost' => ['Lost', 'crit', false],
    ];

    protected $fillable = ['account_id', 'organisation_id', 'contact_id', 'title', 'kind', 'stage', 'value', 'probability',
        'return_date', 'decision_date', 'lost_reason', 'notes', 'owner_id', 'closed_at', 'dataverse_id'];

    protected $casts = ['value' => 'decimal:2', 'return_date' => 'date', 'decision_date' => 'date', 'closed_at' => 'datetime'];

    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function activities(): HasMany { return $this->hasMany(CrmActivity::class)->latest('occurred_at'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function isOpen(): bool { return self::STAGES[$this->stage][2] ?? true; }
    public function stageLabel(): string { return self::STAGES[$this->stage][0] ?? $this->stage; }
    public function stageChip(): string { return self::STAGES[$this->stage][1] ?? ''; }

    public static function openStages(): array
    {
        return array_keys(array_filter(self::STAGES, fn ($s) => $s[2]));
    }
}
