<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOffer extends Model
{
    protected $fillable = ['work_order_id','organisation_id','mode','status','deadline_at','responded_at','reason'];
    protected $casts = ['deadline_at' => 'datetime', 'responded_at' => 'datetime'];

    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
}
