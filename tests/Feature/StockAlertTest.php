<?php

use App\Models\Product;
use App\Models\Setting;

it('muestra en Ventas los productos agotados y con pocas unidades', function () {
    Product::factory()->create(['name' => 'Producto Agotado', 'stock' => 0]);
    Product::factory()->create(['name' => 'Producto Poco', 'stock' => 2]);
    Product::factory()->create(['name' => 'Producto Lleno', 'stock' => 50]);

    $this->actingAs(admin())->get(route('admin.ventas.index'))
        ->assertOk()
        ->assertSee('Alertas de stock (2)')
        ->assertSee('Producto Agotado')
        ->assertSee('Producto Poco')
        ->assertSee('Quedan 2 unidades')
        ->assertDontSee('Producto Lleno');
});

it('no muestra el recuadro si todo tiene stock suficiente', function () {
    Product::factory()->create(['stock' => 50]);

    $this->actingAs(admin())->get(route('admin.ventas.index'))->assertDontSee('Alertas de stock');
});

it('respeta el umbral elegido en Ajustes', function () {
    Product::factory()->create(['name' => 'Producto Cinco', 'stock' => 5]);

    $this->actingAs(admin())->get(route('admin.ventas.index'))->assertDontSee('Producto Cinco');

    $this->actingAs(admin())->put(route('admin.ajustes.update'), ['store_name' => 'Mi tienda', 'whatsapp' => '3001234567', 'low_stock_threshold' => '5'])
        ->assertRedirect(route('admin.ajustes.edit'))
        ->assertSessionHas('success');

    expect(Setting::lowStockThreshold())->toBe(5);
    $this->actingAs(admin())->get(route('admin.ventas.index'))->assertSee('Producto Cinco');
});

it('valida el umbral en Ajustes', function () {
    $this->actingAs(admin())->put(route('admin.ajustes.update'), ['store_name' => 'Mi tienda', 'whatsapp' => '3001234567', 'low_stock_threshold' => '0'])
        ->assertSessionHasErrors(['low_stock_threshold' => 'Debe ser un número entero mayor o igual a 1.']);
});

it('avisa cuando una venta deja pocas unidades o agota el producto', function () {
    $p = Product::factory()->create(['name' => 'Ron X', 'stock' => 5]);
    $user = admin();

    $this->actingAs($user)->post(route('admin.ventas.store'), ['product_id' => $p->id, 'quantity' => '3', 'unit_price' => '1000'])
        ->assertSessionHas('warning', 'Quedan 2 unidades de «Ron X».');

    $this->actingAs($user)->post(route('admin.ventas.store'), ['product_id' => $p->id, 'quantity' => '2', 'unit_price' => '1000'])
        ->assertSessionHas('warning', '«Ron X» quedó agotado.');
});

it('filtra la lista de productos por stock bajo y muestra el número en el menú', function () {
    Product::factory()->create(['name' => 'Producto Poco', 'stock' => 1]);
    Product::factory()->create(['name' => 'Producto Lleno', 'stock' => 50]);

    $this->actingAs(admin())->get(route('admin.productos.index', ['stock' => 'bajo']))
        ->assertSee('Producto Poco')
        ->assertDontSee('Producto Lleno')
        ->assertSee('<span class="nav-badge"', false);
});
