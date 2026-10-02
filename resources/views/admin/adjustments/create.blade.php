@extends('layouts.admin')

@section('title', 'Ajustar inventario')

@section('content')
    <div class="page-head">
        <h1>Ajustar inventario</h1>
        <a href="{{ route('admin.productos.edit', $product) }}" class="btn btn-ghost">« Volver</a>
    </div>

    <div class="card">
        <strong>{{ $product->name }}</strong><br>
        <span class="muted">En stock: {{ num($product->stock) }} · Costo promedio: {{ cop($product->cost) }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    @php $type = old('type', 'baja'); @endphp

    <form method="POST" action="{{ route('admin.ajustes-inventario.store', $product) }}" novalidate data-adjust-form>
        @csrf

        <div class="card">
            <span class="label">¿Qué quieres hacer?</span>
            <label class="check"><input type="radio" name="type" value="baja" @checked($type === 'baja')> Sacar unidades (daño, pérdida, consumo…)</label>
            <label class="check"><input type="radio" name="type" value="alta" @checked($type === 'alta')> Agregar unidades sin compra (conteo, regalo…)</label>
            @error('type') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="card">
            <div class="field">
                <label for="quantity">Cantidad</label>
                <input id="quantity" name="quantity" type="text" inputmode="numeric" autocomplete="off"
                       class="input @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}">
                @error('quantity') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field" data-adjust="baja" @if ($type !== 'baja') hidden @endif>
                <label for="reason-out">Motivo</label>
                <select id="reason-out" name="reason" class="input @error('reason') is-invalid @enderror" @disabled($type !== 'baja')>
                    @foreach (\App\Models\InventoryAdjustment::REASONS_OUT as $key => $label)
                        <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="hint">Las unidades salen de los lotes más antiguos y su costo se registra como pérdida.</div>
            </div>

            <div data-adjust="alta" @if ($type !== 'alta') hidden @endif>
                <div class="field">
                    <label for="reason-in">Motivo</label>
                    <select id="reason-in" name="reason" class="input @error('reason') is-invalid @enderror" @disabled($type !== 'alta')>
                        @foreach (\App\Models\InventoryAdjustment::REASONS_IN as $key => $label)
                            <option value="{{ $key }}" @selected(old('reason') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="unit_cost">Costo por unidad</label>
                    <div class="input-money">
                        <input id="unit_cost" name="unit_cost" type="text" inputmode="numeric" autocomplete="off"
                               class="input @error('unit_cost') is-invalid @enderror"
                               value="{{ old('unit_cost', $product->cost) }}" @disabled($type !== 'alta')>
                    </div>
                    <div class="hint">Pon 0 si no te costó nada (regalo o bonificación).</div>
                    @error('unit_cost') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            @error('reason') <div class="error">{{ $message }}</div> @enderror

            <div class="field">
                <label for="note">Nota <span class="muted small">(opcional)</span></label>
                <input id="note" name="note" type="text" maxlength="200" class="input" value="{{ old('note') }}"
                       placeholder="Ej: se rompió una botella al descargar">
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.productos.edit', $product) }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar ajuste</button>
        </div>
    </form>
@endsection
