<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'amount', 'currency_id',
        'saved_by', 'agent_id',
        'description', 'stock_history_id',
        'payment_type', 'invoice_id', 'payment_method'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_type' => PaymentTypeEnum::class,
        'payment_method' => PaymentMethodEnum::class
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stockHistory(): BelongsTo
    {
        return $this->belongsTo(StockHistory::class);
    }

    // public function invoice(): BelongsTo
    // {
    //     return $this->belongsTo(invoice::class);
    // }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
