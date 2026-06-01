<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'payment_id',
        'period',
    ];

    /**
     * Get the payment associated with the invoice.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the client associated with the invoice.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
