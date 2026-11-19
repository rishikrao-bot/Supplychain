<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','key','value','status','note'];
    protected $casts = ['value' => 'array'];

}
