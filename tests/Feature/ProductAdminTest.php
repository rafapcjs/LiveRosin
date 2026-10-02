<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\PhotoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function validProduct(array $over = []): array
{
    return array_merge([
        'name' => 'Ron de prueba',
        'category_id' => Category::firstOrCreate(['name' => 'Destilados'])->id,
        'description' => 'Un ron cualquiera',
        'cost' => '40.000',
        'price' => '$ 60.000',
        'stock' => '10',
        'is_visible' => '1',
    ], $over);
}

it('muestra la lista con ganancia, margen y recargo', function () {
    Product::factory()->create(['name' => 'Ron X', 'cost' => 40000, 'price' => 60000, 'stock' => 10]);

    $this->actingAs(admin())->get(route('admin.productos.index'))
        ->assertOk()
        ->assertSee('Ron X')
        ->assertSee('$ 20.000')    // ganancia por unidad
        ->assertSee('33,3 %')      // margen sobre el precio
        ->assertSee('50,0 %')      // recargo sobre el costo
        ->assertSee('$ 200.000');  // ganancia del stock
});

it('crea un producto aceptando montos con puntos de miles', function () {
    $response = $this->actingAs(admin())->post(route('admin.productos.store'), validProduct());

    $p = Product::sole();
    // Al crear, lleva directo a registrar la compra de ese producto.
    $response->assertRedirect(route('admin.compras.create', ['producto' => $p->id]))
        ->assertSessionHas('success', "Se creó el producto «Ron de prueba». Ahora registra la compra para agregarle unidades.");

    expect($p->cost)->toBe(40000)
        ->and($p->price)->toBe(60000)
        ->and($p->is_visible)->toBeTrue();
});

it('muestra errores claros en español', function () {
    $this->actingAs(admin())
        ->post(route('admin.productos.store'), validProduct([
            'name' => '', 'price' => '-5', 'cost' => 'abc', 'category_id' => '999999',
        ]))
        ->assertSessionHasErrors([
            'name' => 'El nombre es obligatorio.',
            'price' => 'El precio debe ser un número mayor o igual a 0, sin decimales.',
            'cost' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
            'category_id' => 'Elige una categoría de la lista.',
        ]);

    expect(Product::count())->toBe(0);
});

it('permite guardar un precio menor que el costo (solo avisa)', function () {
    $this->actingAs(admin())
        ->post(route('admin.productos.store'), validProduct(['cost' => '10000', 'price' => '8000']))
        ->assertSessionHasNoErrors();

    expect(Product::sole()->unitProfit())->toBe(-2000);
});

it('guarda la foto con nombre aleatorio, reducida y con miniatura', function () {
    Storage::fake('public');

    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'photo' => UploadedFile::fake()->image('mi foto.jpg', 3000, 2000),
    ]))->assertSessionHasNoErrors();

    $path = Product::sole()->photo_path;
    expect($path)->not->toContain('mi foto');
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertExists(PhotoService::thumbPathFor($path));

    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
    expect(max($w, $h))->toBe(1200);
});

it('rechaza fotos de más de 4 MB o de otro tipo', function () {
    Storage::fake('public');

    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'photo' => UploadedFile::fake()->image('grande.jpg')->size(5000),
    ]))->assertSessionHasErrors(['photo' => 'La foto pesa más de 4 MB. Elige una más liviana.']);

    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
    ]))->assertSessionHasErrors(['photo' => 'La foto debe ser JPG, PNG o WEBP.']);
});

it('edita un producto y reemplaza la foto', function () {
    Storage::fake('public');
    $user = admin();

    $this->actingAs($user)->post(route('admin.productos.store'), validProduct([
        'photo' => UploadedFile::fake()->image('a.jpg', 800, 800),
    ]));
    $product = Product::sole();
    $old = $product->photo_path;

    $this->actingAs($user)->put(route('admin.productos.update', $product), validProduct([
        'name' => 'Ron editado',
        'is_visible' => '0',
        'photo' => UploadedFile::fake()->image('b.png', 600, 600),
    ]))->assertRedirect(route('admin.productos.index'));

    $product->refresh();
    expect($product->name)->toBe('Ron editado')
        ->and($product->is_visible)->toBeFalse()
        ->and($product->photo_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
});

it('elimina con borrado suave para no dañar el historial', function () {
    $product = Product::factory()->create();

    $this->actingAs(admin())->delete(route('admin.productos.destroy', $product))
        ->assertRedirect(route('admin.productos.index'));

    expect(Product::count())->toBe(0)
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('la base de datos impide stock negativo', function () {
    $product = Product::factory()->create();

    expect(fn () => $product->forceFill(['stock' => -1])->saveQuietly())
        ->toThrow(QueryException::class);
});

it('crea una categoría nueva desde el formulario del producto', function () {
    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'category_id' => 'nueva', 'new_category' => '  Cervezas ',
    ]))->assertSessionHasNoErrors();

    $product = Product::sole();
    expect($product->category->name)->toBe('Cervezas')
        ->and(Category::where('name', 'Cervezas')->count())->toBe(1);
});

it('reutiliza la categoría si ya existe con ese nombre', function () {
    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'category_id' => 'nueva', 'new_category' => 'destilados',
    ]))->assertSessionHasNoErrors();

    expect(Category::whereRaw('LOWER(name) = ?', ['destilados'])->count())->toBe(1)
        ->and(Product::sole()->category->name)->toBe('Destilados');
});

it('pide el nombre si eligió crear una categoría nueva', function () {
    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'category_id' => 'nueva', 'new_category' => '',
    ]))->assertSessionHasErrors(['new_category' => 'Escribe el nombre de la nueva categoría.']);

    expect(Product::count())->toBe(0);
});

it('no crea la categoría si el producto tiene errores', function () {
    $before = Category::count();

    $this->actingAs(admin())->post(route('admin.productos.store'), validProduct([
        'category_id' => 'nueva', 'new_category' => 'Cervezas', 'name' => '',
    ]))->assertSessionHasErrors('name');

    expect(Category::count())->toBe($before);
});
