{{-- Tarjeta del catálogo. Recibe un CatalogItem (nunca el producto completo).
     Los data-* llevan solo datos públicos: los usa la ventana de detalle del producto. --}}
<article @class(['product', 'is-sold-out' => ! $item->canOrder])
         id="producto-{{ $item->id }}"
         data-cat="{{ $item->categorySlug }}" data-search="{{ $item->searchText() }}"
         data-id="{{ $item->id }}" data-name="{{ $item->name }}" data-desc="{{ $item->description }}"
         data-price="{{ $item->price }}" data-category="{{ $item->category }}"
         data-photo="{{ $item->photoUrl }}" data-status="{{ $item->status }}" data-status-label="{{ $item->statusLabel() }}"
         data-can-order="{{ $item->canOrder ? '1' : '0' }}"
         @unless (isset($shownIds[$item->id])) hidden @endunless>
    <a class="product-media" href="#producto-{{ $item->id }}" data-open-product aria-label="Ver detalle de {{ $item->name }}">
        @if ($item->thumbUrl)
            <img class="product-img" src="{{ $item->thumbUrl }}"
                 srcset="{{ $item->thumbUrl }} 480w, {{ $item->photoUrl }} 1200w"
                 sizes="(min-width: 1080px) 33vw, (min-width: 761px) 50vw, 100vw"
                 alt="{{ $item->name }}" loading="lazy" decoding="async">
        @else
            <svg class="product-placeholder" viewBox="0 0 84 120" aria-hidden="true" focusable="false">
                <rect x="30" y="6" width="24" height="14" rx="3"/>
                <rect x="18" y="20" width="48" height="92" rx="10"/>
                <rect x="28" y="40" width="28" height="40" rx="4"/>
                <path d="M28 92h28"/>
            </svg>
        @endif
        <span class="product-tag">{{ $item->category }}</span>
        @unless ($item->canOrder)
            <span class="stamp" aria-hidden="true">Agotado</span>
        @endunless
    </a>

    <div class="product-body">
        <span @class([
            'badge',
            'badge-success' => $item->status === \App\Catalog\CatalogItem::AVAILABLE,
            'badge-warning' => $item->status === \App\Catalog\CatalogItem::LAST_UNITS,
            'badge-danger' => $item->status === \App\Catalog\CatalogItem::SOLD_OUT,
        ])>{{ $item->statusLabel() }}</span>

        <h2 class="product-name">{{ $item->name }}</h2>

        @if ($item->description)
            <p class="product-desc">{{ $item->description }}</p>
        @endif

        <div class="product-foot">
            <p class="product-price">{{ cop($item->price) }}</p>

            @if (! $item->canOrder)
                <button type="button" class="btn btn-block" disabled>Producto agotado</button>
            @elseif ($item->whatsappUrl($whatsapp))
                <div class="cart-card-controls" data-cart-card-controls hidden>
                    <button type="button" data-cart-card-minus aria-label="Quitar una unidad">−</button>
                    <span><strong data-cart-card-qty>1</strong> en el carrito</span>
                    <button type="button" data-cart-card-plus aria-label="Agregar una unidad">+</button>
                </div>
                <button type="button" class="cart-add" data-cart-add aria-label="Agregar {{ $item->name }} al carrito">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 8H6"/><circle cx="10" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/><path d="M15 5v6M12 8h6"/></svg>
                    <span data-cart-add-label>Agregar al carrito</span>
                </button>
            @else
                <button type="button" class="btn btn-block" disabled>Pedidos no disponibles por ahora</button>
            @endif
        </div>
    </div>
</article>
