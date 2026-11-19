<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyGroup extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','name','sort'];
    public $timestamps = false;

    public function competencies(): HasMany { return $this->hasMany(Competency::class); }
}
