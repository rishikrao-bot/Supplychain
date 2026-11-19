<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attachment extends Model
{
    protected $fillable = ['account_id','attachable_type','attachable_id','kind','path','original_name','mime','size','meta','uploaded_by'];
    protected $casts = ['meta' => 'array'];

    public function attachable(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
}
