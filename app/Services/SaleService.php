<?php

namespace App\Services;

use App\Models\LotAllocation;
use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de las ventas. Todo pasa dentro de una transacción con el producto bloqueado,
 * así dos ventas al mismo tiempo nunca pueden dejar el stock en negativo.
 *
 * El costo de cada venta es el REAL de los lotes de donde salieron las unidades
 * (primero los más antiguos), no un promedio.
 */
class SaleService
{
    public function __construct(private InventoryService $inventory) {}

    public function register(int $productId, int $quantity, int $unitPrice, ?CarbonInterface $soldAt = null, ?string $note = null): Sale
    {
        if ($quantity < 1) {
            throw new SaleException('La cantidad debe ser al menos 1.');
        }

        return DB::transaction(function () use ($productId, $quantity, $unitPrice, $soldAt, $note) {
            /** @var Product|null $product */
            $product = Product::whereKey($productId)->lockForUpdate()->first();

            if (! $product) {
                throw new SaleException('Ese producto no existe o fue eliminado.');
            }

            if ($product->stock === 0) {
                throw new SaleException("«{$product->name}» está agotado.");
            }

            if ($quantity > $product->stock) {
                $units = $product->stock === 1 ? 'Solo queda 1 unidad' : "Solo quedan {$product->stock} unidades";
                throw new SaleException("{$units} de «{$product->name}».");
            }

            $consumed = $this->inventory->consume($product, $quantity);
            $total = $unitPrice * $quantity;

            $sale = Sale::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => (int) round($consumed['total_cost'] / $quantity),
                'total_cost' => $consumed['total_cost'],
                'total' => $total,
                'profit' => $total - $consumed['total_cost'],
                'sold_at' => $soldAt ?? now(),
                'note' => $note,
            ]);

            foreach ($consumed['allocations'] as $a) {
                LotAllocation::create($a + ['sale_id' => $sale->id]);
            }

            return $sale;
        });
    }

    public function void(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            /** @var Sale $locked */
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new SaleException('Esta venta ya estaba anulada.');
            }

            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();

            $locked->voided_at = now();
            $locked->save();

            $allocations = $locked->allocations()->get();
            if ($allocations->isNotEmpty()) {
                // Las unidades vuelven a los mismos lotes, con su mismo costo.
                $this->inventory->restore($product, $allocations);
            } else {
                // Venta registrada antes de existir los lotes: vuelve como un lote nuevo a su costo.
                $this->inventory->addLot($product, $locked->quantity, $locked->unit_cost, 'devolucion', $locked->sold_at);
            }

            return $locked;
        });
    }
}
