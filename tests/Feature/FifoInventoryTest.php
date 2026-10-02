<?php

use App\Models\Product;
use App\Models\StockLot;
use App\Services\AdjustmentException;
use App\Services\AdjustmentService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Carbon\CarbonImmutable;

/** El stock del producto siempre debe ser igual a lo que queda en sus lotes. */
function assertStockMatchesLots(Product $p): void
{
    $p->refresh();
    expect((int) StockLot::where('product_id', $p->id)->sum('remaining'))->toBe($p->stock);
}

function emptyProduct(array $attrs = []): Product
{
    return Product::factory()->create(['stock' => 0, 'cost' => 0, 'price' => 12000] + $attrs);
}

it('ejemplo real: 10 a $8.000 hoy y 10 a $5.000 mañana; vender 15 cuesta $105.000', function () {
    $p = emptyProduct();
    $buy = app(PurchaseService::class);

    $buy->register($p->id, 10, 8000, null, CarbonImmutable::parse('2026-10-01 10:00'));
    $buy->register($p->id, 10, 5000, null, CarbonImmutable::parse('2026-10-02 10:00'));

    expect($p->fresh()->stock)->toBe(20)
        ->and($p->fresh()->inventoryValue())->toBe(130000)
        ->and($p->fresh()->cost)->toBe(6500); // promedio de referencia

    $sale = app(SaleService::class)->register($p->id, 15, 12000);

    // 10 × $8.000 (lote más antiguo) + 5 × $5.000 = $105.000
    expect($sale->total_cost)->toBe(105000)
        ->and($sale->total)->toBe(180000)
        ->and($sale->profit)->toBe(75000)
        ->and($sale->allocations()->orderBy('id')->get(['quantity', 'unit_cost'])->toArray())
        ->toBe([['quantity' => 10, 'unit_cost' => 8000], ['quantity' => 5, 'unit_cost' => 5000]]);

    // Quedan 5 unidades del lote de $5.000.
    expect($p->fresh()->stock)->toBe(5)
        ->and($p->fresh()->inventoryValue())->toBe(25000)
        ->and($p->fresh()->cost)->toBe(5000);

    assertStockMatchesLots($p);
});

it('cada venta toma el costo de los lotes en orden de llegada', function () {
    $p = emptyProduct();
    $buy = app(PurchaseService::class);
    $buy->register($p->id, 10, 8000, null, CarbonImmutable::parse('2026-10-01 10:00'));
    $buy->register($p->id, 10, 5000, null, CarbonImmutable::parse('2026-10-02 10:00'));
    $sell = app(SaleService::class);

    $first = $sell->register($p->id, 4, 12000);   // 4 × 8.000
    $second = $sell->register($p->id, 8, 12000);  // 6 × 8.000 + 2 × 5.000
    $third = $sell->register($p->id, 8, 12000);   // 8 × 5.000

    expect($first->total_cost)->toBe(32000)
        ->and($second->total_cost)->toBe(58000)
        ->and($third->total_cost)->toBe(40000)
        ->and($first->total_cost + $second->total_cost + $third->total_cost)->toBe(130000) // = lo invertido
        ->and($p->fresh()->stock)->toBe(0)
        ->and($p->fresh()->inventoryValue())->toBe(0);
});

it('anular una venta devuelve las unidades a los mismos lotes y con su costo', function () {
    $p = emptyProduct();
    $buy = app(PurchaseService::class);
    $buy->register($p->id, 10, 8000, null, CarbonImmutable::parse('2026-10-01 10:00'));
    $buy->register($p->id, 10, 5000, null, CarbonImmutable::parse('2026-10-02 10:00'));
    $sell = app(SaleService::class);

    $sale = $sell->register($p->id, 15, 12000);
    $sell->void($sale);

    expect($p->fresh()->stock)->toBe(20)->and($p->fresh()->inventoryValue())->toBe(130000);

    // La siguiente venta vuelve a salir del lote de $8.000 primero.
    expect($sell->register($p->id, 1, 12000)->total_cost)->toBe(8000);
    assertStockMatchesLots($p);
});

