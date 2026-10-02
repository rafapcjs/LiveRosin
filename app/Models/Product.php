<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'category_id', 'description', 'photo_path', 'cost', 'price', 'stock', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    /** Valor exacto de lo que hay en bodega: suma de (unidades que quedan × costo) de cada lote. */
    public function inventoryValue(): int
    {
        return (int) $this->lots()->sum(DB::raw('remaining * unit_cost'));
    }

    /** Productos agotados o con pocas unidades (según el umbral de Ajustes). */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', Setting::lowStockThreshold());
    }

    public function isLowStock(): bool
    {
        return $this->stock <= Setting::lowStockThreshold();
    }

    /** Ganancia por unidad = precio − costo. */
    public function unitProfit(): int
    {
        return $this->price - $this->cost;
    }

    /** Margen sobre el precio (%) = ganancia / precio × 100. Null si el precio es 0. */
    public function marginPercent(): ?float
    {
        return $this->price > 0 ? $this->unitProfit() / $this->price * 100 : null;
    }

    /** Recargo sobre el costo (%) = ganancia / costo × 100. Null si el costo es 0. */
    public function markupPercent(): ?float
    {
        return $this->cost > 0 ? $this->unitProfit() / $this->cost * 100 : null;
    }

    /** Ganancia total del stock = ganancia por unidad × stock. */
    public function stockProfit(): int
    {
        return $this->unitProfit() * $this->stock;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->photo_path
            ? Storage::disk('public')->url(\App\Services\PhotoService::thumbPathFor($this->photo_path))
            : null;
    }
}
