/**
 * JavaScript principal da Mercearia Online (página da loja)
 */

const CART_KEY = 'mercearia_cart';
const LOW_STOCK = 5;
const DEFAULT_IMAGE = 'images/products/default.jpg';

let cart = {};      // { productId: quantidade }
let products = [];  // lista vinda da API
let activeCategory = 'all';
// Regras da loja (substituídas pelas que vêm da API; o servidor recalcula sempre os valores finais)
let settings = { delivery_fee: 3.5, free_delivery_from: 30, min_order: 10, max_qty: 20, categories: [] };

const $ = (id) => document.getElementById(id);

// Traduções injetadas pelo PHP (includes/i18n.php). t('chave', { param: valor })
const I18N = window.I18N || { lang: 'pt', strings: {} };
function t(key, params = {}) {
    let text = I18N.strings[key] ?? key;
    for (const [name, value] of Object.entries(params)) text = text.split(`{${name}}`).join(value);
    return text;
}

document.addEventListener('DOMContentLoaded', () => {
    cart = loadCart();
    bindEvents();
    updateCartDisplay();
    loadProducts();
});

/* ---------------- Dados ---------------- */

async function loadProducts() {
    const container = $('products-container');
    try {
        const response = await fetch('api/products.php');
        const data = await response.json();

        if (!data.success) throw new Error(data.message || t('load_error'));

        // Normalizar tipos (o PHP pode devolver números como texto)
        settings = { ...settings, ...(data.settings || {}) };
        products = data.products.map(p => ({
            ...p,
            id: Number(p.id),
            price: Number(p.price),
            quantity: Number(p.quantity),
        }));

        reconcileCart();
        renderCategoryChips();
        displayProducts();
        // CORREÇÃO: antes o carrinho era desenhado antes de os produtos chegarem e aparecia vazio
        updateCartDisplay();
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        container.innerHTML = `
            <div class="empty-state">
                <strong>${t('load_error')}</strong>
                ${t('load_error_hint')}
                <div style="margin-top:14px"><button class="btn btn-secondary btn-sm" onclick="location.reload()">${t('retry')}</button></div>
            </div>`;
        $('products-summary').textContent = '';
    } finally {
        container.setAttribute('aria-busy', 'false');
    }
}

// Remover do carrinho produtos que já não existem e limitar ao stock atual
function reconcileCart() {
    let changed = false;
    Object.keys(cart).forEach(id => {
        const product = findProduct(id);
        if (!product || product.quantity === 0) {
            delete cart[id];
            changed = true;
        } else if (cart[id] > maxFor(product)) {
            cart[id] = maxFor(product);
            changed = true;
        }
    });
    if (changed) {
        saveCart();
        showAlert('info', t('cart_adjusted'));
    }
}

// Máximo que se pode pôr no carrinho: o stock, até ao limite por encomenda
function maxFor(product) {
    return Math.min(product.quantity, settings.max_qty);
}


function findProduct(id) {
    id = Number(id);
    return products.find(p => p.id === id);
}

/* ---------------- Produtos ---------------- */

function getVisibleProducts() {
    const term = normalize($('search-input').value);
    const sort = $('sort-select').value;

    const list = products.filter(p =>
        (activeCategory === 'all' || p.category === activeCategory) &&
        (!term || normalize(p.name).includes(term) || normalize(p.category_label || '').includes(term))
    );

    const sorters = {
        'name':       (a, b) => a.name.localeCompare(b.name, I18N.lang),
        'price-asc':  (a, b) => a.price - b.price,
        'price-desc': (a, b) => b.price - a.price,
        'stock':      (a, b) => (b.quantity > 0) - (a.quantity > 0) || a.name.localeCompare(b.name, I18N.lang),
    };
    return list.sort(sorters[sort] || sorters.name);
}

function renderCategoryChips() {
    const used = new Set(products.map(p => p.category));
    const cats = [{ key: 'all', label: t('all') }, ...settings.categories.filter(c => used.has(c.key))];
    $('category-chips').innerHTML = cats.map(c => `
        <button type="button" class="chip" data-category="${escapeHtml(c.key)}" aria-pressed="${c.key === activeCategory}">
            ${escapeHtml(c.label)}${c.key === 'all' ? '' : ` <small>${products.filter(p => p.category === c.key).length}</small>`}
        </button>`).join('');
}

