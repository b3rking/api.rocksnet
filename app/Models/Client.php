<?php

namespace App\Models;

use App\Enums\ClientEtat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'phone', 'adress', 'subscription_id', 'etat', 'email'
    ];

    protected $casts = [
        'etat' => ClientEtat::class
    ];

    /**
     * Get the internet subscription tier assigned to this client.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get all transaction ledger entries settled by this client.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
