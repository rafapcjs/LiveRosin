@extends('layouts.admin')

@section('title', 'Cambiar contraseña')

@section('content')
    <div class="page-head">
        <h1>{{ $isMe ? 'Cambiar mi contraseña' : 'Cambiar contraseña de '.$user->displayName() }}</h1>
        <a href="{{ route('admin.administradores.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    @unless ($isMe)
        <div class="alert alert-warning">
            Al guardar, <strong>{{ $user->displayName() }}</strong> tendrá que entrar con la contraseña nueva
            y sus sesiones abiertas en otros equipos se cerrarán.
        </div>
    @endunless

    <form method="POST" action="{{ route('admin.administradores.password.update', $user) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="card">
            @include('admin.users._password-fields')
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.administradores.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar contraseña</button>
        </div>
    </form>
@endsection
