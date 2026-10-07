<?php

use App\Http\Middleware\AgeGate;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\SaleService;
use Carbon\CarbonImmutable;

function adult(): Tests\TestCase
{
    return test()->withCookie(AgeGate::COOKIE, '1');
}

function setStore(array $data = []): void
{
    Setting::current()->update($data + ['store_name' => 'Licores La 70', 'tagline' => 'Domicilios en Medellín', 'whatsapp' => '573001234567']);
}

/* ---------------- Control de edad ---------------- */

it('sin confirmar la edad no muestra ningún producto', function () {
    Product::factory()->create(['name' => 'Ron Secreto']);

    $this->get('/')
        ->assertOk()
        ->assertSee('¿Eres mayor de 18 años?')
        ->assertSee('Sí, soy mayor de 18')
        ->assertDontSee('Ron Secreto');
});

it('el control de edad también protege los filtros por categoría', function () {
    $cat = Category::factory()->create(['name' => 'Rones']);
    Product::factory()->for($cat)->create(['name' => 'Ron Secreto']);

    $this->get('/?categoria=rones')->assertSee('¿Eres mayor de 18 años?')->assertDontSee('Ron Secreto');
});

it('al responder "Sí" guarda la confirmación por 30 días y vuelve al catálogo', function () {
    $response = $this->post(route('age.confirm'), ['respuesta' => 'si', 'volver' => '/?categoria=vapers']);

    $response->assertRedirect('/?categoria=vapers')->assertCookie(AgeGate::COOKIE, '1');

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === AgeGate::COOKIE);
    expect($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->timestamp);
});

it('al responder "No" muestra acceso denegado', function () {
    $this->post(route('age.confirm'), ['respuesta' => 'no'])
        ->assertRedirect(route('age.denied'))
        ->assertCookieMissing(AgeGate::COOKIE);

    $this->get(route('age.denied'))->assertSee('Acceso no permitido');
});

it('no redirige a otras páginas web después de confirmar la edad', function () {
    $this->post(route('age.confirm'), ['respuesta' => 'si', 'volver' => 'https://otro-sitio.com'])->assertRedirect('/');
    $this->post(route('age.confirm'), ['respuesta' => 'si', 'volver' => '//otro-sitio.com'])->assertRedirect('/');
});

/* ---------------- Catálogo ---------------- */

it('muestra solo productos visibles y no eliminados', function () {
    setStore();
    Product::factory()->create(['name' => 'Producto Visible']);
    Product::factory()->create(['name' => 'Producto Oculto', 'is_visible' => false]);
    Product::factory()->create(['name' => 'Producto Borrado'])->delete();

    adult()->get('/')
        ->assertOk()
        ->assertSee('Licores La 70')
        ->assertSee('Domicilios en Medellín')
        ->assertSee('Producto Visible')
        ->assertDontSee('Producto Oculto')
        ->assertDontSee('Producto Borrado');
});

it('muestra el estado según el stock y el umbral de Ajustes', function () {
    setStore(['low_stock_threshold' => 3]);
    Product::factory()->create(['name' => 'Producto A', 'stock' => 10]);
    Product::factory()->create(['name' => 'Producto B', 'stock' => 3]);
    Product::factory()->create(['name' => 'Producto C', 'stock' => 0]);

    $html = adult()->get('/')->getContent();

    // Cada tarjeta (<article>) debe llevar la etiqueta que le corresponde.
    $card = fn (string $name) => collect(explode('<article', $html))->first(fn ($c) => str_contains($c, $name));

    expect($card('Producto A'))->toMatch('/badge-success">Disponible</')
        ->and($card('Producto B'))->toMatch('/badge-warning">Últimas unidades</')
        ->and($card('Producto C'))->toMatch('/badge-danger">Agotado</');
});

it('muestra el botón de carrito y mantiene deshabilitado el producto agotado', function () {
    setStore();
    Product::factory()->create(['name' => 'Ron Viejo', 'price' => 65000, 'stock' => 5]);
    Product::factory()->create(['name' => 'Tequila', 'price' => 98000, 'stock' => 0]);

    $html = adult()->get('/')->getContent();

    expect($html)->toContain('data-cart-add')
        ->toContain('aria-label="Agregar Ron Viejo al carrito"')
        ->toContain('<button type="button" class="btn btn-block" disabled>Producto agotado</button>')
        ->not->toContain('aria-label="Agregar Tequila al carrito"');
});

