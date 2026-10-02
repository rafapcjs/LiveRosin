{{-- Alertas de stock: productos agotados o con pocas unidades. Recibe $lowStock. --}}
@if ($lowStock->isNotEmpty())
    <section class="card card-warning" aria-labelledby="stock-alerts-title">
        <h2 id="stock-alerts-title">⚠ Alertas de stock ({{ $lowStock->count() }})</h2>
        <ul class="alert-list">
            @foreach ($lowStock as $p)
                <li>
                    <div>
                        <span class="name">{{ $p->name }}</span><br>
                        @if ($p->stock === 0)
                            <span class="badge badge-danger">Agotado</span>
                        @else
                            <span class="badge badge-warning">{{ $p->stock === 1 ? 'Queda 1 unidad' : 'Quedan '.$p->stock.' unidades' }}</span>
                        @endif
                    </div>
                    <a href="{{ route('admin.compras.create', ['producto' => $p->id]) }}" class="btn btn-sm">Reponer</a>
                </li>
            @endforeach
        </ul>
        <p class="small muted">Se avisa cuando quedan {{ \App\Models\Setting::lowStockThreshold() }} unidades o menos. Puedes cambiarlo en <a href="{{ route('admin.ajustes.edit') }}">Ajustes</a>.</p>
    </section>
@endif