function displayProducts() {
    const container = $('products-container');
    const list = getVisibleProducts();

    const inStock = products.filter(p => p.quantity > 0).length;
    $('products-summary').textContent = t('summary', { total: products.length, available: inStock });

    if (list.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <strong>${t('none_found')}</strong>
                ${t('none_found_hint')}
            </div>`;
        return;
    }

    container.innerHTML = list.map(createProductCard).join('');
}

function createProductCard(product) {
    const qty = cart[product.id] || 0;
    const isOut = product.quantity === 0;
    const isLow = !isOut && product.quantity <= LOW_STOCK;
    const name = escapeHtml(product.name);

    let badge = '';
    if (isOut) badge = `<span class="badge badge-out">${t('sold_out')}</span>`;
    else if (isLow) badge = `<span class="badge badge-low">${t('last_units', { n: product.quantity })}</span>`;

    const max = maxFor(product);
    const stockText = isOut ? t('no_stock') : escapeHtml(product.category_label || '');

    return `
        <article class="product-card ${isOut ? 'is-out' : ''} ${qty ? 'in-cart' : ''}" data-id="${product.id}">
            ${badge}
            <div class="product-image">
                <img src="${escapeHtml(product.image || DEFAULT_IMAGE)}" alt="${name}" loading="lazy"
                     onerror="this.onerror=null;this.src='${DEFAULT_IMAGE}'">
            </div>
            <div class="product-body">
                <h3 class="product-name">${name}</h3>
                <div class="product-stock">${stockText}</div>
                <div class="product-footer">
                    <div class="product-price">${formatPrice(product.price)}<small> / ${escapeHtml(product.unit_label || product.unit)}</small></div>
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn minus" data-action="dec"
                                aria-label="${t('aria_dec', { name })}" ${isOut || qty === 0 ? 'disabled' : ''}>−</button>
                        <input type="number" class="quantity-input" id="qty_${product.id}" inputmode="numeric"
                               value="${qty}" min="0" max="${max}"
                               aria-label="${t('aria_qty', { name })}" ${isOut ? 'disabled' : ''}>
                        <button type="button" class="quantity-btn plus" data-action="inc"
                                aria-label="${t('aria_inc', { name })}" ${isOut || qty >= max ? 'disabled' : ''}>+</button>
                    </div>
                </div>
            </div>
        </article>`;
}

// Atualizar só o cartão alterado (evita redesenhar a grelha toda)
function refreshCard(productId) {
    const product = findProduct(productId);
    const card = document.querySelector(`.product-card[data-id="${productId}"]`);
    if (!product || !card) return;
    const focusedAction = card.contains(document.activeElement) ? document.activeElement.dataset.action : null;
    const tmp = document.createElement('div');
    tmp.innerHTML = createProductCard(product).trim();
    const newCard = tmp.firstChild;
    card.replaceWith(newCard);
    // Manter o foco no mesmo botão (navegação por teclado)
    if (focusedAction) {
        const btn = newCard.querySelector(`[data-action="${focusedAction}"]`);
        (btn && !btn.disabled ? btn : newCard.querySelector('.quantity-input'))?.focus();
    }
}

/* ---------------- Carrinho ---------------- */

function changeQuantity(productId, change) {
    const current = cart[productId] || 0;
    updateQuantity(productId, current + change);
}

function updateQuantity(productId, quantity) {
    const product = findProduct(productId);
    if (!product) return;

    quantity = Math.max(0, parseInt(quantity, 10) || 0);

    if (quantity > product.quantity) {
        showAlert('warning', t('only_stock', { n: product.quantity, unit: product.unit_label, name: product.name }));
        quantity = product.quantity;
    }
    if (quantity > settings.max_qty) {
        showAlert('warning', t('max_qty', { n: settings.max_qty }));
        quantity = settings.max_qty;
    }

    if (quantity === 0) delete cart[productId];
    else cart[productId] = quantity;

    saveCart();
    refreshCard(productId);
    updateCartDisplay();
}

function updateCartDisplay() {
    const itemsEl = $('cart-items');
    const ids = Object.keys(cart);
    let total = 0;
    let count = 0;

    const rows = ids.map(id => {
        const product = findProduct(id);
        if (!product) return '';
        const qty = cart[id];
        const subtotal = product.price * qty;
        total += subtotal;
        count += qty;
        return `
            <div class="cart-item">
                <img src="${escapeHtml(product.image || DEFAULT_IMAGE)}" alt="" onerror="this.onerror=null;this.src='${DEFAULT_IMAGE}'">
                <div>
                    <div class="cart-item-name">${escapeHtml(product.name)}</div>
                    <div class="cart-item-meta">${qty} ${escapeHtml(product.unit_label || product.unit)} × ${formatPrice(product.price)}</div>
                </div>
                <div>
                    <div class="cart-item-price">${formatPrice(subtotal)}</div>
                    <button type="button" class="cart-item-remove" data-remove="${product.id}">${t('remove')}</button>
                </div>
            </div>`;
    }).join('');

    if (rows) {
        itemsEl.innerHTML = rows;
    } else if (ids.length && products.length === 0) {
        itemsEl.innerHTML = `<div class="cart-empty">${t('cart_loading')}</div>`;
    } else {
        itemsEl.innerHTML = `<div class="cart-empty"><span>🧺</span>${t('cart_empty')}</div>`;
    }

    const empty = count === 0;
    const subtotal = Math.round(total * 100) / 100;
    const fee = empty ? 0 : (subtotal >= settings.free_delivery_from ? 0 : settings.delivery_fee);
    const belowMin = !empty && subtotal < settings.min_order;

    $('cart-subtotal').textContent = formatPrice(subtotal);
    $('cart-delivery').textContent = empty ? '—' : (fee === 0 ? t('free') : formatPrice(fee));
    $('cart-total').textContent = formatPrice(subtotal + fee);

    // Barra de progresso para portes grátis
    const progress = $('delivery-progress');
    progress.hidden = empty;
    if (!empty) {
        const missing = settings.free_delivery_from - subtotal;
        $('delivery-progress-text').innerHTML = missing > 0
            ? t('missing_free', { amount: formatPrice(missing) })
            : t('got_free');
        $('delivery-progress-bar').style.width = Math.min(100, subtotal / settings.free_delivery_from * 100) + '%';
        progress.classList.toggle('is-free', missing <= 0);
    }

    // Valor mínimo de encomenda
    const minNote = $('cart-min-note');
    minNote.hidden = !belowMin;
    if (belowMin) {
        minNote.textContent = t('min_note', { min: formatPrice(settings.min_order), missing: formatPrice(settings.min_order - subtotal) });
    }

    const countEl = $('cart-count');
    countEl.textContent = count;
    countEl.dataset.count = count;

    $('checkout-btn').disabled = empty || belowMin;
    $('clear-cart-btn').disabled = empty;
}

async function proceedToCheckout() {
    if (Object.keys(cart).length === 0) {
        showAlert('warning', t('empty_warning'));
        return;
    }

    const btn = $('checkout-btn');
    btn.disabled = true;
    btn.textContent = t('checking');

    const stockCheck = await checkCartStock();
    if (!stockCheck.success) {
        showAlert('error', stockCheck.message);
        await loadProducts(); // atualizar stock
        btn.textContent = t('checkout');
        updateCartDisplay();
        return;
    }

    saveCart();
    window.location.href = 'pages/checkout.php';
}

async function checkCartStock() {
    try {
        const response = await fetch('api/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check_stock', cart })
        });
        return await response.json();
    } catch (error) {
        console.error('Erro ao verificar stock:', error);
        return { success: false, message: t('connection') };
    }
}

// Esvaziar sem pedir confirmação: mostra "Desfazer" durante alguns segundos
// (melhor experiência do que uma janela de confirmação do browser)
function clearCart() {
    const previous = { ...cart };
    cart = {};
    saveCart();
    displayProducts();
    updateCartDisplay();
    showAlert('info', t('cleared'), { label: t('undo'), onClick: () => restoreCart(previous) });
}

function removeFromCart(productId) {
    const product = findProduct(productId);
    const previous = { ...cart };
    updateQuantity(productId, 0);
    if (product) {
        showAlert('info', t('removed', { name: product.name }), { label: t('undo'), onClick: () => restoreCart(previous) });
    }
}

function restoreCart(previous) {
    cart = previous;
    reconcileCart();
    saveCart();
    displayProducts();
    updateCartDisplay();
}

function saveCart() {
    try {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch (error) {
        console.error('Erro ao guardar carrinho:', error);
    }
}

function loadCart() {
    try {
        // Compatibilidade com a versão anterior (sessionStorage 'cart' / localStorage 'cart_backup')
        const raw = localStorage.getItem(CART_KEY)
            || sessionStorage.getItem('cart')
            || localStorage.getItem('cart_backup');
        sessionStorage.removeItem('cart');
        localStorage.removeItem('cart_backup');

        const parsed = raw ? JSON.parse(raw) : {};
        const clean = {};
        Object.entries(parsed || {}).forEach(([id, qty]) => {
            qty = parseInt(qty, 10);
            if (qty > 0) clean[id] = qty;
        });
        return clean;
    } catch (error) {
        console.error('Erro ao carregar carrinho:', error);
        return {};
    }
}

/* ---------------- Eventos ---------------- */

function bindEvents() {
    const grid = $('products-container');

    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = Number(btn.closest('.product-card').dataset.id);
        changeQuantity(id, btn.dataset.action === 'inc' ? 1 : -1);
    });

    grid.addEventListener('change', (e) => {
        if (!e.target.classList.contains('quantity-input')) return;
        const id = Number(e.target.closest('.product-card').dataset.id);
        updateQuantity(id, e.target.value);
    });

    $('cart-items').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (btn) removeFromCart(Number(btn.dataset.remove));
    });

    $('category-chips').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-category]');
        if (!chip) return;
        activeCategory = chip.dataset.category;
        renderCategoryChips();
        displayProducts();
    });

    $('search-input').addEventListener('input', debounce(displayProducts, 150));
    $('sort-select').addEventListener('change', displayProducts);

    $('checkout-btn').addEventListener('click', proceedToCheckout);
    $('clear-cart-btn').addEventListener('click', clearCart);

    // Gaveta do carrinho (ecrãs pequenos)
    const toggle = $('cart-toggle');
    const setOpen = (open) => {
        document.body.classList.toggle('cart-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(true));
    $('cart-close').addEventListener('click', () => setOpen(false));
    $('cart-backdrop').addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
}

/* ---------------- Utilitários ---------------- */

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML.replace(/"/g, '&quot;');
}

function normalize(text) {
    return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

// Formato do idioma: 1,50 € (PT) / €1.50 (EN)
const priceFormat = new Intl.NumberFormat(t('locale'), { style: 'currency', currency: 'EUR' });
function formatPrice(price) {
    return priceFormat.format(Number(price) || 0);
}

// action (opcional): { label, onClick } -> botão no alerta (ex.: "Desfazer")
function showAlert(type, message, action = null) {
    const container = $('alert-container');
    if (!container) { alert(message); return; }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    const span = document.createElement('span');
    span.textContent = message; // textContent: evita injeção de HTML
    alertDiv.appendChild(span);

    if (action) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'alert-action';
        btn.textContent = action.label;
        btn.onclick = () => { alertDiv.remove(); action.onClick(); };
        alertDiv.appendChild(btn);
    }

    const close = document.createElement('button');
    close.className = 'alert-close';
    close.setAttribute('aria-label', t('close'));
    close.textContent = '×';
    close.onclick = () => alertDiv.remove();
    alertDiv.appendChild(close);

    container.innerHTML = '';
    container.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), action ? 7000 : 5000);
}

function debounce(func, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}
