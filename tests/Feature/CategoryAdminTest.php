<?php

use App\Models\Category;
use App\Models\Product;

it('trae las categorías iniciales Destilados, Vapers y Otras', function () {
    expect(Category::ordered()->pluck('name')->all())->toBe(['Destilados', 'Otras', 'Vapers']);
});

it('lista las categorías con su número de productos', function () {
    $cat = Category::factory()->create(['name' => 'Cervezas']);
    Product::factory()->count(2)->for($cat)->create();

    $this->actingAs(admin())->get(route('admin.categorias.index'))
        ->assertOk()
        ->assertSee('Cervezas')
        ->assertSee('Destilados');
});

it('crea una categoría y le genera su dirección para el filtro', function () {
    $this->actingAs(admin())
        ->post(route('admin.categorias.store'), ['name' => '  Licores   Finos '])
        ->assertRedirect(route('admin.categorias.index'))
        ->assertSessionHas('success');

    $cat = Category::where('name', 'Licores Finos')->sole();
    expect($cat->slug)->toBe('licores-finos');
});

it('no permite nombres repetidos ni vacíos', function () {
    $this->actingAs(admin())
        ->post(route('admin.categorias.store'), ['name' => 'Destilados'])
        ->assertSessionHasErrors(['name' => 'Ya existe una categoría con ese nombre.']);

    $this->actingAs(admin())
        ->post(route('admin.categorias.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'El nombre es obligatorio.']);
});

it('edita una categoría', function () {
    $cat = Category::factory()->create(['name' => 'Snaks']);

    $this->actingAs(admin())
        ->put(route('admin.categorias.update', $cat), ['name' => 'Snacks'])
        ->assertRedirect(route('admin.categorias.index'));

    expect($cat->fresh()->name)->toBe('Snacks')->and($cat->fresh()->slug)->toBe('snacks');
});

it('no deja eliminar una categoría que tiene productos', function () {
    $cat = Category::factory()->create(['name' => 'Cervezas']);
    Product::factory()->count(3)->for($cat)->create();

    $this->actingAs(admin())->delete(route('admin.categorias.destroy', $cat))
        ->assertSessionHas('error', 'No puedes eliminar «Cervezas» porque tiene 3 productos. Muévelos primero a otra categoría.');

    expect(Category::find($cat->id))->not->toBeNull();
});

it('elimina una categoría vacía (aunque tenga productos ya eliminados)', function () {
    $cat = Category::factory()->create();
    Product::factory()->for($cat)->create()->delete();

    $this->actingAs(admin())->delete(route('admin.categorias.destroy', $cat))
        ->assertSessionHas('success');

    expect(Category::find($cat->id))->toBeNull();
});

it('no deja asignar un producto a una categoría eliminada', function () {
    $cat = Category::factory()->create();
    $cat->delete();

    $this->actingAs(admin())->post(route('admin.productos.store'), [
        'name' => 'X', 'category_id' => $cat->id, 'cost' => '1', 'price' => '2', 'stock' => '1',
    ])->assertSessionHasErrors(['category_id' => 'Elige una categoría de la lista.']);
});

it('filtra la lista de productos por categoría', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create();
    Product::factory()->for($a)->create(['name' => 'Producto Alfa']);
    Product::factory()->for($b)->create(['name' => 'Producto Beta']);

    $this->actingAs(admin())->get(route('admin.productos.index', ['categoria' => $a->id]))
        ->assertSee('Producto Alfa')
        ->assertDontSee('Producto Beta');
});
