<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person at a client, prospect, supplier or partner company. */
class Contact extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'organisation_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'mobile', 'notes', 'owner_id', 'dataverse_id'];

    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function activities(): HasMany { return $this->hasMany(CrmActivity::class)->latest('occurred_at'); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
