{{-- Ventana de detalle del producto: foto, nombre, precio, descripción, cantidad y pedido por WhatsApp.
     Se llena con JavaScript a partir de la tarjeta que se tocó (solo datos públicos). --}}
<dialog class="pmodal" data-product-modal data-wa="{{ $whatsapp }}" aria-labelledby="pm-name">
    <div class="pmodal-box">
        <button type="button" class="pmodal-close" data-pm-close aria-label="Cerrar">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        <div class="pmodal-media">
            <img data-pm-img alt="">
            <span class="product-tag" data-pm-cat></span>
        </div>

        <div class="pmodal-info">
            <p class="pmodal-legal">
                Producto exclusivo para mayores de 18 años. Los vapeadores pueden contener nicotina, sustancia adictiva.
                El exceso de alcohol es perjudicial para la salud.
            </p>

            <span class="badge" data-pm-status></span>
            <h2 id="pm-name" class="pmodal-name" data-pm-name></h2>
            <p class="pmodal-price" data-pm-price></p>
            <p class="pmodal-desc" data-pm-desc></p>

            <div class="pmodal-buy" data-pm-buy>
                <div class="qty">
                    <span class="qty-label" id="pm-qty-label">Cantidad</span>
                    <div class="qty-box" role="group" aria-labelledby="pm-qty-label">
                        <button type="button" data-pm-minus aria-label="Quitar uno">−</button>
                        <input type="number" inputmode="numeric" min="1" max="99" value="1" data-pm-qty aria-labelledby="pm-qty-label">
                        <button type="button" data-pm-plus aria-label="Agregar uno">+</button>
                    </div>
                </div>

                <div class="pmodal-total">
                    <span data-pm-calc></span>
                    <strong data-pm-total></strong>
                </div>

                @if ($whatsapp)
                    <a class="btn btn-go btn-block pmodal-order" href="#" target="_blank" rel="noopener" data-pm-order>
                        @include('partials.chat-icon') <span>Pedir por WhatsApp</span>
                    </a>
                    <p class="pmodal-note">Se abrirá WhatsApp con tu pedido listo: producto, descripción, cantidad y total. Solo tienes que enviarlo.</p>
                @else
                    <button type="button" class="btn btn-block" disabled>Pedidos no disponibles por ahora</button>
                @endif
            </div>

            <button type="button" class="btn btn-block" disabled data-pm-soldout hidden>Producto agotado</button>
        </div>
    </div>
</dialog>
