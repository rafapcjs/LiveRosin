@extends('layouts.admin')

@section('title', 'Registrar compra')

@section('content')
    <div class="page-head">
        <h1>Registrar compra</h1>
        <a href="{{ route('admin.compras.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">Revisa los campos marcados en rojo.</div>
    @endif

    @if ($products->isEmpty())
        <div class="card empty">
            Primero crea el producto en <a href="{{ route('admin.productos.create') }}">Productos</a>.
        </div>
    @else
    <form method="POST" action="{{ route('admin.compras.store') }}" novalidate data-purchase-form>
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
                        <option value="{{ $p->id }}" data-cost="{{ $p->cost }}" data-stock="{{ $p->stock }}"
                                @selected((string) old('product_id', $selected) === (string) $p->id)>
                            {{ $p->name }} — {{ $p->stock === 0 ? 'agotado' : num($p->stock).' en stock' }}
                        </option>
                    @endforeach
                </select>
                <div class="hint" data-stock-info></div>
                @error('product_id') <div class="error">{{ $message }}</div> @enderror
                <div class="hint">¿Es un producto nuevo? <a href="{{ route('admin.productos.create') }}">Créalo primero aquí</a>.</div>
            </div>
        </div>

        <div class="card">
            <div class="grid-2">
                <div class="field">
                    <label for="quantity">Cantidad que compraste</label>
                    <input id="quantity" name="quantity" type="text" inputmode="numeric" autocomplete="off" required
                           class="input @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" placeholder="Ej: 12">
                    @error('quantity') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="unit_cost">Costo por unidad</label>
                    <div class="input-money">
                        <input id="unit_cost" name="unit_cost" type="text" inputmode="numeric" autocomplete="off" required
                               class="input @error('unit_cost') is-invalid @enderror" value="{{ old('unit_cost') }}">
                    </div>
                    <div class="hint">Viene con el costo actual. Cámbialo si esta vez pagaste otro valor.</div>
                    @error('unit_cost') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="calc-box" aria-live="polite">
                <div class="calc-grid calc-grid-3">
                    <div class="calc-item"><span class="k">Total de la compra</span><span class="v" data-purchase-total>—</span></div>
                    <div class="calc-item"><span class="k">Stock después</span><span class="v" data-stock-after>—</span></div>
                    <div class="calc-item"><span class="k">Costo promedio después</span><span class="v" data-avg-after>—</span></div>
                </div>
                <p class="small muted calc-note">
                    Estas unidades se guardan como un lote con su propio costo. Al vender, primero salen las unidades
                    más antiguas, así la ganancia de cada venta se calcula con el costo real.
                </p>
            </div>

            <div class="field">
                <label for="supplier">Proveedor <span class="muted small">(opcional)</span></label>
                <input id="supplier" name="supplier" type="text" maxlength="120" list="suppliers-list"
                       class="input @error('supplier') is-invalid @enderror" value="{{ old('supplier') }}" placeholder="Ej: Distribuidora El Paisa">
                <datalist id="suppliers-list">
                    @foreach ($suppliers as $s)
                        <option value="{{ $s }}">
                    @endforeach
                </datalist>
                @error('supplier') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="purchased_at">Fecha y hora de la compra</label>
                <input id="purchased_at" name="purchased_at" type="datetime-local" class="input @error('purchased_at') is-invalid @enderror"
                       value="{{ old('purchased_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}">
                <div class="hint">Déjala así si la compra es de ahora. Cámbiala si estás registrando una compra anterior.</div>
                @error('purchased_at') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.compras.index') }}" class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary">Registrar compra</button>
        </div>
    </form>
    @endif
@endsection
