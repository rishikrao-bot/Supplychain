<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['name', 'slug', 'brand_colour', 'accent_colour', 'logo_path'];

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
