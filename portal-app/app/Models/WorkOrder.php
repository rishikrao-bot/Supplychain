<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A maintenance job. Its reference is Q-{number} while it is an estimate and
 * P-{number} (same number) once the client approves it.
 */
class WorkOrder extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'number', 'client_id', 'contract_id', 'site_id', 'parent_id', 'client_ref',
        'client_order_no', 'source', 'priority', 'rate_band', 'location', 'description', 'nte', 'est_materials',
        'specialist_category', 'specialist_cost', 'status', 'appointment_window', 'assigned_org_id', 'assigned_user_id',
        'estimate_total', 'estimate_lines', 'approved_at', 'approved_by', 'approved_amount', 'created_by', 'received_at'];

    protected $casts = ['received_at' => 'datetime', 'approved_at' => 'datetime', 'estimate_lines' => 'array'];

    public function ref(): string
    {
        $isOrder = $this->approved_at !== null || ! in_array($this->status, ['awaiting', 'cancelled'], true);

        return ($isOrder ? 'P-' : 'Q-').$this->number;
    }

    public function client(): BelongsTo { return $this->belongsTo(Organisation::class, 'client_id'); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function trades(): HasMany { return $this->hasMany(WorkOrderTrade::class); }
    public function offers(): HasMany { return $this->hasMany(JobOffer::class); }
    public function availabilityRequests(): HasMany { return $this->hasMany(AvailabilityRequest::class); }
    public function visits(): HasMany { return $this->hasMany(Visit::class); }
    public function exceptions(): HasMany { return $this->hasMany(JobException::class); }
    public function quotes(): HasMany { return $this->hasMany(Quote::class); }
    public function valuations(): HasMany { return $this->hasMany(Valuation::class); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
    public function assignedOrg(): BelongsTo { return $this->belongsTo(Organisation::class, 'assigned_org_id'); }
}
