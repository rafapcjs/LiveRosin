@extends('layouts.admin')

@section('title', 'Seguridad')

@section('content')
    <div class="page-head">
        <h1>Seguridad</h1>
        <a href="{{ route('admin.ajustes.edit') }}" class="btn btn-ghost">« Ajustes</a>
    </div>

    <p class="muted">Aquí ves cada intento de entrar al panel. Si ves intentos que no fueron tuyos, cambia tu contraseña.</p>

    <div class="stats">
        <div class="stat">
            <span class="stat-label">Intentos fallidos (24 horas)</span>
            <span @class(['stat-value', 'text-danger' => $failures24h > 0])>{{ num($failures24h) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">Intentos fallidos (30 días)</span>
            <span @class(['stat-value', 'text-danger' => $failures30d > 0])>{{ num($failures30d) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">Tu última entrada</span>
            <span class="stat-sub">
                {{ $user->last_login_at ? $user->last_login_at->translatedFormat('j M Y, h:i a') : '—' }}
                @if ($user->last_login_ip) · IP {{ $user->last_login_ip }} @endif
            </span>
        </div>
    </div>

    <div class="card">
        <h2>¿Qué hago si veo intentos raros?</h2>
        <ol class="security-tips">
            <li>Cambia tu contraseña en el servidor con: <code>php artisan tienda:cambiar-clave</code></li>
            <li>Usa una contraseña larga (12 o más caracteres) que no uses en otra parte.</li>
            <li>Después de 5 intentos fallidos, esa persona queda bloqueada 5 minutos y te llega un correo de aviso.</li>
        </ol>
    </div>

    @if ($events->isEmpty())
        <div class="card empty">Todavía no hay intentos registrados.</div>
    @else
        <table class="rtable">
            <thead>
            <tr>
                <th>Qué pasó</th>
                <th>Fecha</th>
                <th>Usuario o correo usado</th>
                <th>IP</th>
                <th>Dispositivo</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($events as $event)
                @php $isNew = $event->type !== \App\Models\SecurityEvent::LOGIN_OK && (! $seenBefore || $event->created_at->gt($seenBefore)); @endphp
                <tr>
                    <td class="cell-main">
                        <div>
                            <span @class([
                                'badge',
                                'badge-success' => $event->type === \App\Models\SecurityEvent::LOGIN_OK,
                                'badge-warning' => $event->type === \App\Models\SecurityEvent::LOGIN_FAILED,
                                'badge-danger' => $event->type === \App\Models\SecurityEvent::LOGIN_LOCKED,
                            ])>{{ $event->typeLabel() }}</span>
                            @if ($isNew) <strong class="small text-danger">NUEVO</strong> @endif
                            @if ($event->detail)<br><span class="small muted">{{ $event->detail }}</span>@endif
                        </div>
                    </td>
                    <td data-label="Fecha" class="nowrap">{{ $event->created_at->translatedFormat('d/m/Y h:i a') }}</td>
                    <td data-label="Usuario o correo">{{ $event->email ?? '—' }}</td>
                    <td data-label="IP" class="nowrap">{{ $event->ip ?? '—' }}</td>
                    <td data-label="Dispositivo">{{ $event->deviceLabel() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @include('partials.pager', ['paginator' => $events])
    @endif
@endsection
