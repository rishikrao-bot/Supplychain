<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','contract_id','name','address','postcode','zone','site_type','contact_name','contact_phone','access_notes','asbestos_notes','latitude','longitude','notes'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
