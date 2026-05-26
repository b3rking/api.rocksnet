<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol'];

    public function profil(): HasMany
    {
        return $this->hasMany(Profil::class);
    }
}
