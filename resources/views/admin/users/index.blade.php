@extends('layouts.admin')

@section('title', 'Administradores')

@section('content')
    <div class="page-head">
        <h1>Administradores</h1>
        <a href="{{ route('admin.administradores.create') }}" class="btn btn-primary">+ Nuevo administrador</a>
    </div>

    <p><a href="{{ route('admin.ajustes.edit') }}">« Volver a Ajustes</a></p>
    <p class="muted">
        Personas que pueden entrar al panel. Para crear, cambiar contraseñas, habilitar, deshabilitar o eliminar
        se pide tu contraseña actual.
    </p>

    @if ($errors->has('current_password'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('current_password') }}</div>
    @endif

    <table class="rtable">
        <thead>
        <tr>
            <th>Usuario</th>
            <th>Estado</th>
            <th>Correo</th>
            <th>Última entrada</th>
            <th><span class="visually-hidden">Acciones</span></th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            @php $isMe = $user->is($me); @endphp
            <tr @class(['is-disabled-row' => ! $user->is_active])>
                <td class="cell-main">
                    <div>
                        <strong>{{ $user->displayName() }}</strong>
                        @if ($isMe) <span class="badge badge-success">Tú</span> @endif
                    </div>
                </td>
                <td data-label="Estado">
                    @if ($user->is_active)
                        <span class="badge badge-success">Habilitado</span>
                    @else
                        <span class="badge badge-danger">Deshabilitado</span>
                    @endif
                </td>
                <td data-label="Correo">{{ $user->email ?? '—' }}</td>
                <td data-label="Última entrada" class="nowrap">
                    {{ $user->last_login_at ? $user->last_login_at->translatedFormat('j M Y, h:i a') : 'Nunca' }}
                </td>
                <td class="cell-actions">
                    <div class="actions">
                        <a href="{{ route('admin.administradores.password', $user) }}" class="btn btn-sm">
                            {{ $isMe ? 'Cambiar mi contraseña' : 'Cambiar contraseña' }}
                        </a>

                        @unless ($isMe)
                            {{-- Habilitar / deshabilitar --}}
                            <form method="POST" action="{{ route('admin.administradores.toggle', $user) }}">
                                @csrf
                                @method('PATCH')
                                <details class="delete-admin">
                                    <summary @class(['btn btn-sm', 'btn-primary' => ! $user->is_active])>
                                        {{ $user->is_active ? 'Deshabilitar' : 'Habilitar' }}
                                    </summary>
                                    <div class="delete-admin-box">
                                        <p class="small">
                                            Para {{ $user->is_active ? 'deshabilitar' : 'habilitar' }} a
                                            <strong>{{ $user->displayName() }}</strong>, escribe <strong>tu</strong> contraseña:
                                        </p>
                                        <input type="password" name="current_password" class="input" autocomplete="current-password" required
                                               aria-label="Tu contraseña actual">
                                        <button type="submit" @class(['btn btn-sm', 'btn-danger' => $user->is_active, 'btn-primary' => ! $user->is_active])>
                                            Sí, {{ $user->is_active ? 'deshabilitar' : 'habilitar' }}
                                        </button>
                                    </div>
                                </details>
                            </form>

                            {{-- Eliminar --}}
                            <form method="POST" action="{{ route('admin.administradores.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <details class="delete-admin">
                                    <summary class="btn btn-sm btn-danger">Eliminar</summary>
                                    <div class="delete-admin-box">
                                        <p class="small">Para eliminar a <strong>{{ $user->displayName() }}</strong>, escribe <strong>tu</strong> contraseña:</p>
                                        <input type="password" name="current_password" class="input" autocomplete="current-password" required
                                               aria-label="Tu contraseña actual">
                                        <button type="submit" class="btn btn-sm btn-danger">Sí, eliminar</button>
                                    </div>
                                </details>
                            </form>
                        @endunless
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
