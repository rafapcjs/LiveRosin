@extends('layouts.admin')

@section('title', 'Ajustes')

@section('content')
    <div class="page-head">
        <h1>Ajustes</h1>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    @if (! $settings->whatsapp)
        <div class="alert alert-warning">⚠ Aún no has puesto tu número de WhatsApp: tus clientes no pueden hacer pedidos desde el catálogo.</div>
    @endif

    <a href="{{ route('admin.administradores.index') }}" class="card settings-link">
        <span>
            <strong>👥 Administradores</strong><br>
            <span class="muted small">Crea administradores, cambia contraseñas o elimina accesos.</span>
        </span>
        <span aria-hidden="true">›</span>
    </a>

    <a href="{{ route('admin.seguridad') }}" class="card settings-link">
        <span>
            <strong>🔒 Seguridad</strong><br>
            <span class="muted small">Mira los intentos de entrar al panel (correctos y fallidos).</span>
        </span>
        @if ($securityAlertCount > 0)
            <span class="badge badge-danger">{{ $securityAlertCount }} nuevos</span>
        @else
            <span aria-hidden="true">›</span>
        @endif
    </a>

    <form method="POST" action="{{ route('admin.ajustes.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="card">
            <h2>Tu tienda</h2>
            <p class="muted small">Esto es lo que ven tus clientes en la parte de arriba del catálogo.</p>

            <div class="field">
                <label for="store_name">Nombre de la tienda</label>
                <input id="store_name" name="store_name" type="text" maxlength="80"
                       class="input @error('store_name') is-invalid @enderror"
                       value="{{ old('store_name', $settings->store_name) }}">
                @error('store_name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="tagline">Frase corta <span class="muted small">(opcional)</span></label>
                <input id="tagline" name="tagline" type="text" maxlength="140"
                       class="input @error('tagline') is-invalid @enderror"
                       value="{{ old('tagline', $settings->tagline) }}" placeholder="Ej: Licores y vapers a domicilio en Medellín">
                @error('tagline') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="whatsapp">Número de WhatsApp para pedidos</label>
                <input id="whatsapp" name="whatsapp" type="tel" inputmode="numeric" autocomplete="off"
                       class="input @error('whatsapp') is-invalid @enderror"
                       value="{{ old('whatsapp', $settings->whatsapp ? substr($settings->whatsapp, 2) : '') }}" placeholder="Ej: 300 123 4567">
                <div class="hint">Celular colombiano de 10 dígitos (empieza por 3). No hace falta escribir el +57.</div>
                @error('whatsapp') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card">
            <h2>Alerta de stock</h2>
            <div class="field">
                <label for="low_stock_threshold">Avisarme cuando queden estas unidades o menos</label>
                <input id="low_stock_threshold" name="low_stock_threshold" type="text" inputmode="numeric" autocomplete="off"
                       class="input @error('low_stock_threshold') is-invalid @enderror"
                       value="{{ old('low_stock_threshold', $settings->low_stock_threshold) }}">
                <div class="hint">
                    Ejemplo: con 3, un producto con 3, 2 o 1 unidades sale como «Pocas unidades», y con 0 como «Agotado».
                    En el catálogo se mostrará como «Últimas unidades».
                </div>
                @error('low_stock_threshold') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
@endsection