it('filtra por categoría con un parámetro en la dirección', function () {
    setStore();
    $vapers = Category::factory()->create(['name' => 'Vapers 2']);
    $rones = Category::factory()->create(['name' => 'Rones']);
    Product::factory()->for($vapers)->create(['name' => 'Vaper Mango']);
    Product::factory()->for($rones)->create(['name' => 'Ron Viejo']);

    $html = adult()->get('/?categoria=rones')
        ->assertSee('?categoria=vapers-2', false)
        ->assertSee('1 producto en Rones')
        ->getContent();

    // Todos vienen en la página (para filtrar al instante), pero los de otra categoría van ocultos.
    $card = fn (string $name) => collect(explode('<article', $html))->first(fn ($c) => str_contains($c, $name));
    $isHidden = fn (string $chunk) => (bool) preg_match('/^[^>]*\shidden\s*>/', $chunk);
    expect($card('Ron Viejo'))->toContain('data-cat="rones"')
        ->and($isHidden($card('Ron Viejo')))->toBeFalse()
        ->and($isHidden($card('Vaper Mango')))->toBeTrue();

    adult()->get('/')->assertSee('2 productos');
    adult()->get('/?categoria=no-existe')->assertSee('Esa categoría no existe');
});

it('muestra la fecha de la última actualización, incluyendo cambios de stock por ventas', function () {
    setStore();
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));
    $p = Product::factory()->create(['stock' => 5]);
    Setting::current()->touch();

    $this->travelTo(CarbonImmutable::parse('2026-10-03 15:30'));
    app(SaleService::class)->register($p->id, 1, 1000);

    adult()->get('/')->assertSee('3 de octubre de 2026, 03:30 p. m.');
});

it('refleja al instante los cambios de precio (sin caché)', function () {
    setStore();
    $p = Product::factory()->create(['name' => 'Ron Viejo', 'price' => 65000]);

    adult()->get('/')->assertSee('$ 65.000')->assertHeader('Cache-Control', 'no-cache, private');

    $p->update(['price' => 70000]);
    adult()->get('/')->assertSee('$ 70.000')->assertDontSee('$ 65.000');
});

/* ---------------- Privacidad (crítico) ---------------- */

it('el HTML público nunca contiene costo, ganancia ni stock exacto', function () {
    setStore(['low_stock_threshold' => 3]);
    // Valores raros a propósito, para que no aparezcan por casualidad en otra parte de la página.
    Product::factory()->create(['name' => 'Whisky Secreto', 'cost' => 98761, 'price' => 154321, 'stock' => 4729]);
    Product::factory()->create(['name' => 'Vaper Secreto', 'cost' => 12347, 'price' => 30001, 'stock' => 2]);
    app(SaleService::class)->register(Product::where('name', 'Whisky Secreto')->value('id'), 7, 150001);

    $html = adult()->get('/')->assertOk()->getContent();

    expect($html)->toContain('Whisky Secreto')->toContain('$ 154.321');

    foreach ([
        '98761', '98.761',        // costo
        '55560', '55.560',        // ganancia por unidad (154.321 − 98.761)
        '4722', '4.722', '4729',  // stock exacto (antes y después de la venta)
        '12347', '12.347',        // costo del otro producto
        '150001', '150.001',      // precio de la venta registrada
        'cost', 'stock', 'margen', 'ganancia',
    ] as $secret) {
        expect(str_contains(strtolower($html), strtolower($secret)))->toBeFalse("El catálogo deja ver: {$secret}");
    }
});

it('las vistas públicas reciben objetos del catálogo y no modelos completos', function () {
    setStore();
    Product::factory()->create();

    adult()->get('/')->assertViewHas('items', fn ($items) => $items->every(fn ($i) => $i instanceof App\Catalog\CatalogItem));
});

it('puede pedir a los buscadores que no muestren el catálogo', function () {
    config(['tienda.noindex' => true]);
    adult()->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    config(['tienda.noindex' => false]);
    adult()->get('/')->assertDontSee('noindex', false);
});

/* ---------------- Ajustes de la tienda ---------------- */

