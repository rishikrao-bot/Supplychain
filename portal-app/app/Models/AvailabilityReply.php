<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilityReply extends Model
{
    protected $fillable = ['availability_request_id','organisation_id','status','responded_at'];
    public $timestamps = false; protected $casts = ['responded_at' => 'datetime'];

    public function slots(): HasMany { return $this->hasMany(AvailabilitySlot::class); }
    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
}
