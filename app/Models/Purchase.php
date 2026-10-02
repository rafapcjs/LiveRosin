<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = [
        'product_id', 'product_name', 'quantity', 'unit_cost', 'total', 'supplier',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'total' => 'integer',
            'purchased_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Solo compras válidas (las anuladas no cuentan en ningún total). */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
