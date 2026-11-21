<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A note, call, meeting or saved Outlook email on a company, contact or opportunity. */
class CrmActivity extends Model
{
    use BelongsToAccount;

    public const TYPES = ['note' => 'Note', 'call' => 'Call', 'meeting' => 'Meeting', 'email' => 'Email'];

    protected $fillable = ['account_id', 'organisation_id', 'contact_id', 'opportunity_id', 'type', 'subject', 'body',
        'occurred_at', 'user_id', 'outlook_message_id', 'outlook_link', 'dataverse_id'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
