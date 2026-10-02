<?php

use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleException;
use App\Services\SaleService;
use Carbon\CarbonImmutable;

function sell(Product $product, int $qty, ?int $price = null): Sale
{
    return app(SaleService::class)->register($product->id, $qty, $price ?? $product->price);
}

it('una venta correcta descuenta el stock y guarda los valores del día', function () {
    $p = Product::factory()->create(['cost' => 40000, 'price' => 60000, 'stock' => 10]);

    $sale = sell($p, 3);

    expect($p->fresh()->stock)->toBe(7)
        ->and($sale->product_name)->toBe($p->name)
        ->and($sale->unit_cost)->toBe(40000)
        ->and($sale->unit_price)->toBe(60000)
        ->and($sale->total)->toBe(180000)
        ->and($sale->profit)->toBe(60000);
});

it('permite vender con descuento', function () {
    $p = Product::factory()->create(['cost' => 40000, 'price' => 60000, 'stock' => 5]);

    $sale = sell($p, 2, 50000);

    expect($sale->total)->toBe(100000)->and($sale->profit)->toBe(20000);
});

it('una venta mayor al stock falla y no cambia nada', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 3]);

    expect(fn () => sell($p, 4))->toThrow(SaleException::class, 'Solo quedan 3 unidades de «Ron X».');
    expect($p->fresh()->stock)->toBe(3)->and(Sale::count())->toBe(0);
});

it('no permite vender productos agotados', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 0]);

    expect(fn () => sell($p, 1))->toThrow(SaleException::class, '«Ron X» está agotado.');
});

it('anular devuelve las unidades al stock', function () {
    $p = Product::factory()->create(['stock' => 10]);
    $sale = sell($p, 4);

    app(SaleService::class)->void($sale);

    expect($p->fresh()->stock)->toBe(10)->and($sale->fresh()->voided_at)->not->toBeNull();
});

it('anular dos veces falla y no devuelve stock de más', function () {
    $p = Product::factory()->create(['stock' => 10]);
    $sale = sell($p, 4);
    app(SaleService::class)->void($sale);

    expect(fn () => app(SaleService::class)->void($sale))
        ->toThrow(SaleException::class, 'Esta venta ya estaba anulada.');
    expect($p->fresh()->stock)->toBe(10);
});

it('el costo guardado no cambia si luego edito el producto', function () {
    $p = Product::factory()->create(['cost' => 40000, 'price' => 60000, 'stock' => 10]);
    $sale = sell($p, 2);

    $this->actingAs(admin())->put(route('admin.productos.update', $p), [
        'name' => 'Nombre nuevo', 'category_id' => $p->category_id,
        'cost' => '55000', 'price' => '90000', 'stock' => '8',
    ])->assertSessionHasNoErrors();

    $sale->refresh();
    expect($sale->unit_cost)->toBe(40000)
        ->and($sale->unit_price)->toBe(60000)
        ->and($sale->profit)->toBe(40000)
        ->and($sale->product_name)->not->toBe('Nombre nuevo');
});

it('se puede anular la venta de un producto que luego fue eliminado', function () {
    $p = Product::factory()->create(['stock' => 5]);
    $sale = sell($p, 2);
    $p->delete();

    app(SaleService::class)->void($sale);

    expect(Product::withTrashed()->find($p->id)->stock)->toBe(5);
});

it('registra una venta desde el formulario', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'cost' => 40000, 'price' => 60000, 'stock' => 10]);

    $this->actingAs(admin())->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '2', 'unit_price' => '55.000',
    ])->assertRedirect(route('admin.ventas.index', ['periodo' => 'hoy']))
        ->assertSessionHas('success', 'Venta registrada: 2 unidades de «Ron X» por $ 110.000.');

    expect($p->fresh()->stock)->toBe(8);
});

it('muestra en el formulario el mensaje de stock insuficiente', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 3]);

    $this->actingAs(admin())->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '5', 'unit_price' => '1000',
    ])->assertSessionHasErrors(['quantity' => 'Solo quedan 3 unidades de «Ron X».']);
});

it('permite registrar una venta de días anteriores pero no del futuro', function () {
    $p = Product::factory()->create(['stock' => 10]);
    $user = admin();

    $this->actingAs($user)->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '1', 'unit_price' => '1000', 'sold_at' => '2026-09-15T18:30',
    ])->assertSessionHasNoErrors();
    expect(Sale::sole()->sold_at->format('Y-m-d H:i'))->toBe('2026-09-15 18:30');

    $this->actingAs($user)->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '1', 'unit_price' => '1000',
        'sold_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors(['sold_at' => 'La fecha de la venta no puede ser en el futuro.']);
});

