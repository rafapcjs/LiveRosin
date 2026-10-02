@extends('layouts.admin')

@section('title', 'Registrar venta')

@section('content')
    <div class="page-head">
        <h1>Registrar venta</h1>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    <form method="POST" action="{{ route('admin.ventas.store') }}" novalidate data-sale-form>
        @csrf

        <div class="card">
            <div class="field">
                <label for="product-search">Buscar producto</label>
                <input id="product-search" type="search" class="input" placeholder="Escribe parte del nombre…"
                       autocomplete="off" data-filter-select="product_id">
            </div>

            <div class="field">
                <label for="product_id">Producto</label>
                <select id="product_id" name="product_id" required class="input @error('product_id') is-invalid @enderror">
                    <option value="">Elige un producto…</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}"
                                data-price="{{ $p->price }}" data-cost="{{ $p->cost }}" data-stock="{{ $p->stock }}"
                                @selected((string) old('product_id', $selected) === (string) $p->id)
                                @disabled($p->stock === 0)>
                            {{ $p->name }} — {{ $p->stock === 0 ? 'AGOTADO' : num($p->stock).' disp.' }}
                        </option>
                    @endforeach
                </select>
                <div class="hint" data-stock-info></div>
                @error('product_id') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="card">
            <div class="grid-2">
                <div class="field">
                    <label for="quantity">Cantidad</label>
                    <input id="quantity" name="quantity" type="text" inputmode="numeric" autocomplete="off" required
                           class="input @error('quantity') is-invalid @enderror" value="{{ old('quantity', 1) }}">
                    <div class="error" data-qty-warning hidden></div>
                    @error('quantity') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="unit_price">Precio por unidad</label>
                    <div class="input-money">
                        <input id="unit_price" name="unit_price" type="text" inputmode="numeric" autocomplete="off" required
                               class="input @error('unit_price') is-invalid @enderror" value="{{ old('unit_price') }}">
                    </div>
                    <div class="hint">Viene con el precio normal. Cámbialo si hiciste descuento.</div>
                    @error('unit_price') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="alert alert-warning" data-below-cost hidden>
                Atención: este precio es menor que el costo. Esta venta deja pérdida.
            </div>

            <div class="calc-box" aria-live="polite">
                <div class="calc-grid calc-grid-2">
                    <div class="calc-item"><span class="k">Total de la venta</span><span class="v" data-sale-total>—</span></div>
                    <div class="calc-item"><span class="k">Ganancia estimada</span><span class="v" data-sale-profit>—</span></div>
                </div>
            </div>

            <div class="field">
                <label for="note">Nota <span class="muted small">(opcional)</span></label>
                <input id="note" name="note" type="text" maxlength="200" autocomplete="off"
                       class="input @error('note') is-invalid @enderror" value="{{ old('note') }}"
                       placeholder="Ej: descuento cliente frecuente, pagó por Nequi…">
                @error('note') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="sold_at">Fecha y hora de la venta</label>
                <input id="sold_at" name="sold_at" type="datetime-local" class="input @error('sold_at') is-invalid @enderror"
                       value="{{ old('sold_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}">
                <div class="hint">Déjala así si la venta es de ahora. Cámbiala si estás registrando una venta anterior.</div>
                @error('sold_at') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.ventas.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar venta</button>
        </div>
    </form>
@endsection
