@extends('layouts.admin')

@section('title', 'Compras')

@section('content')
    <div class="page-head">
        <h1>Compras</h1>
        <a href="{{ route('admin.compras.create') }}" class="btn btn-primary">+ Registrar compra</a>
    </div>

    <p class="muted">Registra aquí la mercancía que compras: el stock de cada producto sube automáticamente.</p>

    {{-- Resumen rápido: cuánto invertiste hoy, esta semana y este mes --}}
    <div class="stats">
        @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes'] as $key => $label)
            <a href="{{ route('admin.compras.index', ['periodo' => $key]) }}" class="stat">
                <span class="stat-label">{{ $label }}</span>
                <span class="stat-value stat-value-neutral">{{ cop($summary[$key]->total) }}</span>
                <span class="stat-sub">invertido · {{ num($summary[$key]->units) }} unidades</span>
            </a>
        @endforeach
    </div>

    {{-- Filtros --}}
    <div class="card">
        <nav class="chips" aria-label="Periodo">
            @foreach (\App\Support\DateRange::PRESETS as $key => $label)
                <a href="{{ route('admin.compras.index', ['periodo' => $key]) }}"
                   @class(['chip', 'active' => $range->preset === $key])>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.compras.index') }}" class="filters">
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
                <div><span class="k">Invertido</span><strong>{{ cop($totals->total) }}</strong></div>
                <div><span class="k">Compras</span><strong>{{ num($totals->count) }}</strong></div>
                <div><span class="k">Unidades</span><strong>{{ num($totals->units) }}</strong></div>
            </div>
        </div>
        <a href="{{ route('admin.compras.export', $range->query()) }}" class="btn">⬇ Descargar Excel</a>
    </div>

    @if ($purchases->isEmpty())
        <div class="card empty">No hay compras en este periodo.</div>
    @else
        <table class="rtable">
            <thead>
            <tr>
                <th>Producto</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th class="num">Cantidad</th>
                <th class="num">Costo unitario</th>
                <th class="num">Total</th>
                <th><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($purchases as $purchase)
                <tr @class(['is-voided' => $purchase->isVoided()])>
                    <td class="cell-main">
                        <div>
                            <strong>{{ $purchase->product_name }}</strong>
                            @if ($purchase->isVoided())
                                <span class="badge badge-danger">Anulada</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Fecha" class="nowrap">{{ $purchase->purchased_at->translatedFormat('d/m/Y h:i a') }}</td>
                    <td data-label="Proveedor">{{ $purchase->supplier ?? '—' }}</td>
                    <td data-label="Cantidad" class="num">+{{ num($purchase->quantity) }}</td>
                    <td data-label="Costo unitario" class="num nowrap money">{{ cop($purchase->unit_cost) }}</td>
                    <td data-label="Total" class="num nowrap money"><strong>{{ cop($purchase->total) }}</strong></td>
                    <td class="cell-actions">
                        @unless ($purchase->isVoided())
                            <form method="POST" action="{{ route('admin.compras.void', $purchase) }}"
                                  data-confirm="¿Seguro que quieres anular la compra de {{ $purchase->quantity }} × «{{ $purchase->product_name }}»? Esas unidades se restarán del stock."
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

        @include('partials.pager', ['paginator' => $purchases])
    @endif
@endsection
