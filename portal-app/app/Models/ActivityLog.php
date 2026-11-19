<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityLog extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','user_id','subject_type','subject_id','action','message','data'];
    protected $table = 'activity_log'; public $timestamps = false; protected $casts = ['data' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
