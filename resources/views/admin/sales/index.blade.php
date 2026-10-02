@extends('layouts.admin')

@section('title', 'Ventas')

@section('content')
    <div class="page-head">
        <h1>Ventas</h1>
        <a href="{{ route('admin.ventas.create') }}" class="btn btn-primary">+ Registrar venta</a>
    </div>

    {{-- Resumen rápido: hoy, semana y mes (sin ventas anuladas) --}}
    <div class="stats">
        @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes'] as $key => $label)
            <a href="{{ route('admin.ventas.index', ['periodo' => $key]) }}" class="stat">
                <span class="stat-label">{{ $label }}</span>
                <span class="stat-value">{{ cop($summary[$key]->profit) }}</span>
                <span class="stat-sub">ganancia · vendido {{ cop($summary[$key]->total) }}</span>
            </a>
        @endforeach
    </div>

    @include('partials.stock-alerts')

    {{-- Filtros --}}
    <div class="card">
        <nav class="chips" aria-label="Periodo">
            @foreach (\App\Support\DateRange::PRESETS as $key => $label)
                <a href="{{ route('admin.ventas.index', ['periodo' => $key]) }}"
                   @class(['chip', 'active' => $range->preset === $key])>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.ventas.index') }}" class="filters">
            <div class="field">
                <label for="desde">Desde</label>
                <input id="desde" name="desde" type="date" class="input" value="{{ $range->from->format('Y-m-d') }}">
            </div>
            <div class="field">
                <label for="hasta">Hasta</label>
                <input id="hasta" name="hasta" type="date" class="input" value="{{ $range->to->format('Y-m-d') }}">
            </div>
            <div class="actions">
                <button type="submit" class="btn">Filtrar</button>
            </div>
        </form>
        @if ($range->error)
            <div class="error">{{ $range->error }}</div>
        @endif
    </div>

    {{-- Totales del filtro --}}
    <div class="card totals">
        <div>
            <span class="muted small">{{ $range->label() }}</span>
            <div class="totals-grid">
                <div><span class="k">Vendido</span><strong>{{ cop($totals->total) }}</strong></div>
                <div><span class="k">Ganancia</span><strong class="{{ $totals->profit < 0 ? 'text-danger' : 'text-success' }}">{{ cop($totals->profit) }}</strong></div>
                <div><span class="k">Ventas</span><strong>{{ num($totals->count) }}</strong></div>
                <div><span class="k">Unidades</span><strong>{{ num($totals->units) }}</strong></div>
            </div>
        </div>
        <a href="{{ route('admin.ventas.export', $range->query()) }}" class="btn">⬇ Descargar Excel</a>
    </div>

    @if ($sales->isEmpty())
        <div class="card empty">No hay ventas en este periodo.</div>
    @else
        <table class="rtable">
            <thead>
            <tr>
                <th>Producto</th>
                <th>Fecha</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unitario</th>
                <th class="num">Total</th>
                <th class="num">Ganancia</th>
                <th><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($sales as $sale)
                <tr @class(['is-voided' => $sale->isVoided()])>
                    <td class="cell-main">
                        <div>
                            <strong>{{ $sale->product_name }}</strong>
                            @if ($sale->isVoided())
                                <span class="badge badge-danger">Anulada</span>
                            @endif
                            @if ($sale->note)
                                <br><span class="small muted">📝 {{ $sale->note }}</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Fecha" class="nowrap">{{ $sale->sold_at->translatedFormat('d/m/Y h:i a') }}</td>
                    <td data-label="Cantidad" class="num">{{ num($sale->quantity) }}</td>
                    <td data-label="Precio unitario" class="num nowrap money">{{ cop($sale->unit_price) }}</td>
                    <td data-label="Total" class="num nowrap money"><strong>{{ cop($sale->total) }}</strong></td>
                    <td data-label="Ganancia" @class(['num nowrap money', 'text-danger' => $sale->profit < 0])>{{ cop($sale->profit) }}</td>
                    <td class="cell-actions">
                        @unless ($sale->isVoided())
                            <form method="POST" action="{{ route('admin.ventas.void', $sale) }}"
                                  data-confirm="¿Seguro que quieres anular la venta de {{ $sale->quantity }} × «{{ $sale->product_name }}»? Las unidades volverán al stock."
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

        @include('partials.pager', ['paginator' => $sales])
    @endif
@endsection
