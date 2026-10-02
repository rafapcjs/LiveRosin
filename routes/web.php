<?php

use App\Http\Controllers\Admin\AdjustmentController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AgeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\AgeGate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Catálogo público (sin inicio de sesión, con control de edad)
|--------------------------------------------------------------------------
*/
Route::middleware(['throttle:publico', AgeGate::class])->group(function () {
    Route::get('/', CatalogController::class)->name('home');
});

Route::post('/edad', [AgeController::class, 'confirm'])->middleware('throttle:publico')->name('age.confirm');
Route::get('/edad/no', [AgeController::class, 'denied'])->middleware('throttle:publico')->name('age.denied');

/*
|--------------------------------------------------------------------------
| Inicio de sesión (sin registro público)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/admin/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/entrar', [LoginController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

/*
|--------------------------------------------------------------------------
| Panel privado: TODO lo que está aquí exige sesión iniciada.
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', AdminIdleTimeout::class, 'throttle:panel'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.ventas.index'))->name('home');

    Route::post('/salir', [LoginController::class, 'logout'])->name('logout');

    Route::resource('productos', ProductController::class)
        ->except('show')
        ->parameters(['productos' => 'product']);

    Route::get('/productos/{product}/ajustar', [AdjustmentController::class, 'create'])->name('ajustes-inventario.create');
    Route::post('/productos/{product}/ajustar', [AdjustmentController::class, 'store'])->name('ajustes-inventario.store');
    Route::post('/ajustes-inventario/{adjustment}/anular', [AdjustmentController::class, 'void'])->name('ajustes-inventario.void');

    Route::resource('categorias', CategoryController::class)
        ->except('show')
        ->parameters(['categorias' => 'category']);

    Route::get('/ventas', [SaleController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/crear', [SaleController::class, 'create'])->name('ventas.create');
    Route::post('/ventas', [SaleController::class, 'store'])->name('ventas.store');
    Route::get('/ventas/excel', [SaleController::class, 'export'])->name('ventas.export');
    Route::post('/ventas/{sale}/anular', [SaleController::class, 'void'])->name('ventas.void');

    Route::get('/compras', [PurchaseController::class, 'index'])->name('compras.index');
    Route::get('/compras/crear', [PurchaseController::class, 'create'])->name('compras.create');
    Route::post('/compras', [PurchaseController::class, 'store'])->name('compras.store');
    Route::get('/compras/excel', [PurchaseController::class, 'export'])->name('compras.export');
    Route::post('/compras/{purchase}/anular', [PurchaseController::class, 'void'])->name('compras.void');

    Route::get('/ajustes', [SettingsController::class, 'edit'])->name('ajustes.edit');
    Route::put('/ajustes', [SettingsController::class, 'update'])->name('ajustes.update');

    Route::get('/seguridad', SecurityController::class)->name('seguridad');

    // Administradores del panel
    Route::get('/administradores', [AdminUserController::class, 'index'])->name('administradores.index');
    Route::get('/administradores/crear', [AdminUserController::class, 'create'])->name('administradores.create');
    Route::post('/administradores', [AdminUserController::class, 'store'])->name('administradores.store');
    Route::get('/administradores/{user}/clave', [AdminUserController::class, 'editPassword'])->name('administradores.password');
    Route::put('/administradores/{user}/clave', [AdminUserController::class, 'updatePassword'])->name('administradores.password.update');
    Route::patch('/administradores/{user}/estado', [AdminUserController::class, 'toggle'])->name('administradores.toggle');
    Route::delete('/administradores/{user}', [AdminUserController::class, 'destroy'])->name('administradores.destroy');

    // "Seguir conectado": el aviso de inactividad llama aquí para alargar la sesión.
    Route::post('/sigo-aqui', fn () => response()->noContent())->name('keepalive');
});