it('guarda el WhatsApp como 57 + número y valida el formato colombiano', function () {
    $this->actingAs(admin())->put(route('admin.ajustes.update'), [
        'store_name' => 'Licores La 70', 'tagline' => 'Domicilios', 'whatsapp' => '+57 300 123 4567', 'low_stock_threshold' => '3',
    ])->assertSessionHasNoErrors();

    expect(Setting::current()->fresh()->whatsapp)->toBe('573001234567');

    $this->actingAs(admin())->put(route('admin.ajustes.update'), [
        'store_name' => 'X', 'whatsapp' => '6041234567', 'low_stock_threshold' => '3',
    ])->assertSessionHasErrors(['whatsapp' => 'El número debe ser un celular colombiano de 10 dígitos que empiece por 3. Ej: 300 123 4567.']);
});

it('al cambiar el WhatsApp en Ajustes, los botones del catálogo usan el número nuevo', function () {
    setStore(['whatsapp' => '573113809789']);
    Product::factory()->create(['name' => 'Ron Viejo', 'stock' => 5]);

    adult()->get('/')->assertSee('https://wa.me/573113809789?text=', false);

    $this->actingAs(admin())->put(route('admin.ajustes.update'), [
        'store_name' => 'Licores La 70', 'whatsapp' => '320 555 1234', 'low_stock_threshold' => '3',
    ])->assertSessionHasNoErrors();
    Setting::forget();

    adult()->get('/')
        ->assertSee('https://wa.me/573205551234?text=', false)
        ->assertDontSee('573113809789', false);
});

/* ---------------- Menú y buscador ---------------- */

it('muestra la barra con menú y buscador, y el menú lista las categorías con su cantidad', function () {
    setStore();
    $rones = Category::factory()->create(['name' => 'Rones']);
    Product::factory()->count(2)->for($rones)->create();

    adult()->get('/')
        ->assertSee('data-menu-open', false)
        ->assertSee('placeholder="Buscar"', false)
        ->assertSee('?categoria=rones#productos', false)
        ->assertSeeInOrder(['Rones', '<span class="drawer-count">2</span>'], false);
});

it('busca por nombre sin importar tildes ni mayúsculas', function () {
    setStore();
    Product::factory()->create(['name' => 'Aguardiente Antioqueño', 'description' => 'Tapa azul']);
    Product::factory()->create(['name' => 'Ron Viejo', 'description' => 'Añejo']);

    $html = adult()->get('/?q=ANTIOQUENO')->assertSee('1 resultado para «ANTIOQUENO»')->getContent();

    $card = fn (string $name) => collect(explode('<article', $html))->first(fn ($c) => str_contains($c, $name));
    $isHidden = fn (string $chunk) => (bool) preg_match('/^[^>]*\shidden\s*>/', $chunk);
    expect($isHidden($card('Aguardiente')))->toBeFalse()
        ->and($isHidden($card('Ron Viejo')))->toBeTrue();
});

it('avisa cuando la búsqueda no encuentra nada', function () {
    setStore();
    Product::factory()->create(['name' => 'Ron Viejo']);

    adult()->get('/?q=tequila')
        ->assertSee('0 resultados para «tequila»')
        ->assertSee('No encontramos productos para «tequila».')
        ->assertDontSee('data-no-results hidden', false);
});

it('la búsqueda escapa lo que escribe el visitante', function () {
    setStore();
    Product::factory()->create();

    adult()->get('/?q='.urlencode('<script>alert(1)</script>'))
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('incluye la ventana de detalle con cantidad y solo datos públicos del producto', function () {
    setStore(['whatsapp' => '573113809789']);
    Product::factory()->create(['name' => 'Don Juan Salts', 'description' => '30 ml', 'price' => 70000, 'cost' => 41237, 'stock' => 9]);

    $html = adult()->get('/')
        ->assertSee('data-product-modal', false)
        ->assertSee('data-wa="573113809789"', false)
        ->assertSee('data-pm-qty', false)
        ->getContent();

    $card = collect(explode('<article', $html))->first(fn ($c) => str_contains($c, 'Don Juan Salts'));
    expect($card)->toContain('data-name="Don Juan Salts"')
        ->toContain('data-desc="30 ml"')
        ->toContain('data-price="70000"')
        ->not->toContain('41237')
        ->not->toContain('41.237');
});

it('tiene la sección Quiénes somos con el botón de precios al por mayor', function () {
    setStore(['whatsapp' => '573113809789']);
    Product::factory()->create();

    adult()->get('/')
        ->assertSee('id="quienes-somos"', false)
        ->assertSee('proveedores al por mayor y al detal', false)
        ->assertSee('href="#quienes-somos"', false)
        ->assertSee('https://wa.me/573113809789?text='.rawurlencode('Hola, quiero información de precios al por mayor.'), false);
});
