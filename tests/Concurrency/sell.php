<?php

/*
 * Ayudante de la prueba de ventas simultáneas: se ejecuta como un proceso aparte,
 * espera hasta el instante acordado y trata de vender. Imprime "ok" o el mensaje de error.
 *
 * Uso: php tests/Concurrency/sell.php {product_id} {cantidad} {inicio_en_microsegundos}
 */

use App\Services\SaleException;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $productId, $qty, $startAt] = $argv;

$wait = (int) $startAt - (int) (microtime(true) * 1_000_000);
if ($wait > 0) {
    usleep($wait);
}

try {
    app(SaleService::class)->register((int) $productId, (int) $qty, 1000);
    echo 'ok';
} catch (SaleException $e) {
    echo $e->getMessage();
}
