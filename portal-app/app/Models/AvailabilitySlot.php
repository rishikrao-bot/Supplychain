<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilitySlot extends Model
{
    protected $fillable = ['availability_reply_id','date','window','client_preferred','booked'];
    public $timestamps = false; protected $casts = ['date' => 'date', 'client_preferred' => 'boolean', 'booked' => 'boolean'];

}
