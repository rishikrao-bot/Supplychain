<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilityRequest extends Model
{
    protected $fillable = ['work_order_id','deadline_at','status'];
    protected $casts = ['deadline_at' => 'datetime'];

    public function replies(): HasMany { return $this->hasMany(AvailabilityReply::class); }
}
