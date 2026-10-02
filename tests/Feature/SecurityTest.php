<?php

use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\AgeGate;
use App\Models\SecurityEvent;
use App\Models\User;

/* ---------------- Cierre de sesión por inactividad ---------------- */

it('cierra la sesión del panel después de 30 minutos sin usarlo', function () {
    config(['tienda.admin_idle_minutes' => 30]);

    $this->actingAs(admin())
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(31)->timestamp])
        ->get(route('admin.ventas.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('warning', 'Tu sesión se cerró por inactividad. Vuelve a entrar.');

    $this->assertGuest();
});

it('no cierra la sesión si se usó hace poco', function () {
    config(['tienda.admin_idle_minutes' => 30]);

    $this->actingAs(admin())
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(10)->timestamp])
        ->get(route('admin.ventas.index'))
        ->assertOk();

    $this->assertAuthenticated();
});

it('"Seguir conectado" alarga la sesión', function () {
    $this->actingAs(admin())
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(29)->timestamp])
        ->post(route('admin.keepalive'))
        ->assertNoContent();

    expect(session(AdminIdleTimeout::SESSION_KEY))->toBeGreaterThanOrEqual(now()->subSeconds(5)->timestamp);
});

it('el panel muestra el aviso de inactividad y ya no ofrece "Recordarme"', function () {
    $this->actingAs(admin())->get(route('admin.ventas.index'))
        ->assertSee('data-idle-minutes="30"', false)
        ->assertSee('Seguir conectado');

    $this->get(route('login'))->assertDontSee('name="remember"', false);
});

/* ---------------- Registro y avisos de intentos fallidos ---------------- */

it('registra los intentos fallidos y muestra el aviso en el panel', function () {
    $user = User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'mala1']);
    $this->post(route('login.attempt'), ['email' => 'otro@x.com', 'password' => 'mala2']);

    expect(SecurityEvent::where('type', SecurityEvent::LOGIN_FAILED)->count())->toBe(2);

    $this->actingAs($user)->get(route('admin.ventas.index'))
        ->assertSee('2 intentos fallidos')
        ->assertSee(route('admin.seguridad'), false);
});

it('al revisar la página de Seguridad el aviso desaparece', function () {
    $user = User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);
    $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'mala']);

    $this->actingAs($user)->get(route('admin.seguridad'))
        ->assertOk()
        ->assertSee('Intento fallido')
        ->assertSee('dueno@tienda.test');

    $this->actingAs($user->fresh())->get(route('admin.ventas.index'))->assertDontSee('intentos fallidos');
});

it('una entrada correcta queda registrada con fecha e IP', function () {
    $user = User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123'])
        ->assertRedirect(route('admin.home'));

    expect(SecurityEvent::where('type', SecurityEvent::LOGIN_OK)->count())->toBe(1)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_login_ip)->toBe('127.0.0.1');
});

it('tras 5 intentos fallidos bloquea 5 minutos y envía un solo correo de aviso', function () {
    User::factory()->create(['email' => 'dueno@tienda.test', 'password' => 'ClaveSegura123']);

    for ($i = 0; $i < 7; $i++) {
        $this->post(route('login.attempt'), ['email' => 'dueno@tienda.test', 'password' => 'mala']);
    }

    expect(session('errors')->first('email'))->toContain('Demasiados intentos. Espera 5 minutos')
        ->and(SecurityEvent::where('type', SecurityEvent::LOGIN_LOCKED)->count())->toBe(2);

    $sent = app('mailer')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getOriginalMessage()->getSubject())->toContain('Intentos fallidos')
        ->and($sent[0]->getOriginalMessage()->getTextBody())->toContain('dueno@tienda.test');
});

it('borra solos los registros de seguridad de más de 90 días', function () {
    SecurityEvent::create(['type' => SecurityEvent::LOGIN_FAILED, 'ip' => '1.1.1.1'])->forceFill(['created_at' => now()->subDays(91)])->save();
    SecurityEvent::create(['type' => SecurityEvent::LOGIN_FAILED, 'ip' => '2.2.2.2']);

    $this->artisan('model:prune', ['--model' => [SecurityEvent::class]])->assertSuccessful();

    expect(SecurityEvent::pluck('ip')->all())->toBe(['2.2.2.2']);
});

/* ---------------- Límite de visitas por minuto ---------------- */

it('limita las visitas por minuto al catálogo', function () {
    config(['tienda.rate_public_per_minute' => 3]);

    for ($i = 0; $i < 3; $i++) {
        $this->withCookie(AgeGate::COOKIE, '1')->get('/')->assertOk();
    }

    $this->withCookie(AgeGate::COOKIE, '1')->get('/')
        ->assertStatus(429)
        ->assertSee('Demasiadas solicitudes');
});

it('limita las visitas por minuto al panel', function () {
    config(['tienda.rate_admin_per_minute' => 2]);
    $user = admin();

    $this->actingAs($user)->get(route('admin.ajustes.edit'))->assertOk();
    $this->actingAs($user)->get(route('admin.ajustes.edit'))->assertOk();
    $this->actingAs($user)->get(route('admin.ajustes.edit'))->assertStatus(429);
});
