{{-- Menú lateral (☰). Sin JavaScript se abre con el enlace #menu; con JavaScript se maneja con el botón "atrás". --}}
<div class="drawer" id="menu" data-menu aria-hidden="true">
    <a href="#" class="drawer-backdrop" data-menu-close tabindex="-1" aria-hidden="true"></a>
    <nav class="drawer-panel" aria-label="Menú">
        <div class="drawer-head">
            <span class="drawer-brand outline">{{ $storeName }}</span>
            <a href="#" class="drawer-close" role="button" data-menu-close aria-label="Cerrar menú">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </a>
        </div>

        <p class="drawer-title">Categorías</p>
        <ul class="drawer-list">
            <li>
                <a href="{{ url('/') }}#productos" data-filter="" data-label=""
                   @class(['drawer-link', 'active' => ! $current && $search === ''])>
                    <span>Todos los productos</span><span class="drawer-count">{{ $items->count() }}</span>
                </a>
            </li>
            @foreach ($categories as $category)
                <li>
                    <a href="{{ url('/').'?categoria='.urlencode($category->slug) }}#productos"
                       data-filter="{{ $category->slug }}" data-label="{{ $category->name }}"
                       @class(['drawer-link', 'active' => $current?->id === $category->id && $search === ''])>
                        <span>{{ $category->name }}</span><span class="drawer-count">{{ $counts[$category->id] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="drawer-title">Mi compra</p>
        <button type="button" class="drawer-cart" data-cart-open aria-label="Ver carrito">
            <span class="drawer-cart-icon" aria-hidden="true">🛒</span>
            <span class="drawer-cart-copy">
                <strong>Ver carrito</strong>
                <small><span data-cart-menu-count>0</span> productos · <span data-cart-menu-total>$ 0</span></small>
            </span>
        </button>

        <p class="drawer-title">La tienda</p>
        <ul class="drawer-list">
            <li><a href="#inicio" class="drawer-link" data-menu-go>Inicio</a></li>
            <li><a href="#quienes-somos" class="drawer-link" data-menu-go>Quiénes somos</a></li>
            <li><a href="#como-pedir" class="drawer-link" data-menu-go>Cómo pedir</a></li>
            <li><a href="#contacto" class="drawer-link" data-menu-go>Contacto</a></li>
        </ul>

    </nav>
</div>
