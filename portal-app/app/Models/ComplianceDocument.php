<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceDocument extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','organisation_id','user_id','name','blocks_offers','expires_on','file_path','status','uploaded_by','approved_by','approved_at'];
    protected $casts = ['expires_on' => 'date', 'approved_at' => 'datetime', 'blocks_offers' => 'boolean'];

    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** ok | expiring (within 30 days) | expired (or missing date) */
    public function state(): string { if (! $this->expires_on) return 'expired'; if ($this->expires_on->isPast()) return 'expired'; return $this->expires_on->lte(now()->addDays(30)) ? 'expiring' : 'ok'; }
}
