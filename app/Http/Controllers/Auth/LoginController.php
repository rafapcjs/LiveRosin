<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminIdleTimeout;
use App\Models\SecurityEvent;
use App\Services\SecurityAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión del panel. No hay registro público.
 * Cada intento queda en el registro de seguridad (Ajustes → Seguridad).
 */
class LoginController extends Controller
{
    /** Intentos fallidos permitidos antes de bloquear. */
    private const MAX_ATTEMPTS = 5;

    /** Tiempo de bloqueo después de muchos intentos fallidos (segundos). */
    private const LOCK_SECONDS = 300;

    public function __construct(private SecurityAlertService $alerts) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        // Se entra con usuario (ej: "dealer1") o con correo. Los espacios sobrantes no cuentan.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.required' => 'Escribe tu usuario o correo.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $field = str_contains($credentials['email'], '@') ? 'email' : 'username';
        $key = $credentials['email'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            SecurityEvent::record(SecurityEvent::LOGIN_LOCKED, $request, $credentials['email']);
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera '.$this->wait(RateLimiter::availableIn($key)).' e inténtalo de nuevo.',
            ]);
        }

        $login = [$field => $credentials['email'], 'password' => $credentials['password']];

        if (! Auth::validate($login)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            SecurityEvent::record(SecurityEvent::LOGIN_FAILED, $request, $credentials['email']);
            Log::warning('Intento fallido de inicio de sesión', ['email' => $credentials['email'], 'ip' => $request->ip()]);

            if (RateLimiter::attempts($key) >= self::MAX_ATTEMPTS) {
                $this->alerts->loginLocked($credentials['email'], $request->ip());
            }

            throw ValidationException::withMessages([
                'email' => 'El usuario o la contraseña no son correctos.',
            ]);
        }

        // Contraseña correcta, pero la cuenta puede estar deshabilitada por otro administrador.
        $user = Auth::getProvider()->retrieveByCredentials($login);
        if (! $user->is_active) {
            SecurityEvent::record(SecurityEvent::LOGIN_FAILED, $request, $credentials['email'], 'Intentó entrar con la cuenta deshabilitada');
            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está deshabilitada. Habla con otro administrador.',
            ]);
        }

        // Sin "recordarme": así el cierre por inactividad no se puede saltar.
        Auth::login($user);
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put(AdminIdleTimeout::SESSION_KEY, now()->timestamp);

        $user = $request->user();
        SecurityEvent::record(SecurityEvent::LOGIN_OK, $request, $user->displayName());
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->intended(route('admin.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Cerraste sesión correctamente.');
    }

    private function wait(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' segundos';
        }
        $minutes = (int) ceil($seconds / 60);

        return $minutes === 1 ? '1 minuto' : $minutes.' minutos';
    }
}
