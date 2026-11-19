<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobException extends Model
{
    protected $fillable = ['work_order_id','type','title','detail','charge_note','question','actions','status','decision','decided_by','decided_at'];
    protected $casts = ['actions' => 'array', 'decided_at' => 'datetime'];

    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
