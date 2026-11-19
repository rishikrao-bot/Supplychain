<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','number','work_order_id','site_id','description','value','inspection_fee','status','ordered_at'];
    protected $casts = ['ordered_at' => 'datetime'];

    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