it('anula desde el historial', function () {
    $p = Product::factory()->create(['stock' => 10]);
    $sale = sell($p, 3);

    $this->actingAs(admin())->from(route('admin.ventas.index'))
        ->post(route('admin.ventas.void', $sale))
        ->assertRedirect(route('admin.ventas.index'))
        ->assertSessionHas('success');

    expect($p->fresh()->stock)->toBe(10);
});

it('el historial suma solo ventas no anuladas y respeta el filtro de fechas', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00'));
    $p = Product::factory()->create(['cost' => 1000, 'price' => 3000, 'stock' => 100]);
    $svc = app(SaleService::class);

    $svc->register($p->id, 1, 3000, CarbonImmutable::parse('2026-10-15 09:00'));          // hoy
    $svc->register($p->id, 2, 3000, CarbonImmutable::parse('2026-10-13 09:00'));          // esta semana (martes)
    $svc->register($p->id, 4, 3000, CarbonImmutable::parse('2026-10-02 09:00'));          // este mes
    $svc->register($p->id, 8, 3000, CarbonImmutable::parse('2026-09-20 09:00'));          // mes pasado
    $svc->void($svc->register($p->id, 16, 3000, CarbonImmutable::parse('2026-10-15 10:00'))); // anulada

    $r = $this->actingAs(admin());

    $r->get(route('admin.ventas.index', ['periodo' => 'hoy']))->assertOk()
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 1 && (int) $t->total === 3000 && (int) $t->profit === 2000)
        ->assertSee('Anulada');

    $r->get(route('admin.ventas.index', ['periodo' => 'semana']))
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 3);

    $r->get(route('admin.ventas.index'))
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 7)
        ->assertViewHas('summary', fn ($s) => (int) $s['hoy']->profit === 2000
            && (int) $s['semana']->profit === 6000 && (int) $s['mes']->profit === 14000);

    $r->get(route('admin.ventas.index', ['periodo' => 'mes-pasado']))
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 8);

    $r->get(route('admin.ventas.index', ['desde' => '2026-09-01', 'hasta' => '2026-10-31']))
        ->assertViewHas('totals', fn ($t) => (int) $t->units === 15);
});

it('descarga el Excel de las ventas filtradas', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 10]);
    sell($p, 2);

    $response = $this->actingAs(admin())->get(route('admin.ventas.export', ['periodo' => 'mes']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');

    $file = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($file))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)->toContain('Precio unitario')->toContain('Ron X')->toContain('Válida');
});

it('las páginas de ventas exigen sesión', function () {
    $this->get(route('admin.ventas.index'))->assertRedirect(route('login'));
    $this->get(route('admin.ventas.export'))->assertRedirect(route('login'));
});

it('guarda una nota opcional en la venta y la muestra en el historial y el Excel', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'price' => 60000, 'stock' => 10]);
    $user = admin();

    $this->actingAs($user)->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '1', 'unit_price' => '55000', 'note' => '  Descuento cliente frecuente  ',
    ])->assertSessionHasNoErrors();

    expect(Sale::sole()->note)->toBe('Descuento cliente frecuente');

    $this->actingAs($user)->get(route('admin.ventas.index', ['periodo' => 'hoy']))->assertSee('Descuento cliente frecuente');

    $response = $this->actingAs($user)->get(route('admin.ventas.export', ['periodo' => 'hoy']));
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Nota')->toContain('Descuento cliente frecuente');
    $zip->close();
});

it('la nota es opcional y tiene un límite de 200 letras', function () {
    $p = Product::factory()->create(['stock' => 10]);
    $user = admin();

    $this->actingAs($user)->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '1', 'unit_price' => '1000',
    ])->assertSessionHasNoErrors();
    expect(Sale::sole()->note)->toBeNull();

    $this->actingAs($user)->post(route('admin.ventas.store'), [
        'product_id' => $p->id, 'quantity' => '1', 'unit_price' => '1000', 'note' => str_repeat('a', 201),
    ])->assertSessionHasErrors(['note' => 'La nota no puede tener más de 200 letras.']);
});

it('el formulario de venta tiene el campo de nota opcional', function () {
    Product::factory()->create(['stock' => 5]);

    $this->actingAs(admin())->get(route('admin.ventas.create'))
        ->assertOk()
        ->assertSee('name="note"', false)
        ->assertSee('Nota');
});
