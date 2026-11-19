<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','client_id','rate_card_id','name','end_client','notes'];

    public function client(): BelongsTo { return $this->belongsTo(Organisation::class, 'client_id'); }
    public function sites(): HasMany { return $this->hasMany(Site::class); }
    public function rateCard(): BelongsTo { return $this->belongsTo(RateCard::class); }
}
