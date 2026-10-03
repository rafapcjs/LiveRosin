/*
 * JavaScript mínimo de la tienda (sin librerías).
 * Cada bloque solo se activa si encuentra en la página los elementos que necesita.
 */
(function () {
    'use strict';

    var nf = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });
    var pf = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    /** Convierte "1.250.000" o "$ 1250000" en 1250000. Devuelve null si está vacío o no es válido. */
    function toInt(value) {
        var clean = String(value || '').replace(/[\s$.]/g, '');
        if (clean === '' || !/^-?\d+$/.test(clean)) return null;
        return parseInt(clean, 10);
    }

    function money(n) {
        return (n < 0 ? '-$ ' : '$ ') + nf.format(Math.abs(n));
    }

    function percent(n) {
        return pf.format(n) + ' %';
    }

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    /* ------------------------------------------------------------------
     * 1. Cálculo instantáneo de ganancia, margen y recargo (formulario de producto)
     * ------------------------------------------------------------------ */
    var calcForm = $('[data-product-calc]');
    if (calcForm) {
        var costIn = $('[name="cost"]', calcForm);
        var priceIn = $('[name="price"]', calcForm);
        var stockIn = $('[name="stock"]', calcForm);
        var warning = $('[data-price-warning]', calcForm);

        var setOut = function (key, text, cls) {
            $all('[data-out="' + key + '"]', calcForm).forEach(function (el) {
                el.textContent = text;
                el.classList.toggle('text-danger', cls === 'neg');
            });
        };

        var recalc = function () {
            var cost = toInt(costIn.value);
            var price = toInt(priceIn.value);
            var stock = stockIn ? toInt(stockIn.value) : null;

            $all('[data-money-preview]', calcForm).forEach(function (el) {
                var n = toInt($('[name="' + el.getAttribute('data-money-preview') + '"]', calcForm).value);
                el.textContent = n === null ? '' : money(n);
            });

            if (cost === null || price === null) {
                ['profit', 'margin', 'markup', 'stockProfit'].forEach(function (k) { setOut(k, '—'); });
                if (warning) warning.hidden = true;
                return;
            }

            var profit = price - cost;
            var neg = profit < 0 ? 'neg' : '';
            setOut('profit', money(profit), neg);
            setOut('margin', price > 0 ? percent(profit / price * 100) : '—', neg);
            setOut('markup', cost > 0 ? percent(profit / cost * 100) : '—', neg);
            setOut('stockProfit', stock === null ? '—' : money(profit * stock), neg);
            if (warning) warning.hidden = !(price < cost);
        };

        [costIn, priceIn, stockIn].forEach(function (el) { if (el) el.addEventListener('input', recalc); });
        recalc();

        /* 2. Calculadora inversa: precio sugerido según el margen deseado */
        var marginIn = $('[data-target-margin]', calcForm);
        var suggestOut = $('[data-suggested-price]', calcForm);
        var useBtn = $('[data-use-price]', calcForm);
        var marginErr = $('[data-margin-error]', calcForm);
        var suggested = null;

        var suggest = function () {
            suggested = null;
            var raw = String(marginIn.value || '').replace(',', '.').trim();
            var cost = toInt(costIn.value);
            marginErr.hidden = true;

            if (raw === '' || cost === null) {
                suggestOut.textContent = '—';
            } else {
                var m = Number(raw);
                if (isNaN(m) || m < 0 || m > 99.9) {
                    marginErr.hidden = false;
                    suggestOut.textContent = '—';
                } else {
                    // precio = costo / (1 − margen/100), redondeado a la centena más cercana
                    suggested = Math.round(cost / (1 - m / 100) / 100) * 100;
                    suggestOut.textContent = money(suggested);
                }
            }
            useBtn.disabled = suggested === null;
        };

        marginIn.addEventListener('input', suggest);
        costIn.addEventListener('input', suggest);
        useBtn.addEventListener('click', function () {
            if (suggested === null) return;
            priceIn.value = suggested;
            recalc();
            priceIn.focus();
        });
        suggest();
    }

    /* ------------------------------------------------------------------
     * Buscador simple para una lista desplegable.
     * Uso: <input data-filter-select="id-del-select">
     * ------------------------------------------------------------------ */
    $all('[data-filter-select]').forEach(function (input) {
        var select = document.getElementById(input.getAttribute('data-filter-select'));
        if (!select) return;
        var all = Array.prototype.slice.call(select.options);
        var norm = function (s) {
            return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        };

        input.addEventListener('input', function () {
            var q = norm(input.value.trim());
            var current = select.value;
            var matches = [];
            while (select.options.length) select.remove(0);
            all.forEach(function (opt) {
                if (opt.value === '' || q === '' || norm(opt.text).indexOf(q) !== -1) {
                    select.add(opt);
                    if (opt.value !== '') matches.push(opt);
                }
            });
            var enabled = matches.filter(function (o) { return !o.disabled; });
            if (q !== '' && enabled.length === 1) {
                select.value = enabled[0].value;
            } else {
                select.value = matches.some(function (o) { return o.value === current; }) ? current : '';
            }
            select.dispatchEvent(new Event('change'));
        });
    });

    /* ------------------------------------------------------------------
     * Total y ganancia en vivo al registrar una venta
     * ------------------------------------------------------------------ */
    var saleForm = $('[data-sale-form]');
    if (saleForm) {
        var productSel = $('[name="product_id"]', saleForm);
        var qtyIn = $('[name="quantity"]', saleForm);
        var unitIn = $('[name="unit_price"]', saleForm);
        var totalOut = $('[data-sale-total]', saleForm);
        var profitOut = $('[data-sale-profit]', saleForm);
        var stockInfo = $('[data-stock-info]', saleForm);
        var qtyWarn = $('[data-qty-warning]', saleForm);
        var belowCost = $('[data-below-cost]', saleForm);
        var lastProduct = null;

        var selected = function () {
            var opt = productSel.options[productSel.selectedIndex];
            return opt && opt.value ? opt : null;
        };

        var update = function () {
            var opt = selected();
            var qty = toInt(qtyIn.value);
            var price = toInt(unitIn.value);

            if (!opt) {
                stockInfo.textContent = '';
                totalOut.textContent = '—';
                profitOut.textContent = '—';
                qtyWarn.hidden = true;
                belowCost.hidden = true;
                return;
            }

            var stock = parseInt(opt.getAttribute('data-stock'), 10);
            var cost = parseInt(opt.getAttribute('data-cost'), 10);
            stockInfo.textContent = 'Disponibles: ' + nf.format(stock) +
                ' · Precio normal: ' + money(parseInt(opt.getAttribute('data-price'), 10));

            if (qty !== null && qty > stock) {
                qtyWarn.textContent = stock === 1 ? 'Solo queda 1 unidad.' : 'Solo quedan ' + nf.format(stock) + ' unidades.';
                qtyWarn.hidden = false;
            } else {
                qtyWarn.hidden = true;
            }

            belowCost.hidden = !(price !== null && price < cost);

            if (qty === null || qty < 1 || price === null) {
                totalOut.textContent = '—';
                profitOut.textContent = '—';
                return;
            }
            totalOut.textContent = money(price * qty);
            var profit = (price - cost) * qty;
            profitOut.textContent = money(profit);
            profitOut.classList.toggle('text-danger', profit < 0);
        };

        productSel.addEventListener('change', function () {
            var opt = selected();
            // Al cambiar de producto se pone su precio normal (el descuento se escribe después).
            if (opt && opt.value !== lastProduct) {
                if (lastProduct !== null || unitIn.value.trim() === '') {
                    unitIn.value = opt.getAttribute('data-price');
                }
                lastProduct = opt.value;
            }
            update();
        });
        [qtyIn, unitIn].forEach(function (el) { el.addEventListener('input', update); });

        if (selected()) {
            lastProduct = selected().value;
            if (unitIn.value.trim() === '') unitIn.value = selected().getAttribute('data-price');
        }
        update();
    }

    /* ------------------------------------------------------------------
     * Total en vivo al registrar una compra
     * ------------------------------------------------------------------ */
    var buyForm = $('[data-purchase-form]');
    if (buyForm) {
        var bProduct = $('[name="product_id"]', buyForm);
        var bQty = $('[name="quantity"]', buyForm);
        var bCost = $('[name="unit_cost"]', buyForm);
        var bTotal = $('[data-purchase-total]', buyForm);
        var bAfter = $('[data-stock-after]', buyForm);
        var bInfo = $('[data-stock-info]', buyForm);
        var bAvg = $('[data-avg-after]', buyForm);
        var bLast = null;

        var bSelected = function () {
            var opt = bProduct.options[bProduct.selectedIndex];
            return opt && opt.value ? opt : null;
        };

        var bUpdate = function () {
            var opt = bSelected();
            var qty = toInt(bQty.value);
            var cost = toInt(bCost.value);

            if (!opt) {
                bInfo.textContent = '';
                bAfter.textContent = '—';
                bTotal.textContent = '—';
                bAvg.textContent = '—';
                return;
            }

            var stock = parseInt(opt.getAttribute('data-stock'), 10);
            var current = parseInt(opt.getAttribute('data-cost'), 10);
            var valid = qty !== null && qty > 0 && cost !== null && cost >= 0;
            bInfo.textContent = 'En stock ahora: ' + nf.format(stock) + ' · Costo promedio actual: ' + money(current);
            bAfter.textContent = qty !== null && qty > 0 ? nf.format(stock + qty) : nf.format(stock);
            bTotal.textContent = valid ? money(qty * cost) : '—';
            // Promedio ponderado de lo que quedará en bodega (solo informativo).
            bAvg.textContent = valid ? money(Math.round((stock * current + qty * cost) / (stock + qty))) : '—';
        };

        bProduct.addEventListener('change', function () {
            var opt = bSelected();
            if (opt && opt.value !== bLast) {
                if (bLast !== null || bCost.value.trim() === '') bCost.value = opt.getAttribute('data-cost');
                bLast = opt.value;
            }
            bUpdate();
        });
        [bQty, bCost].forEach(function (el) { el.addEventListener('input', bUpdate); });

        if (bSelected()) {
            bLast = bSelected().value;
            if (bCost.value.trim() === '') bCost.value = bSelected().getAttribute('data-cost');
        }
        bUpdate();
    }

    /* ------------------------------------------------------------------
     * Ajuste de inventario: mostrar los campos de "sacar" o "agregar"
     * ------------------------------------------------------------------ */
    var adjForm = $('[data-adjust-form]');
    if (adjForm) {
        var adjToggle = function () {
            var checked = $('[name="type"]:checked', adjForm);
            var type = checked ? checked.value : 'baja';
            $all('[data-adjust]', adjForm).forEach(function (box) {
                var on = box.getAttribute('data-adjust') === type;
                box.hidden = !on;
                // Los campos ocultos se desactivan para que no se envíen.
                $all('input, select', box).forEach(function (el) { el.disabled = !on; });
            });
        };
        $all('[name="type"]', adjForm).forEach(function (r) { r.addEventListener('change', adjToggle); });
        adjToggle();
    }

    /* ------------------------------------------------------------------
     * Producto: mostrar el campo "nueva categoría" al elegir esa opción
     * ------------------------------------------------------------------ */
    var newCatBox = $('[data-new-category]');
    var catSelect = $('select[name="category_id"]');
    if (newCatBox && catSelect) {
        var toggleNewCat = function () {
            var isNew = catSelect.value === 'nueva';
            newCatBox.hidden = !isNew;
            if (isNew) $('input', newCatBox).focus();
        };
        catSelect.addEventListener('change', toggleNewCat);
    }

    /* ------------------------------------------------------------------
     * Catálogo: menú ☰, buscador y filtro por categoría AL INSTANTE.
     * Todos los productos ya vienen en la página; aquí solo se muestran u ocultan.
     * Cada cambio queda en el historial (?categoria=… / ?q=…), así el botón
     * "atrás" del celular cierra el menú, deshace la búsqueda y vuelve a la
     * categoría anterior. Sin JavaScript, todo sigue funcionando con enlaces.
     * ------------------------------------------------------------------ */
    var grid = $('[data-products]');
    if (grid && window.history && window.history.pushState) {
        var cards = $all('.product', grid);
        var filterLinks = $all('[data-filter]');
        var countEl = $('[data-results-count]');
        var noResults = $('[data-no-results]');
        var menu = $('[data-menu]');
        var menuBtn = $('[data-menu-open]');
        var searchForm = $('[data-search-form]');
        var searchInput = $('[data-search-input]');
        var topbar = $('.topbar');

        var norm = function (s) {
            return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
        };
        var labels = {};
        filterLinks.forEach(function (a) { labels[a.getAttribute('data-filter')] = a.getAttribute('data-label'); });

        var readUrl = function () {
            var p = new URLSearchParams(window.location.search);
            var cat = p.get('categoria') || '';
            return { cat: labels.hasOwnProperty(cat) ? cat : '', q: (p.get('q') || '').slice(0, 60) };
        };
        var urlFor = function (st) {
            var p = new URLSearchParams();
            if (st.cat) p.set('categoria', st.cat);
            if (st.q) p.set('q', st.q);
            var qs = p.toString();
            return window.location.pathname + (qs ? '?' + qs : '') + '#productos';
        };

        var state = readUrl();

        /* Muestra solo los productos de la categoría y la búsqueda actuales. */
        var render = function () {
            var words = norm(state.q).split(' ').filter(Boolean);
            var shown = 0;

            grid.classList.remove('is-filtering');
            void grid.offsetWidth; // reinicia la animación corta de aparición
            grid.classList.add('is-filtering');

            cards.forEach(function (card) {
                var okCat = !state.cat || card.getAttribute('data-cat') === state.cat;
                var text = card.getAttribute('data-search') || '';
                var okQ = words.every(function (w) { return text.indexOf(w) !== -1; });
                card.hidden = !(okCat && okQ);
                if (!card.hidden) shown++;
            });

            filterLinks.forEach(function (a) {
                var on = !state.q && a.getAttribute('data-filter') === state.cat;
                a.classList.toggle('active', on);
                if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
            });

            if (countEl) {
                countEl.textContent = state.q
                    ? (shown === 1 ? '1 resultado' : shown + ' resultados') + ' para «' + state.q + '»'
                    : (shown === 1 ? '1 producto' : shown + ' productos') + (state.cat ? ' en ' + labels[state.cat] : '');
            }
            if (noResults) {
                noResults.hidden = shown > 0;
                var msg = $('p', noResults);
                if (msg) msg.textContent = 'No encontramos productos' + (state.q ? ' para «' + state.q + '»' : '') + '.';
            }
            if (searchInput && document.activeElement !== searchInput) searchInput.value = state.q;
        };

        /* Lleva la vista al inicio de la lista de productos (debajo de la barra fija). */
        var scrollToProducts = function () {
            var section = document.getElementById('productos');
            if (!section) return;
            var offset = (topbar ? topbar.offsetHeight : 0) + 8;
            var y = window.scrollY + section.getBoundingClientRect().top - offset;
            window.scrollTo({ top: Math.max(y, 0), behavior: 'smooth' });
        };

        /* ----- Menú ☰ ----- */
        var menuOpen = false;
        var setMenu = function (open) {
            if (!menu) return;
            menuOpen = open;
            menu.classList.toggle('is-open', open);
            menu.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.classList.toggle('no-scroll', open);
            if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var first = $('.drawer-link.active', menu) || $('.drawer-link', menu);
                if (first) setTimeout(function () { first.focus(); }, 50);
            } else if (menuBtn) {
                menuBtn.focus({ preventScroll: true });
            }
        };
        var openMenu = function () {
            if (menuOpen) return;
            // Se guarda en el historial para que "atrás" cierre el menú.
            window.history.pushState({ cat: state.cat, q: state.q, menu: true }, '', window.location.href.split('#')[0] + '#menu');
            setMenu(true);
        };
        var closeMenu = function () {
            if (!menuOpen) return;
            if (window.history.state && window.history.state.menu) window.history.back(); else setMenu(false);
        };

        if (menuBtn) menuBtn.addEventListener('click', function (e) { e.preventDefault(); openMenu(); });
        $all('[data-menu-close]').forEach(function (el) {
            el.addEventListener('click', function (e) { e.preventDefault(); closeMenu(); });
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menuOpen) closeMenu(); });

        // Enlaces de secciones del menú (Inicio, Cómo pedir, Contacto): cierran el menú y bajan a la sección.
        $all('[data-menu-go]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var target = document.querySelector(a.getAttribute('href'));
                window.history.replaceState({ cat: state.cat, q: state.q }, '', urlFor(state).split('#')[0]);
                setMenu(false);
                if (target) {
                    var y = window.scrollY + target.getBoundingClientRect().top - (topbar ? topbar.offsetHeight : 0);
                    window.scrollTo({ top: Math.max(y, 0), behavior: 'smooth' });
                }
            });
        });

        /* ----- Categorías (botones de la lista y del menú) ----- */
        var goCategory = function (cat) {
            state = { cat: cat, q: '' };
            if (menuOpen) {
                // Desde el menú: se reemplaza la entrada del menú, así "atrás" vuelve a lo que había antes.
                window.history.replaceState({ cat: state.cat, q: '' }, '', urlFor(state));
                setMenu(false);
            } else {
                window.history.pushState({ cat: state.cat, q: '' }, '', urlFor(state));
            }
            if (searchInput) searchInput.value = '';
            render();
            scrollToProducts();
        };
        filterLinks.forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey) return;
                e.preventDefault();
                goCategory(a.getAttribute('data-filter'));
            });
        });
        $all('[data-filter-reset]').forEach(function (a) {
            a.addEventListener('click', function (e) { e.preventDefault(); goCategory(''); });
        });

        /* ----- Buscador: filtra mientras se escribe ----- */
        if (searchInput) {
            var typing = false; // ¿ya se guardó en el historial esta búsqueda?
            var timer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var q = searchInput.value.trim().slice(0, 60);
                    var startNew = !typing && q !== '';
                    state = { cat: '', q: q };
                    var entry = { cat: '', q: q };
                    if (startNew) {
                        window.history.pushState(entry, '', urlFor(state));
                        typing = true;
                        scrollToProducts();
                    } else {
                        window.history.replaceState(entry, '', urlFor(state));
                    }
                    if (q === '') typing = false;
                    render();
                }, 120);
            });
            searchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                clearTimeout(timer);
                var q = searchInput.value.trim().slice(0, 60);
                state = { cat: '', q: q };
                window.history[typing ? 'replaceState' : 'pushState']({ cat: '', q: q }, '', urlFor(state));
                typing = false;
                render();
                searchInput.blur(); // cierra el teclado del celular
                scrollToProducts();
            });
            searchInput.addEventListener('blur', function () { typing = false; });
        }

        /* ----- Ventana de detalle del producto (cantidad + pedido completo por WhatsApp) ----- */
        var modal = $('[data-product-modal]');
        var modalCard = null;
        var openProduct = function () {};
        var closeProductUi = function () {};

        if (modal && typeof modal.showModal === 'function') {
            var pm = function (sel) { return $(sel, modal); };
            var qtyIn = pm('[data-pm-qty]');
            var locationIn = pm('[data-pm-location]');
            var orderBtn = pm('[data-pm-order]');
            var wa = modal.getAttribute('data-wa');
            var MAX_QTY = 99;

            var clampQty = function (n) {
                n = parseInt(n, 10);
                if (isNaN(n) || n < 1) n = 1;
                return Math.min(n, MAX_QTY);
            };

            var updateOrder = function () {
                if (!modalCard) return;
                var qty = clampQty(qtyIn.value);
                var price = parseInt(modalCard.getAttribute('data-price'), 10);
                var total = price * qty;
                pm('[data-pm-calc]').textContent = qty + ' × ' + money(price);
                pm('[data-pm-total]').textContent = money(total);
                pm('[data-pm-minus]').disabled = qty <= 1;
                pm('[data-pm-plus]').disabled = qty >= MAX_QTY;

                if (orderBtn && wa) {
                    var desc = (modalCard.getAttribute('data-desc') || '').trim();
                    var lines = [
                        'Hola, quiero hacer este pedido:',
                        '• Producto: ' + modalCard.getAttribute('data-name'),
                    ];
                    if (desc) lines.push('• Descripción: ' + desc);
                    lines.push('• Cantidad: ' + qty);
                    lines.push('• Precio unitario: ' + money(price));
                    lines.push('• Total: ' + money(total));
                    var location = locationIn ? locationIn.value.trim() : '';
                    if (location) lines.push('• Ubicación / dirección: ' + location);
                    orderBtn.href = 'https://wa.me/' + wa + '?text=' + encodeURIComponent(lines.join('\n'));
                }
            };

            var fill = function (card) {
                modalCard = card;
                var photo = card.getAttribute('data-photo');
                var img = pm('[data-pm-img]');
                img.hidden = !photo;
                if (photo) { img.src = photo; img.alt = card.getAttribute('data-name'); } else { img.removeAttribute('src'); }
                pm('[data-pm-cat]').textContent = card.getAttribute('data-category');
                pm('[data-pm-name]').textContent = card.getAttribute('data-name');
                pm('[data-pm-price]').textContent = money(parseInt(card.getAttribute('data-price'), 10));
                var desc = card.getAttribute('data-desc') || '';
                pm('[data-pm-desc]').textContent = desc;
                pm('[data-pm-desc]').hidden = desc.trim() === '';

                var status = card.getAttribute('data-status');
                var badge = pm('[data-pm-status]');
                badge.textContent = card.getAttribute('data-status-label');
                badge.className = 'badge ' + (status === 'disponible' ? 'badge-success' : status === 'ultimas' ? 'badge-warning' : 'badge-danger');

                var canOrder = card.getAttribute('data-can-order') === '1';
                pm('[data-pm-buy]').hidden = !canOrder;
                pm('[data-pm-soldout]').hidden = canOrder;
                modal.classList.toggle('is-sold-out', !canOrder);

                qtyIn.value = 1;
                if (locationIn) locationIn.value = '';
                updateOrder();
            };

            openProduct = function (card, push) {
                fill(card);
                if (push) {
                    // Se guarda en el historial: "atrás" cierra la ventana; el enlace se puede compartir.
                    window.history.pushState({ cat: state.cat, q: state.q, product: card.getAttribute('data-id') }, '',
                        window.location.pathname + window.location.search + '#producto-' + card.getAttribute('data-id'));
                }
                if (!modal.open) modal.showModal();
                document.body.classList.add('no-scroll');
            };
            closeProductUi = function () {
                if (modal.open) modal.close();
                document.body.classList.remove('no-scroll');
                modalCard = null;
            };
            var closeProduct = function () {
                if (window.history.state && window.history.state.product) window.history.back(); else closeProductUi();
            };

            // Tocar la foto o "Pedir" en una tarjeta abre el detalle.
            grid.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-open-product]');
                if (!trigger || e.metaKey || e.ctrlKey || e.shiftKey) return;
                e.preventDefault();
                openProduct(trigger.closest('.product'), true);
            });

            pm('[data-pm-close]').addEventListener('click', closeProduct);
            // Tocar fuera de la caja cierra.
            modal.addEventListener('click', function (e) { if (e.target === modal) closeProduct(); });
            // Tecla Esc
            modal.addEventListener('cancel', function (e) { e.preventDefault(); closeProduct(); });

            pm('[data-pm-minus]').addEventListener('click', function () { qtyIn.value = clampQty(qtyIn.value) - 1 || 1; updateOrder(); });
            pm('[data-pm-plus]').addEventListener('click', function () { qtyIn.value = clampQty(clampQty(qtyIn.value) + 1); updateOrder(); });
            qtyIn.addEventListener('input', updateOrder);
            qtyIn.addEventListener('blur', function () { qtyIn.value = clampQty(qtyIn.value); updateOrder(); });
            if (locationIn) locationIn.addEventListener('input', updateOrder);
            if (orderBtn) orderBtn.addEventListener('click', updateOrder);
        }

        var cardById = function (id) { return id ? document.getElementById('producto-' + id) : null; };

        /* ----- Botón "atrás" / "adelante" del navegador ----- */
        window.addEventListener('popstate', function (e) {
            var s = e.state || readUrl();
            setMenu(!!(s && s.menu));
            var next = { cat: labels.hasOwnProperty(s.cat || '') ? (s.cat || '') : '', q: s.q || '' };
            if (next.cat !== state.cat || next.q !== state.q) {
                state = next;
                if (searchInput) searchInput.value = state.q;
                render();
            }
            var card = cardById(s && s.product);
            if (card) openProduct(card, false); else closeProductUi();
        });

        // Estado inicial: si se abrió con #menu o con el enlace de un producto (#producto-12).
        var initialHash = window.location.hash;
        var linked = /^#producto-(\d+)$/.exec(initialHash);
        window.history.replaceState({ cat: state.cat, q: state.q }, '', initialHash === '#menu' || linked ? urlFor(state).split('#')[0] : window.location.href);
        if (linked && cardById(linked[1])) {
            window.history.pushState({ cat: state.cat, q: state.q, product: linked[1] }, '', window.location.pathname + window.location.search + initialHash);
            openProduct(cardById(linked[1]), false);
        }
    }

    /* ------------------------------------------------------------------
     * 3. Vista previa de la foto antes de guardar
     * ------------------------------------------------------------------ */
    $all('[data-photo-input]').forEach(function (input) {
        var preview = document.getElementById(input.getAttribute('data-photo-input'));
        var lastUrl = null;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (lastUrl) { URL.revokeObjectURL(lastUrl); lastUrl = null; }
            if (!file || !preview) return;
            lastUrl = URL.createObjectURL(file);
            preview.src = lastUrl;
            preview.hidden = false;
        });
    });

    /* ------------------------------------------------------------------
     * 4. Ventanas de confirmación con <dialog>
     *    Uso: <form data-confirm="¿Seguro...?" data-confirm-ok="Sí, eliminar">
     * ------------------------------------------------------------------ */
    var dialog = $('#confirm-dialog');
    if (dialog && typeof dialog.showModal === 'function') {
        var msg = $('[data-confirm-message]', dialog);
        var okBtn = $('[data-confirm-ok]', dialog);
        var pending = null;

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') return;
            e.preventDefault();
            pending = form;
            msg.textContent = form.getAttribute('data-confirm');
            okBtn.textContent = form.getAttribute('data-confirm-ok') || 'Sí, continuar';
            dialog.showModal();
        });

        dialog.addEventListener('close', function () {
            if (dialog.returnValue === 'ok' && pending) {
                pending.dataset.confirmed = '1';
                pending.submit();
            }
            pending = null;
        });
    }

    /* ------------------------------------------------------------------
     * Panel: aviso antes de cerrar la sesión por inactividad.
     * El servidor cierra la sesión si pasan X minutos sin pedir ninguna página.
     * 2 minutos antes se muestra un aviso con "Seguir conectado".
     * ------------------------------------------------------------------ */
    var idleMinutes = parseInt(document.body.getAttribute('data-idle-minutes'), 10);
    var idleBox = $('[data-idle-warning]');
    if (idleMinutes > 0 && idleBox) {
        var keepUrl = document.body.getAttribute('data-keepalive-url');
        var tokenMeta = $('meta[name="csrf-token"]');
        var countdown = $('[data-idle-countdown]', idleBox);
        var WARN_MS = Math.min(2 * 60 * 1000, idleMinutes * 60 * 1000 / 2);
        var deadline = Date.now() + idleMinutes * 60 * 1000;

        var tick = function () {
            var left = deadline - Date.now();
            if (left <= -3000) {
                // Ya pasó el tiempo: al recargar, el servidor cierra la sesión y muestra la pantalla de entrar.
                window.location.reload();
                return;
            }
            if (left <= WARN_MS) {
                var s = Math.max(0, Math.ceil(left / 1000));
                countdown.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
                idleBox.hidden = false;
            } else {
                idleBox.hidden = true;
            }
        };
        setInterval(tick, 1000);

        $('[data-idle-keep]', idleBox).addEventListener('click', function () {
            fetch(keepUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : '', 'Accept': 'application/json' }
            }).then(function (r) {
                if (!r.ok) { window.location.reload(); return; }
                deadline = Date.now() + idleMinutes * 60 * 1000;
                idleBox.hidden = true;
            }).catch(function () { window.location.reload(); });
        });
    }

    /* ------------------------------------------------------------------
     * 5. Evitar doble envío de formularios (doble toque en el celular)
     * ------------------------------------------------------------------ */
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        $all('button[type="submit"]', e.target).forEach(function (b) {
            setTimeout(function () { b.disabled = true; }, 0);
        });
    });
    window.addEventListener('pageshow', function () {
        $all('button[type="submit"]').forEach(function (b) { b.disabled = false; });
    });
})();
