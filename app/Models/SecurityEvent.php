<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Un evento de seguridad: intento de entrar al panel.
 * Se borran solos los de más de 90 días (php artisan model:prune, programado a diario).
 */
class SecurityEvent extends Model
{
    use Prunable;

    public const LOGIN_FAILED = 'login_failed';

    public const LOGIN_LOCKED = 'login_locked';

    public const LOGIN_OK = 'login_ok';

    public const ADMIN_CREATED = 'admin_created';

    public const ADMIN_DELETED = 'admin_deleted';

    public const PASSWORD_CHANGED = 'password_changed';

    public const ADMIN_DISABLED = 'admin_disabled';

    public const ADMIN_ENABLED = 'admin_enabled';

    public const UPDATED_AT = null;

    protected $fillable = ['type', 'email', 'detail', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(string $type, Request $request, ?string $email = null, ?string $detail = null): self
    {
        return static::create([
            'type' => $type,
            'email' => $email ? Str::limit(Str::lower($email), 250, '') : null,
            'detail' => $detail ? Str::limit($detail, 250, '') : null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /** Intentos fallidos o bloqueados. */
    public function scopeFailures(Builder $query): Builder
    {
        return $query->whereIn('type', [self::LOGIN_FAILED, self::LOGIN_LOCKED]);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::LOGIN_FAILED => 'Intento fallido',
            self::LOGIN_LOCKED => 'Bloqueado por muchos intentos',
            self::LOGIN_OK => 'Entrada correcta',
            self::ADMIN_CREATED => 'Administrador creado',
            self::ADMIN_DELETED => 'Administrador eliminado',
            self::PASSWORD_CHANGED => 'Contraseña cambiada',
            self::ADMIN_DISABLED => 'Administrador deshabilitado',
            self::ADMIN_ENABLED => 'Administrador habilitado',
            default => $this->type,
        };
    }

    /** Descripción corta del dispositivo a partir del navegador. */
    public function deviceLabel(): string
    {
        $ua = (string) $this->user_agent;

        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iPhone/iPad',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Otro',
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'desconocido',
            default => 'otro programa',
        };

        return "{$browser} en {$os}";
    }
}
