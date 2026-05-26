<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id', 'profil_id', 'quantity', 'action'
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class);
    }
}
