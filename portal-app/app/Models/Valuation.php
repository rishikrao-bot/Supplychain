<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Valuation extends Model
{
    protected $fillable = ['work_order_id','lines','total','issued_at','issued_by'];
    protected $casts = ['lines' => 'array', 'issued_at' => 'datetime'];

}
