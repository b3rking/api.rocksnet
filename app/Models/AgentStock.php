<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStock extends Model
{
    protected $fillable = [
        'user_id', 'profil_id', 'quantity'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class);
    }
}
