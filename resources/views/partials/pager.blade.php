@if ($paginator->hasPages())
    <nav class="pager" aria-label="Páginas">
        @if ($paginator->onFirstPage())
            <span class="btn is-disabled" aria-disabled="true">« Anterior</span>
        @else
            <a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Anterior</a>
        @endif

        <span class="muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente »</a>
        @else
            <span class="btn is-disabled" aria-disabled="true">Siguiente »</span>
        @endif
    </nav>
@endif
