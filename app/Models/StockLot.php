<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un lote de mercancía: unidades que entraron juntas con un mismo costo.
 */
class StockLot extends Model
{
    public const SOURCES = [
        'inicial' => 'Inventario inicial',
        'compra' => 'Compra',
        'ajuste' => 'Ajuste de inventario',
        'devolucion' => 'Venta anulada',
    ];

    protected $fillable = [
        'product_id', 'source', 'purchase_id', 'adjustment_id', 'unit_cost', 'quantity', 'remaining', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'quantity' => 'integer',
            'remaining' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function sourceLabel(): string
    {
        $label = self::SOURCES[$this->source] ?? $this->source;

        if ($this->source === 'compra' && $this->purchase?->supplier) {
            $label .= ' · '.$this->purchase->supplier;
        }

        return $label;
    }
}
