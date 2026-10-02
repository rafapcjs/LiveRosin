<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Route;

it('protege con inicio de sesión todas las rutas del panel', function () {
    $publicAdminRoutes = ['login', 'login.attempt'];

    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'admin'))
        ->reject(fn ($r) => in_array($r->getName(), $publicAdminRoutes, true));

    expect($adminRoutes)->not->toBeEmpty();

    foreach ($adminRoutes as $route) {
        expect($route->gatherMiddleware())->toContain('auth');
    }
});

it('envía a la pantalla de inicio de sesión a quien no ha entrado', function () {
    $product = Product::factory()->create();

    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/admin/productos')->assertRedirect(route('login'));
    $this->get('/admin/productos/crear')->assertRedirect(route('login'));
    $this->get("/admin/productos/{$product->id}/editar")->assertRedirect(route('login'));
    $this->post('/admin/productos', [])->assertRedirect(route('login'));
    $this->delete("/admin/productos/{$product->id}")->assertRedirect(route('login'));

    expect(Product::count())->toBe(1);
});

it('permite entrar con el correo y la contraseña correctos', function () {
    $user = User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123'])
        ->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($user);
});

it('rechaza una contraseña incorrecta con un mensaje en español', function () {
    User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    $this->from(route('login'))
        ->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'otra'])
        ->assertSessionHasErrors(['email' => 'El usuario o la contraseña no son correctos.']);

    $this->assertGuest();
});

it('bloquea después de varios intentos fallidos', function () {
    User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'mala']);
    }

    $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Demasiados intentos');
    $this->assertGuest();
});

it('no tiene registro público ni recuperación de contraseña', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
});

it('cierra la sesión', function () {
    $this->actingAs(admin())->post(route('admin.logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('crea el administrador con los datos del .env', function () {
    config(['tienda.admin_email' => 'jefe@tienda.test', 'tienda.admin_password' => 'UnaClaveLarga1']);

    $this->artisan('tienda:crear-admin')->assertSuccessful();

    $this->post(route('login.attempt'), ['email' => 'jefe@tienda.test', 'password' => 'UnaClaveLarga1']);
    $this->assertAuthenticated();
});

it('permite entrar con nombre de usuario (sin correo)', function () {
    $user = User::factory()->create(['username' => 'dealer1', 'email' => null, 'password' => 'UnaClaveLarga@1']);

    $this->post(route('login.attempt'), ['email' => '  Dealer1 ', 'password' => 'UnaClaveLarga@1'])
        ->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($user);
});

it('rechaza un usuario con contraseña equivocada', function () {
    User::factory()->create(['username' => 'dealer2', 'email' => null, 'password' => 'UnaClaveLarga@1']);

    $this->post(route('login.attempt'), ['email' => 'dealer2', 'password' => 'otra'])
        ->assertSessionHasErrors(['email' => 'El usuario o la contraseña no son correctos.']);

    $this->assertGuest();
});

it('crea un administrador con usuario desde la terminal', function () {
    $this->artisan('tienda:crear-usuario', ['usuario' => 'dealer3'])
        ->expectsQuestion('Contraseña para dealer3 (no se ve mientras escribes)', 'OtraClave2026!')
        ->expectsQuestion('Escríbela otra vez', 'OtraClave2026!')
        ->assertSuccessful();

    $this->post(route('login.attempt'), ['email' => 'dealer3', 'password' => 'OtraClave2026!']);
    $this->assertAuthenticated();
});

it('no crea usuarios repetidos ni con contraseñas cortas', function () {
    User::factory()->create(['username' => 'dealer1']);

    $this->artisan('tienda:crear-usuario', ['usuario' => 'dealer1'])
        ->expectsOutput('Ya existe un administrador con ese usuario.')
        ->assertFailed();

    $this->artisan('tienda:crear-usuario', ['usuario' => 'dealer9'])
        ->expectsQuestion('Contraseña para dealer9 (no se ve mientras escribes)', 'corta')
        ->assertFailed();

    expect(User::where('username', 'dealer9')->exists())->toBeFalse();
});

it('cambia la contraseña buscando por usuario', function () {
    User::factory()->create(['username' => 'dealer1', 'email' => null]);
    User::factory()->create(['username' => 'dealer2', 'email' => null]);

    $this->artisan('tienda:cambiar-clave', ['usuario' => 'dealer2'])
        ->expectsQuestion('Nueva contraseña (no se ve mientras escribes)', 'NuevaClave2026!')
        ->expectsQuestion('Escríbela otra vez', 'NuevaClave2026!')
        ->assertSuccessful();

    $this->post(route('login.attempt'), ['email' => 'dealer2', 'password' => 'NuevaClave2026!']);
    $this->assertAuthenticated();
});
