@extends('layouts.admin')

@section('title', 'Nuevo administrador')

@section('content')
    <div class="page-head">
        <h1>Nuevo administrador</h1>
        <a href="{{ route('admin.administradores.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    <form method="POST" action="{{ route('admin.administradores.store') }}" novalidate>
        @csrf

        <div class="card">
            <div class="field">
                <label for="username">Usuario para entrar</label>
                <input id="username" name="username" type="text" maxlength="50" autocomplete="off"
                       autocapitalize="none" spellcheck="false"
                       class="input @error('username') is-invalid @enderror" value="{{ old('username') }}" placeholder="Ej: dealer3">
                <div class="hint">Solo letras, números, guiones o guiones bajos. Sin espacios.</div>
                @error('username') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="email">Correo <span class="muted small">(opcional)</span></label>
                <input id="email" name="email" type="email" maxlength="255" autocomplete="off"
                       class="input @error('email') is-invalid @enderror" value="{{ old('email') }}">
                <div class="hint">Si lo pones, a ese correo le llegan los avisos de seguridad.</div>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            @include('admin.users._password-fields')
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.administradores.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Crear administrador</button>
        </div>
    </form>
@endsection
