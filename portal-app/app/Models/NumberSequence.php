<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberSequence extends Model
{
    use \App\Models\Concerns\BelongsToAccount;

    protected $fillable = ['account_id','name','next_value'];
    public $timestamps = false;

}
