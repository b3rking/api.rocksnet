<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class Profil extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'duration', 'price', 'currency_id'];

    #[Override]
    protected function casts()
    {
        return [
            'price' => 'float'
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
