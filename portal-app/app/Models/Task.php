<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A follow-up for one person, kept in step with their Microsoft To Do ("your company Portal" list). */
class Task extends Model
{
    use BelongsToAccount;

    public const OPEN = 'open';
    public const DONE = 'done';

    protected $fillable = ['account_id', 'title', 'notes', 'due_on', 'status', 'completed_at', 'assigned_to', 'created_by',
        'organisation_id', 'contact_id', 'opportunity_id', 'dataverse_id'];

    protected $casts = ['due_on' => 'date', 'completed_at' => 'datetime', 'todo_modified_at' => 'datetime', 'todo_pending' => 'boolean'];

    public function todoUser(): BelongsTo { return $this->belongsTo(User::class, 'todo_user_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function organisation(): BelongsTo { return $this->belongsTo(Organisation::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }

    public function isDone(): bool { return $this->status === self::DONE; }
    public function isOverdue(): bool { return ! $this->isDone() && $this->due_on && $this->due_on->isPast() && ! $this->due_on->isToday(); }

    /** What it's about, for lists: "Client Co · Jo Bloggs · North Region tender". */
    public function about(): string
    {
        return collect([$this->organisation?->name, $this->contact?->name(), $this->opportunity?->title])->filter()->implode(' · ');
    }
}
