<?php

use App\Models\SecurityEvent;
use App\Models\User;

function me(): User
{
    return User::factory()->create(['username' => 'dealer1', 'password' => 'MiClave2026']);
}

it('lista los administradores desde Ajustes', function () {
    $me = me();
    User::factory()->create(['username' => 'dealer2']);

    $this->actingAs($me)->get(route('admin.ajustes.edit'))->assertSee(route('admin.administradores.index'), false);

    $this->actingAs($me)->get(route('admin.administradores.index'))
        ->assertOk()
        ->assertSee('dealer1')
        ->assertSee('dealer2')
        ->assertSee('Tú');
});

it('crea un administrador pidiendo la contraseña actual', function () {
    $me = me();

    $this->actingAs($me)->post(route('admin.administradores.store'), [
        'username' => 'Dealer3', 'email' => '', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        'current_password' => 'MiClave2026',
    ])->assertRedirect(route('admin.administradores.index'))->assertSessionHas('success');

    $new = User::where('username', 'dealer3')->sole();
    expect($new->email)->toBeNull()->and($new->is_active)->toBeTrue()
        ->and(SecurityEvent::where('type', SecurityEvent::ADMIN_CREATED)->value('detail'))->toBe('dealer1 creó al administrador dealer3');
});

it('valida la contraseña nueva y la actual', function () {
    $me = me();

    $this->actingAs($me)->post(route('admin.administradores.store'), [
        'username' => 'dealer3', 'password' => 'solotexto', 'password_confirmation' => 'otra', 'current_password' => 'mala',
    ])->assertSessionHasErrors([
        'password' => 'Las dos contraseñas nuevas no coinciden.',
        'current_password' => 'Tu contraseña actual no es correcta.',
    ]);

    $this->actingAs($me)->post(route('admin.administradores.store'), [
        'username' => 'dealer3', 'password' => 'solotextolargo', 'password_confirmation' => 'solotextolargo', 'current_password' => 'MiClave2026',
    ])->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos un número.']);

    expect(User::where('username', 'dealer3')->exists())->toBeFalse();
});

it('cambia la contraseña de otro administrador y cierra sus sesiones', function () {
    $me = me();
    $other = User::factory()->create(['username' => 'dealer2', 'password' => 'Vieja2026clave']);
    DB::table('sessions')->insert(['id' => 'sesion-de-dealer2', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($me)->put(route('admin.administradores.password.update', $other), [
        'password' => 'Nueva2026clave', 'password_confirmation' => 'Nueva2026clave', 'current_password' => 'MiClave2026',
    ])->assertRedirect(route('admin.administradores.index'));

    expect(Hash::check('Nueva2026clave', $other->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'sesion-de-dealer2')->exists())->toBeFalse()
        ->and(SecurityEvent::where('type', SecurityEvent::PASSWORD_CHANGED)->value('detail'))->toBe('dealer1 cambió la contraseña de dealer2');
});

it('cambia mi propia contraseña', function () {
    $me = me();

    $this->actingAs($me)->put(route('admin.administradores.password.update', $me), [
        'password' => 'OtraMas2026', 'password_confirmation' => 'OtraMas2026', 'current_password' => 'MiClave2026',
    ])->assertSessionHas('success', 'Cambiaste tu contraseña. Úsala la próxima vez que entres.');

    expect(Hash::check('OtraMas2026', $me->fresh()->password))->toBeTrue();
});

it('deshabilita a un administrador: no puede entrar y lo saca si estaba dentro', function () {
    $me = me();
    $other = User::factory()->create(['username' => 'dealer2', 'password' => 'Clave2026dealer']);

    $this->actingAs($me)->patch(route('admin.administradores.toggle', $other), ['current_password' => 'MiClave2026'])
        ->assertSessionHas('success');
    expect($other->fresh()->is_active)->toBeFalse();

    // Si tenía el panel abierto, al siguiente clic sale.
    $this->actingAs($other->fresh())->get(route('admin.ventas.index'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Tu cuenta fue deshabilitada. Habla con otro administrador.');

    // Y no puede volver a entrar, aunque su contraseña sea correcta.
    auth()->logout();
    $this->post(route('login.attempt'), ['email' => 'dealer2', 'password' => 'Clave2026dealer'])
        ->assertSessionHasErrors(['email' => 'Tu cuenta está deshabilitada. Habla con otro administrador.']);
    $this->assertGuest();
});

it('vuelve a habilitar a un administrador', function () {
    $me = me();
    $other = User::factory()->create(['username' => 'dealer2', 'password' => 'Clave2026dealer', 'is_active' => false]);

    $this->actingAs($me)->patch(route('admin.administradores.toggle', $other), ['current_password' => 'MiClave2026']);
    expect($other->fresh()->is_active)->toBeTrue();

    auth()->logout();
    $this->post(route('login.attempt'), ['email' => 'dealer2', 'password' => 'Clave2026dealer'])->assertRedirect(route('admin.home'));
});

it('no deja deshabilitarse ni eliminarse a uno mismo, ni dejar el panel sin administradores habilitados', function () {
    $me = me();
    $off = User::factory()->create(['username' => 'dealer2', 'is_active' => false]);

    $this->actingAs($me)->patch(route('admin.administradores.toggle', $me), ['current_password' => 'MiClave2026'])
        ->assertSessionHas('error', 'No puedes deshabilitar tu propia cuenta.');
    $this->actingAs($me)->delete(route('admin.administradores.destroy', $me), ['current_password' => 'MiClave2026'])
        ->assertSessionHas('error');

    expect($me->fresh()->is_active)->toBeTrue()->and(User::count())->toBe(2);
});

it('elimina un administrador pidiendo la contraseña actual', function () {
    $me = me();
    $other = User::factory()->create(['username' => 'dealer2']);

    $this->actingAs($me)->delete(route('admin.administradores.destroy', $other), ['current_password' => 'mala'])
        ->assertSessionHasErrors('current_password');
    expect(User::find($other->id))->not->toBeNull();

    $this->actingAs($me)->delete(route('admin.administradores.destroy', $other), ['current_password' => 'MiClave2026'])
        ->assertSessionHas('success', 'Se eliminó el administrador «dealer2».');
    expect(User::find($other->id))->toBeNull();
});
