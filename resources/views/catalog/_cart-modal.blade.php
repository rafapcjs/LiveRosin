<dialog class="cart-modal" data-cart-modal aria-labelledby="cart-title">
    <div class="cart-box">
        <button type="button" class="pmodal-close" data-cart-close aria-label="Cerrar carrito">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <h2 id="cart-title" class="cart-title">Mi carrito</h2>
        <div class="cart-items" data-cart-items></div>
        <div class="cart-empty" data-cart-empty>
            <strong>Tu carrito está vacío</strong>
            <p>Agrega productos para comenzar tu pedido.</p>
            <button type="button" class="btn btn-dark btn-block" data-cart-products>Ver productos</button>
        </div>
        <div class="cart-summary" data-cart-summary hidden>
            <span>Total</span><strong data-cart-total></strong>
        </div>
        <div class="field cart-location" data-cart-location-box hidden>
            <label for="cart-location">Ubicación / dirección <span class="muted small">(opcional)</span></label>
            <input id="cart-location" type="text" class="input" maxlength="250" autocomplete="street-address"
                   placeholder="Barrio, calle, número y una referencia" data-cart-location>
        </div>
        @if ($whatsapp)
            <a class="btn btn-go btn-block" href="#" target="_blank" rel="noopener" data-cart-order hidden>
                @include('partials.chat-icon') <span>Finalizar compra</span>
            </a>
        @endif
    </div>
</dialog>
