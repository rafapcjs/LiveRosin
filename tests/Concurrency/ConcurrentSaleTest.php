<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
 * Esta prueba usa procesos reales en paralelo, cada uno con su propia conexión a la base de datos.
 * Por eso no puede ir dentro de una transacción de prueba: los datos se guardan de verdad
 * (en la base de pruebas) y se borran al terminar.
 */
uses(TestCase::class, DatabaseTruncation::class);

it('dos ventas simultáneas no dejan el stock en negativo', function () {
    $category = Category::create(['name' => 'Prueba']);
    $product = Product::factory()->for($category)->create(['stock' => 5, 'cost' => 500, 'price' => 1000]);

    $db = config('database.connections.pgsql');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => $db['host'],
        'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => $db['database'],
        'DB_USERNAME' => $db['username'],
        'DB_PASSWORD' => $db['password'],
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
    ];

    // Todos los procesos arrancan la venta en el mismo instante (dentro de 1,5 segundos).
    $startAt = (string) (int) ((microtime(true) + 1.5) * 1_000_000);

    $processes = [];
    foreach (range(1, 4) as $i) {
        $p = new Process([PHP_BINARY, base_path('tests/Concurrency/sell.php'), (string) $product->id, '3', $startAt], base_path(), $env);
        $p->start();
        $processes[] = $p;
    }

    $outputs = [];
    foreach ($processes as $p) {
        $p->wait();
        expect($p->getErrorOutput())->toBe('');
        $outputs[] = trim($p->getOutput());
    }

    // Con 5 unidades y 4 personas comprando 3 cada una, solo una puede lograrlo.
    expect(array_count_values($outputs)['ok'] ?? 0)->toBe(1)
        ->and($product->fresh()->stock)->toBe(2)
        ->and(Sale::count())->toBe(1);

    foreach ($outputs as $out) {
        expect($out)->toBeIn(['ok', 'Solo quedan 2 unidades de «'.$product->name.'».']);
    }
});
