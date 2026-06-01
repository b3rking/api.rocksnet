<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockHistory extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id', 'profil_id', 'quantity', 'action'
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
