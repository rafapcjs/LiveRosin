<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'product_name', 'quantity', 'unit_price', 'unit_cost', 'total_cost', 'total', 'profit', 'note', 'sold_at',
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(LotAllocation::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'total_cost' => 'integer',
            'total' => 'integer',
            'profit' => 'integer',
            'sold_at' => 'datetime',
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

    /** Solo ventas válidas (las anuladas no cuentan en ningún total). */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
