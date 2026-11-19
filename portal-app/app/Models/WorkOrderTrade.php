<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderTrade extends Model
{
    protected $fillable = ['work_order_id','trade','competency_id','est_hours','is_primary'];
    public $timestamps = false; protected $casts = ['is_primary' => 'boolean'];

    public function competency(): BelongsTo { return $this->belongsTo(Competency::class); }
}
