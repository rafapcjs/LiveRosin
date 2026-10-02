@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
    <div class="page-head">
        <h1>Productos</h1>
        <a href="{{ route('admin.productos.create') }}" class="btn btn-primary">+ Nuevo producto</a>
    </div>

    <form method="GET" action="{{ route('admin.productos.index') }}" class="card filters" role="search">
        <div class="field">
            <label for="q">Buscar por nombre</label>
            <input id="q" name="q" type="search" class="input" value="{{ $search }}" placeholder="Ej: aguardiente">
        </div>
        <div class="field">
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria" class="input">
                <option value="">Todas</option>
                @foreach ($categories as $id => $label)
                    <option value="{{ $id }}" @selected($categoryId === $id)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field filters-check">
            <label class="check">
                <input type="checkbox" name="stock" value="bajo" @checked($lowOnly)>
                Solo stock bajo (agotados o {{ $threshold }} o menos)
            </label>
        </div>
        <div class="actions">
            <button type="submit" class="btn">Buscar</button>
            @if ($search !== '' || $categoryId || $lowOnly)
                <a href="{{ route('admin.productos.index') }}" class="btn btn-ghost">Quitar filtros</a>
            @endif
        </div>
    </form>

    @if ($products->isEmpty())
        <div class="card empty">
            @if ($lowOnly && $search === '' && ! $categoryId)
                ¡Todo bien! No hay productos agotados ni con pocas unidades.
            @elseif ($search !== '' || $categoryId || $lowOnly)
                No hay productos que coincidan con la búsqueda.
            @else
                Todavía no tienes productos. Crea el primero con el botón «Nuevo producto».
            @endif
        </div>
    @else
        <table class="rtable">
            <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Costo</th>
                <th class="num">Precio</th>
                <th class="num">Ganancia / u</th>
                <th class="num">Margen</th>
                <th class="num">Recargo</th>
                <th class="num">Stock</th>
                <th class="num">Ganancia del stock</th>
                <th>Catálogo</th>
                <th><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($products as $product)
                @php $neg = $product->unitProfit() < 0; @endphp
                <tr>
                    <td class="cell-main">
                        <div class="prod-cell">
                            @if ($product->photo_path)
                                <img src="{{ $product->thumbUrl() }}" alt="" class="thumb" loading="lazy">
                            @else
                                <div class="thumb thumb-empty">Sin foto</div>
                            @endif
                            <div>
                                <strong>{{ $product->name }}</strong><br>
                                <span class="muted small">{{ $product->category?->name }}</span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Costo" class="num nowrap">{{ cop($product->cost) }}</td>
                    <td data-label="Precio" class="num nowrap">{{ cop($product->price) }}</td>
                    <td data-label="Ganancia por unidad" @class(['num nowrap', 'text-danger' => $neg])>{{ cop($product->unitProfit()) }}</td>
                    <td data-label="Margen" @class(['num nowrap', 'text-danger' => $neg])>{{ pct($product->marginPercent()) }}</td>
                    <td data-label="Recargo" @class(['num nowrap', 'text-danger' => $neg])>{{ pct($product->markupPercent()) }}</td>
                    <td data-label="Stock" class="num">
                        @if ($product->stock === 0)
                            <span class="badge badge-danger">Agotado</span>
                        @elseif ($product->stock <= $threshold)
                            {{ num($product->stock) }} <span class="badge badge-warning">Pocas unidades</span>
                        @else
                            {{ num($product->stock) }}
                        @endif
                    </td>
                    <td data-label="Ganancia del stock" @class(['num nowrap', 'text-danger' => $neg])>{{ cop($product->stockProfit()) }}</td>
                    <td data-label="Catálogo">
                        @if ($product->is_visible)
                            <span class="badge badge-success">Visible</span>
                        @else
                            <span class="badge">Oculto</span>
                        @endif
                    </td>
                    <td class="cell-actions">
                        <div class="actions">
                            @if ($product->stock > 0)
                                <a href="{{ route('admin.ventas.create', ['producto' => $product->id]) }}" class="btn btn-sm btn-primary">Vender</a>
                            @endif
                            <a href="{{ route('admin.compras.create', ['producto' => $product->id]) }}" class="btn btn-sm">+ Stock</a>
                            <a href="{{ route('admin.productos.edit', $product) }}" class="btn btn-sm">Editar</a>
                            <form method="POST" action="{{ route('admin.productos.destroy', $product) }}"
                                  data-confirm="¿Seguro que quieres eliminar «{{ $product->name }}»?"
                                  data-confirm-ok="Sí, eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @include('partials.pager', ['paginator' => $products])
    @endif
@endsection
