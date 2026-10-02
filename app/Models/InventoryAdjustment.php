<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajuste de inventario: unidades que salen sin venta (baja) o entran sin compra (alta).
 */
class InventoryAdjustment extends Model
{
    public const REASONS_OUT = [
        'danado' => 'Producto dañado o vencido',
        'perdido' => 'Pérdida o robo',
        'consumo' => 'Consumo propio o regalo a un cliente',
        'conteo' => 'Corrección de conteo (había menos)',
        'otro' => 'Otro motivo',
    ];

    public const REASONS_IN = [
        'conteo' => 'Corrección de conteo (había más)',
        'regalo' => 'Mercancía recibida sin costo (regalo, bonificación)',
        'otro' => 'Otro motivo',
    ];

    protected $fillable = [
        'product_id', 'product_name', 'type', 'reason', 'note', 'quantity', 'total_cost', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total_cost' => 'integer',
            'adjusted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LotAllocation::class, 'adjustment_id');
    }

    public function isOut(): bool
    {
        return $this->type === 'baja';
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function reasonLabel(): string
    {
        $list = $this->isOut() ? self::REASONS_OUT : self::REASONS_IN;

        return $list[$this->reason] ?? $this->reason;
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }
}
