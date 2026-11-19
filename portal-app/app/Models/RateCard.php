<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RateCard extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','client_id','name','valid_from','valid_to','status'];
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];

    public function items(): HasMany { return $this->hasMany(RateCardItem::class); }
    public function client(): BelongsTo { return $this->belongsTo(Organisation::class, 'client_id'); }
    /** Amount for a code, or null when TBC / missing. */
    public function rate(string $code): ?float { $i = $this->items->firstWhere('code', $code); return $i && $i->amount !== null ? (float) $i->amount : null; }
}
