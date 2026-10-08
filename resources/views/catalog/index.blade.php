@extends('layouts.store')

@section('title', $storeName)

@php
    // WhatsApp para mostrar: 573113809789 => "311 380 9789"
    $waShown = $whatsapp ? preg_replace('/^57(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', $whatsapp) : null;
    $waGeneral = $whatsapp ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode('Hola, quiero hacer un pedido.') : null;
    $waWholesale = $whatsapp ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode('Hola, quiero información de precios al por mayor.') : null;
@endphp

@section('body')
    {{-- Barra fija: menú ☰ + buscador --}}
    <header class="topbar">
        <a href="#menu" class="menu-btn" role="button" aria-controls="menu" aria-expanded="false" data-menu-open>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            <span class="visually-hidden">Abrir menú</span>
        </a>
        {{-- En PC se ven también el nombre de la tienda y las secciones --}}
        <a href="#inicio" class="topbar-brand outline">{{ $storeName }}</a>
        <nav class="topbar-nav" aria-label="Secciones">
            <a href="#productos">Productos</a>
            <a href="#quienes-somos">Quiénes somos</a>
            <a href="#como-pedir">Cómo pedir</a>
            <a href="#contacto">Contacto</a>
        </nav>
        <button type="button" class="cart-btn" data-cart-open aria-label="Abrir carrito">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 8H6"/><circle cx="10" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/></svg>
            <span class="cart-label">Ver carrito</span>
            <span class="cart-count" data-cart-count hidden>0</span>
            <span class="cart-top-total" data-cart-top-total hidden></span>
        </button>
        <form class="search" method="GET" action="{{ url('/') }}#productos" role="search" data-search-form>
            <label for="buscar" class="visually-hidden">Buscar productos</label>
            <input id="buscar" name="q" type="search" placeholder="Buscar" value="{{ $search }}"
                   autocomplete="off" enterkeyhint="search" maxlength="60" data-search-input>
            <button type="submit" class="search-btn" aria-label="Buscar">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.8-4.8"/></svg>
            </button>
        </form>
    </header>

    @include('catalog._drawer')
    @include('catalog._product-modal')
    @include('catalog._cart-modal')
    <div class="cart-toast" data-cart-toast role="status" aria-live="polite" hidden></div>
    <button type="button" class="mobile-cart-bar" data-cart-open aria-label="Ver carrito" hidden>
        <span>🛒 <strong>Mi carrito</strong></span>
        <span><span data-mobile-cart-count>0</span> productos · <strong data-mobile-cart-total>$ 0</strong></span>
        <span class="mobile-cart-bar-action">Ver carrito</span>
    </button>

    <section id="inicio" class="hero">
        <div class="grain" aria-hidden="true"></div>
        @include('partials.skyline')
        <div class="wrap hero-inner">
            <p class="kicker"> misión del día: elige y pide por WhatsApp</p>
            <h1 class="hero-title outline-lg">{{ $storeName }}</h1>
            <p class="hero-text outline">{{ $tagline ?: 'Destilados, vapers y más. Escoge tu producto y pide por WhatsApp.' }}</p>
            <div class="hero-actions">
                <a href="#productos" class="btn btn-go">Ver productos</a>
            </div>
        </div>
    </section>

    <section id="productos" class="menu brick">
        <div class="spray spray-top" aria-hidden="true">{{ $storeName }}</div>
        <div class="spray spray-bottom" aria-hidden="true">del barrio</div>

        <div class="wrap menu-inner">
            <div class="section-head">
                <p class="kicker kicker-orange">// el menú del barrio</p>
                <h2 class="section-title outline-lg">Productos</h2>
            </div>

            @if ($categories->count() > 1)
                <nav class="pills" aria-label="Categorías" data-catalog-filter>
                    <a href="{{ url('/') }}#productos" data-filter="" data-label=""
                       @class(['pill', 'active' => ! $current]) @if (! $current) aria-current="page" @endif>Todos</a>
                    @foreach ($categories as $category)
                        @php $isCurrent = $current?->id === $category->id; @endphp
                        <a href="{{ url('/').'?categoria='.urlencode($category->slug) }}#productos"
                           data-filter="{{ $category->slug }}" data-label="{{ $category->name }}"
                           @class(['pill', 'active' => $isCurrent]) @if ($isCurrent) aria-current="page" @endif>{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($unknownCategory)
                <p class="notice">Esa categoría no existe. Te mostramos todos los productos.</p>
            @endif

            @if ($items->isEmpty())
                <p class="notice">Pronto tendremos productos disponibles.</p>
            @else
                @php
                    $shown = count($shownIds);
                    if ($search !== '') {
                        $countText = ($shown === 1 ? '1 resultado' : $shown.' resultados').' para «'.$search.'»';
                    } else {
                        $countText = ($shown === 1 ? '1 producto' : $shown.' productos').($current ? ' en '.$current->name : '');
                    }
                @endphp
                <p class="results-count" data-results-count aria-live="polite">{{ $countText }}</p>
                <div class="no-results" data-no-results @if ($shown > 0) hidden @endif>
                    <p>No encontramos productos{{ $search !== '' ? ' para «'.$search.'»' : '' }}.</p>
                    <a href="{{ url('/') }}#productos" class="btn btn-dark" data-filter-reset>Ver todos los productos</a>
                </div>
                <div class="products" data-products>
                    @foreach ($items as $item)
                        @include('catalog._card', ['item' => $item])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="quienes-somos" class="about asphalt">
        <div class="wrap about-inner">
            <div class="about-copy">
                <p class="kicker kicker-orange">// quiénes somos</p>
                <h2 class="section-title outline-lg">Quiénes somos</h2>
                <p class="about-lead">
                    En <strong>{{ $storeName }}</strong> somos <strong>proveedores al por mayor y al detal</strong>.
                    Tenemos lo que necesitas para que te parches con tus amigos y la pases bacano:
                    destilados, vapers y todo pa' la vuelta escribinos al WhatsApp recibe precios especiales al por mayor
                </p>
                <p>
                    Ya sea que tengas un negocio y busques surtido, o que solo quieras armar el plan del fin de semana,
                    aquí te atendemos como parcero: rápido, por WhatsApp y con buenos precios.
                </p>
                <p class="about-note">Solo para mayores de 18. Disfruta con responsabilidad.</p>
            </div>

            <div class="about-cards">
                <div class="about-card">
                    <span class="about-icon" aria-hidden="true">📦</span>
                    <h3>Al por mayor</h3>
                    <p>¿Tienes tienda, bar o revendes? Llega a nuestro WhatsApp y recibe los descuentos.</p>
                </div>
                <div class="about-card">
                    <span class="about-icon" aria-hidden="true">🛍️</span>
                    <h3>Al detal</h3>
                    <p>Desde una unidad. Pide lo que necesites, sin mínimos.</p>
                </div>
                <div class="about-card">
                    <span class="about-icon" aria-hidden="true">🎉</span>
                    <h3>Pal' parche</h3>
                    <p>Lo que se necesita para el plan con los amigos, listo para que te llegue.</p>
                </div>
                @if ($waWholesale)
                    <a href="{{ $waWholesale }}" class="btn btn-dark btn-block about-cta" target="_blank" rel="noopener">
                        @include('partials.chat-icon') <span>Pide precios al por mayor</span>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section id="como-pedir" class="steps-section fence">
        <div class="wrap">
            <h2 class="section-title outline-lg">Cómo pedir</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-num outline">01</div>
                    <h3>Escoge tu producto</h3>
                    <p>Mira el menú y toca «Agregar al carrito» en el producto que quieras.</p>
                </div>
                <div class="step">
                    <div class="step-num outline">02</div>
                    <h3>Elige la cantidad</h3>
                    <p>Selecciona cuántas unidades quieres y agrégalas al carrito.</p>
                </div>
                <div class="step">
                    <div class="step-num outline">03</div>
                    <h3>Revisa tu carrito</h3>
                    <p>Abre «Ver carrito» para editar cantidades, eliminar productos y confirmar el total.</p>
                </div>
                <div class="step">
                    <div class="step-num outline">04</div>
                    <h3>Finaliza por WhatsApp</h3>
                    <p>Toca «Finalizar compra», revisa el mensaje y envíalo. Coordinamos la entrega y verificamos la edad.</p>
                </div>
                <div class="step step-note">
                    <div class="step-num outline">05</div>
                    <h3>Te llega al barrio</h3>
                    <p>Coordinamos la entrega por WhatsApp. Recuerda tener lista tu ubicación si deseas compartirla.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="contacto" class="contact asphalt">
        <div class="wrap contact-inner">
            <div class="contact-copy">
                <p class="kicker kicker-orange">// nueva misión disponible</p>
                <h2 class="section-title outline-lg">Pide por WhatsApp</h2>
                <p>Escríbenos y cerramos el negocio. Te respondemos con disponibilidad y entrega.</p>
            </div>
            <div class="contact-card">
                <div class="contact-row"><span>WhatsApp</span><strong>{{ $waShown ?? 'Muy pronto' }}</strong></div>
                @if ($lastUpdate)
                    <div class="contact-row"><span>Precios al día</span><strong>{{ $lastUpdate->timezone('America/Bogota')->translatedFormat('j M Y, h:i a') }}</strong></div>
                @endif
                @if ($waGeneral)
                    <a href="{{ $waGeneral }}" class="btn btn-go btn-block" target="_blank" rel="noopener">
                        @include('partials.chat-icon') <span>Escríbenos</span>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <footer class="site-foot">
        <div class="wrap">
            <p class="warning">
                ADVERTENCIA: Productos exclusivos para mayores de 18 años. Los vapeadores pueden contener nicotina,
                una sustancia altamente adictiva. El exceso de alcohol es perjudicial para la salud. Ley 30 de 1986.
                Prohíbese el expendio de bebidas embriagantes a menores de edad. Ley 124 de 1994.
            </p>
            @if ($lastUpdate)
                <p class="foot-update">Última actualización de precios y disponibilidad:
                    <strong>{{ $lastUpdate->timezone('America/Bogota')->translatedFormat('j \d\e F \d\e Y, h:i a') }}</strong> (hora de Colombia)
                </p>
            @endif
            <div class="foot-row">
                <span>© {{ now()->year }} {{ $storeName }}</span>
                <span class="typewriter">hecho en el barrio</span>
            </div>
        </div>
    </footer>

    @if ($waGeneral)
        <a class="wa-fab" href="{{ $waGeneral }}" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
            @include('partials.chat-icon')
        </a>
    @endif
@endsection
