<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    protected $fillable = ['work_order_id','user_id','arrived_at','departed_at','arrival_lat','arrival_lng','safety_checklist','notes','outcome','outcome_detail','calls_to_site','within_window','signed_by','expenses'];
    protected $casts = ['arrived_at' => 'datetime', 'departed_at' => 'datetime', 'safety_checklist' => 'array', 'within_window' => 'boolean'];

    public function materials(): HasMany { return $this->hasMany(VisitMaterial::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
}
