<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Services\PurchaseException;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Carbon\CarbonImmutable;

function buy(Product $product, int $qty, ?int $cost = null): Purchase
{
    return app(PurchaseService::class)->register($product->id, $qty, $cost ?? $product->cost);
}

it('una compra aumenta el stock y crea su lote', function () {
    $p = Product::factory()->create(['cost' => 40000, 'stock' => 2]);

    $purchase = buy($p, 10);

    expect($p->fresh()->stock)->toBe(12)
        ->and($purchase->total)->toBe(400000)
        ->and($p->lots()->where('purchase_id', $purchase->id)->value('remaining'))->toBe(10);
});

it('el costo del producto pasa a ser el promedio de lo que hay en bodega', function () {
    $p = Product::factory()->create(['cost' => 40000, 'stock' => 10]);

    buy($p, 10, 50000);

    expect($p->fresh()->cost)->toBe(45000)
        ->and($p->fresh()->inventoryValue())->toBe(900000);
});

it('anular una compra quita su lote y recalcula el costo', function () {
    $p = Product::factory()->create(['cost' => 40000, 'stock' => 2]);
    $purchase = buy($p, 10, 45000);

    app(PurchaseService::class)->void($purchase);

    expect($p->fresh()->stock)->toBe(2)
        ->and($p->fresh()->cost)->toBe(40000)
        ->and($purchase->fresh()->voided_at)->not->toBeNull();
});

it('no deja anular una compra si ya salieron unidades de su lote', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 0]);
    $purchase = buy($p, 5);
    app(SaleService::class)->register($p->id, 3, 1000);

    expect(fn () => app(PurchaseService::class)->void($purchase))
        ->toThrow(PurchaseException::class, 'No puedes anular esta compra: ya salieron 3 unidades de ese lote (vendidas o dadas de baja).');
    expect($p->fresh()->stock)->toBe(2);
});

it('anular dos veces falla', function () {
    $p = Product::factory()->create(['stock' => 0]);
    $purchase = buy($p, 5);
    app(PurchaseService::class)->void($purchase);

    expect(fn () => app(PurchaseService::class)->void($purchase))
        ->toThrow(PurchaseException::class, 'Esta compra ya estaba anulada.');
    expect($p->fresh()->stock)->toBe(0);
});

it('registra una compra desde el formulario', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'cost' => 40000, 'stock' => 3]);

    $this->actingAs(admin())->post(route('admin.compras.store'), [
        'product_id' => $p->id, 'quantity' => '12', 'unit_cost' => '42.000',
        'supplier' => 'Distribuidora Central',
    ])->assertRedirect(route('admin.compras.index', ['periodo' => 'hoy']))
        ->assertSessionHas('success', 'Compra registrada: 12 unidades de «Ron X» a $ 42.000 c/u ($ 504.000). Ahora hay 15 en stock, con un costo promedio de $ 41.600.');

    expect($p->fresh()->stock)->toBe(15)
        ->and(Purchase::sole()->supplier)->toBe('Distribuidora Central');
});

it('valida los datos de la compra en español', function () {
    $this->actingAs(admin())->post(route('admin.compras.store'), [
        'product_id' => '', 'quantity' => '0', 'unit_cost' => '-1',
    ])->assertSessionHasErrors([
        'product_id' => 'Elige el producto que compraste.',
        'quantity' => 'La cantidad debe ser un número entero mayor que 0.',
        'unit_cost' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
    ]);
});

it('muestra el historial con totales y filtros, sin contar anuladas', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
    $p = Product::factory()->create(['cost' => 1000, 'stock' => 0]);
    $svc = app(PurchaseService::class);

    $svc->register($p->id, 2, 1000, null, CarbonImmutable::parse('2026-10-15 09:00'));
    $svc->register($p->id, 5, 1000, null, CarbonImmutable::parse('2026-09-10 09:00'));
    $svc->void($svc->register($p->id, 3, 1000, null, CarbonImmutable::parse('2026-10-15 10:00')));

    $this->actingAs(admin())->get(route('admin.compras.index', ['periodo' => 'hoy']))
        ->assertOk()
        ->assertSee('Anulada')
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 2 && (int) $t->total === 2000)
        ->assertViewHas('summary', fn ($s) => (int) $s['mes']->total === 2000);

    $this->actingAs(admin())->get(route('admin.compras.index', ['periodo' => 'mes-pasado']))
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 5);
});

it('descarga el Excel de compras', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 0]);
    buy($p, 4);

    $response = $this->actingAs(admin())->get(route('admin.compras.export'));
    $response->assertOk();

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)->toContain('Proveedor')->toContain('Ron X');
});

it('el botón Reponer de las alertas lleva a registrar la compra', function () {
    $p = Product::factory()->create(['stock' => 1]);

    $this->actingAs(admin())->get(route('admin.ventas.index'))
        ->assertSee(route('admin.compras.create', ['producto' => $p->id]), false);
});

it('las páginas de compras exigen sesión', function () {
    $this->get(route('admin.compras.index'))->assertRedirect(route('login'));
    $this->get(route('admin.compras.create'))->assertRedirect(route('login'));
});
