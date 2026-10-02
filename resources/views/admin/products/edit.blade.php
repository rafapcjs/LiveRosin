@extends('layouts.admin')

@section('title', 'Editar producto')

@section('content')
    <div class="page-head">
        <h1>Editar producto</h1>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.productos.update', $product) }}" enctype="multipart/form-data" novalidate data-product-calc>
        @csrf
        @method('PUT')
        @include('admin.products._form')
    </form>

    {{-- Inventario por lotes --}}
    <section class="card" aria-labelledby="lots-title">
        <div class="page-head">
            <h2 id="lots-title">Inventario por lotes</h2>
            <div class="actions">
                <a href="{{ route('admin.compras.create', ['producto' => $product->id]) }}" class="btn btn-sm btn-primary">+ Registrar compra</a>
                <a href="{{ route('admin.ajustes-inventario.create', $product) }}" class="btn btn-sm">Ajustar inventario</a>
            </div>
        </div>

        <p class="muted small">
            Cada compra queda como un lote con su propio costo. Al vender salen primero las unidades más antiguas,
            así la ganancia de cada venta se calcula con el costo real.
        </p>

        @if ($lots->isEmpty())
            <p class="empty">No hay unidades en bodega.</p>
        @else
            <table class="rtable">
                <thead>
                <tr>
                    <th>Entró</th>
                    <th>Origen</th>
                    <th class="num">Costo unitario</th>
                    <th class="num">Quedan</th>
                    <th class="num">Valor</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($lots as $i => $lot)
                    <tr>
                        <td class="cell-main">
                            <div>
                                <strong>{{ $lot->received_at->translatedFormat('d/m/Y') }}</strong>
                                @if ($i === 0)
                                    <span class="badge badge-success">Sale primero</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Origen">{{ $lot->sourceLabel() }}</td>
                        <td data-label="Costo unitario" class="num nowrap">{{ cop($lot->unit_cost) }}</td>
                        <td data-label="Quedan" class="num">{{ num($lot->remaining) }} de {{ num($lot->quantity) }}</td>
                        <td data-label="Valor" class="num nowrap">{{ cop($lot->remaining * $lot->unit_cost) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="totals-grid">
                <div><span class="k">Unidades en bodega</span><strong>{{ num($product->stock) }}</strong></div>
                <div><span class="k">Valor del inventario</span><strong>{{ cop($product->inventoryValue()) }}</strong></div>
            </div>
        @endif
    </section>

    @if ($adjustments->isNotEmpty())
        <section class="card" aria-labelledby="adj-title">
            <h2 id="adj-title">Ajustes de inventario</h2>
            <table class="rtable">
                <thead>
                <tr>
                    <th>Motivo</th>
                    <th>Fecha</th>
                    <th class="num">Unidades</th>
                    <th class="num">Valor</th>
                    <th><span class="visually-hidden">Acciones</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($adjustments as $adj)
                    <tr @class(['is-voided' => $adj->isVoided()])>
                        <td class="cell-main">
                            <div>
                                <strong>{{ $adj->reasonLabel() }}</strong>
                                @if ($adj->isVoided()) <span class="badge badge-danger">Anulado</span> @endif
                                @if ($adj->note)<br><span class="small muted">{{ $adj->note }}</span>@endif
                            </div>
                        </td>
                        <td data-label="Fecha" class="nowrap">{{ $adj->adjusted_at->translatedFormat('d/m/Y h:i a') }}</td>
                        <td data-label="Unidades" @class(['num', 'text-danger' => $adj->isOut(), 'text-success' => ! $adj->isOut()])>
                            {{ $adj->isOut() ? '−' : '+' }}{{ num($adj->quantity) }}
                        </td>
                        <td data-label="Valor" class="num nowrap money">{{ cop($adj->total_cost) }}</td>
                        <td class="cell-actions">
                            @unless ($adj->isVoided())
                                <form method="POST" action="{{ route('admin.ajustes-inventario.void', $adj) }}"
                                      data-confirm="¿Seguro que quieres anular este ajuste? {{ $adj->isOut() ? 'Las unidades volverán al stock.' : 'Las unidades se restarán del stock.' }}"
                                      data-confirm-ok="Sí, anular">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">Anular</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
