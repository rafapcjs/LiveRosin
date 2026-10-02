<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas unidades de un lote salieron en una venta o en una baja, y a qué costo.
 */
class LotAllocation extends Model
{
    protected $fillable = ['stock_lot_id', 'sale_id', 'adjustment_id', 'quantity', 'unit_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_cost' => 'integer'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }
}
