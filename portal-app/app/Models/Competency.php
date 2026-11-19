<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','competency_group_id','name','certificate_required'];

    public function group(): BelongsTo { return $this->belongsTo(CompetencyGroup::class, 'competency_group_id'); }
    public function organisations(): BelongsToMany { return $this->belongsToMany(Organisation::class); }
}