it('dar de baja unidades dañadas registra su costo real y no las vende', function () {
    $p = emptyProduct(['name' => 'Ron X']);
    $buy = app(PurchaseService::class);
    $buy->register($p->id, 3, 8000, null, CarbonImmutable::parse('2026-10-01 10:00'));
    $buy->register($p->id, 10, 5000, null, CarbonImmutable::parse('2026-10-02 10:00'));

    $adj = app(AdjustmentService::class)->takeOut($p->id, 4, 'danado', 'Se rompieron');

    expect($adj->total_cost)->toBe(29000) // 3 × 8.000 + 1 × 5.000
        ->and($p->fresh()->stock)->toBe(9);

    app(AdjustmentService::class)->void($adj);
    expect($p->fresh()->stock)->toBe(13)->and($p->fresh()->inventoryValue())->toBe(74000);
    assertStockMatchesLots($p);

    expect(fn () => app(AdjustmentService::class)->takeOut($p->id, 50, 'perdido'))
        ->toThrow(AdjustmentException::class, 'Solo quedan 13 unidades de «Ron X».');
});

it('agregar unidades sin compra crea un lote con el costo indicado', function () {
    $p = emptyProduct();

    $adj = app(AdjustmentService::class)->putIn($p->id, 2, 0, 'regalo');

    expect($p->fresh()->stock)->toBe(2)->and($adj->total_cost)->toBe(0);
    expect(app(SaleService::class)->register($p->id, 1, 12000)->profit)->toBe(12000);

    expect(fn () => app(AdjustmentService::class)->void($adj))
        ->toThrow(AdjustmentException::class, 'No puedes anular este ajuste: ya salieron unidades de ese lote.');
    assertStockMatchesLots($p);
});

it('al crear un producto nace sin stock aunque se intente enviar uno; el stock entra con compras', function () {
    $this->actingAs(admin())->post(route('admin.productos.store'), [
        'name' => 'Ron nuevo', 'category_id' => \App\Models\Category::first()->id,
        'cost' => '8.000', 'price' => '12.000', 'stock' => '10', 'is_visible' => '1',
    ])->assertSessionHasNoErrors();

    $p = Product::where('name', 'Ron nuevo')->sole();
    expect($p->stock)->toBe(0)->and($p->lots()->count())->toBe(0);

    app(PurchaseService::class)->register($p->id, 10, 8000);
    expect($p->fresh()->stock)->toBe(10)->and($p->fresh()->inventoryValue())->toBe(80000);
    assertStockMatchesLots($p);
});

it('el formulario de nuevo producto no pide stock', function () {
    $this->actingAs(admin())->get(route('admin.productos.create'))
        ->assertOk()
        ->assertDontSee('name="stock"', false)
        ->assertSee('El producto se crea sin unidades');
});

it('al editar un producto no se puede cambiar el stock ni el costo', function () {
    $p = Product::factory()->create(['cost' => 8000, 'price' => 12000, 'stock' => 10]);

    $this->actingAs(admin())->put(route('admin.productos.update', $p), [
        'name' => 'Nuevo nombre', 'category_id' => $p->category_id, 'price' => '13000',
        'cost' => '1', 'stock' => '999',
    ])->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->name)->toBe('Nuevo nombre')
        ->and($p->price)->toBe(13000)
        ->and($p->stock)->toBe(10)
        ->and($p->cost)->toBe(8000);

    $this->actingAs(admin())->get(route('admin.productos.edit', $p))
        ->assertOk()
        ->assertSee('Inventario por lotes')
        ->assertSee('name="stock" type="text" class="input" value="10" disabled', false);
});

it('ajusta inventario desde el formulario', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'cost' => 8000, 'stock' => 10]);

    $this->actingAs(admin())->post(route('admin.ajustes-inventario.store', $p), [
        'type' => 'baja', 'quantity' => '2', 'reason' => 'danado', 'note' => 'botellas rotas',
    ])->assertRedirect(route('admin.productos.edit', $p))
        ->assertSessionHas('success', 'Se sacaron 2 unidades de «Ron X» (valor $ 16.000).');

    expect($p->fresh()->stock)->toBe(8);

    $this->actingAs(admin())->post(route('admin.ajustes-inventario.store', $p), [
        'type' => 'alta', 'quantity' => '1', 'reason' => 'conteo', 'unit_cost' => '',
    ])->assertSessionHasErrors(['unit_cost' => 'Escribe el costo por unidad (puede ser 0 si fue regalo).']);
});
