<?php
require_once '../includes/layout.php';
start_session_safe();

// Marca o início do checkout: a API recusa encomendas feitas "instantaneamente" (bots)
$_SESSION['checkout_started'] = time();

renderHead(t('checkout.title'), '../');
$estimate = storeText('DELIVERY_ESTIMATE');
$area = storeText('STORE_DELIVERY_AREA');
renderTopbar('../');
?>
    <div id="alert-container" role="status" aria-live="polite"></div>

    <main class="container" id="conteudo">
        <div class="page-head">
            <a href="../index.php" class="back-link"><?php echo e(t('common.continue')); ?></a>
            <h1><?php echo e(t('checkout.title')); ?></h1>
            <p><?php echo e(t('checkout.subtitle')); ?></p>
            <ol class="steps">
                <li class="done"><b>✓</b> <?php echo e(t('checkout.step_cart')); ?></li>
                <li class="current"><b>2</b> <?php echo e(t('checkout.step_data')); ?></li>
                <li><b>3</b> <?php echo e(t('checkout.step_done')); ?></li>
            </ol>
        </div>

        <div id="loading-state" class="loading-state">
            <div class="spinner"></div>
            <p><?php echo e(t('checkout.validating')); ?></p>
        </div>

        <div id="main-content" hidden>
            <div class="checkout-container">
                <section class="card">
                    <form id="checkout-form" novalidate>
                        <h3 class="form-section-title"><?php echo e(t('checkout.contact')); ?></h3>
                        <div class="form-group">
                            <label for="customer_name"><?php echo e(t('checkout.name')); ?> *</label>
                            <input type="text" id="customer_name" required minlength="3" maxlength="255" autocomplete="name" placeholder=" ">
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="customer_email"><?php echo e(t('checkout.email')); ?> *</label>
                                <input type="email" id="customer_email" required maxlength="255" autocomplete="email" placeholder=" ">
                            </div>
                            <div class="form-group">
                                <label for="customer_phone"><?php echo e(t('checkout.phone')); ?> *</label>
                                <input type="tel" id="customer_phone" required autocomplete="tel" placeholder="912 345 678"
                                       pattern="(\+351)?\s*[29](\s*\d){8}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customer_birth_date"><?php echo e(t('checkout.birth')); ?> *</label>
                            <input type="date" id="customer_birth_date" required>
                            <p class="form-hint"><?php echo e(t('checkout.birth_hint')); ?></p>
                        </div>

                        <h3 class="form-section-title"><?php echo e(t('checkout.address_title')); ?></h3>
                        <div class="form-group">
                            <label for="customer_address"><?php echo e(t('checkout.street')); ?> *</label>
                            <input type="text" id="customer_address" required minlength="5" maxlength="500"
                                   autocomplete="street-address" placeholder="<?php echo e(t('checkout.street_ph')); ?>">
                        </div>
                        <div class="form-grid-pc">
                            <div class="form-group">
                                <label for="customer_postal_code"><?php echo e(t('checkout.postal')); ?> *</label>
                                <input type="text" id="customer_postal_code" required inputmode="numeric" maxlength="8"
                                       pattern="\d{4}-\d{3}" autocomplete="postal-code" placeholder="1100-000">
                            </div>
                            <div class="form-group">
                                <label for="customer_city"><?php echo e(t('checkout.city')); ?> *</label>
                                <input type="text" id="customer_city" required maxlength="100" autocomplete="address-level2" placeholder="Lisboa">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customer_notes"><?php echo e(t('checkout.notes')); ?> <span class="muted"><?php echo e(t('checkout.optional')); ?></span></label>
                            <textarea id="customer_notes" rows="2" maxlength="500"
                                      placeholder="<?php echo e(t('checkout.notes_ph')); ?>"></textarea>
                        </div>

                        <!-- Campo-armadilha: invisível para pessoas, bots costumam preenchê-lo -->
                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <label class="checkbox">
                            <input type="checkbox" id="accept_terms" required>
                            <span><?php echo t('checkout.terms'); ?> *</span>
                        </label>

                        <button type="submit" class="btn btn-accent btn-lg btn-full" id="submit-order-btn">
                            <?php echo e(t('checkout.confirm')); ?>
                        </button>
                    </form>
                </section>

                <aside class="card order-summary">
                    <h3><?php echo e(t('checkout.summary')); ?></h3>
                    <div id="order-summary-items"></div>
                    <div class="summary-lines">
                        <div><span><?php echo e(t('common.subtotal')); ?></span><span id="order-subtotal"><?php echo formatPrice(0); ?></span></div>
                        <div><span><?php echo e(t('common.delivery')); ?></span><span id="order-delivery">—</span></div>
                    </div>
                    <div class="summary-total">
                        <span><?php echo e(t('common.total')); ?></span>
                        <strong id="order-total"><?php echo formatPrice(0); ?></strong>
                    </div>
                    <ul class="trust-list">
                        <li><?php echo e(t('checkout.trust_pay')); ?></li>
                        <li><?php echo e(t('checkout.trust_call')); ?></li>
                        <li><?php echo e(t('checkout.trust_ship', ['estimate' => $estimate, 'area' => $area])); ?></li>
                    </ul>
                </aside>
            </div>
        </div>
    </main>

<?php renderFooter('../'); ?>

    <script>
        const CART_KEY = 'mercearia_cart';
        const $ = (id) => document.getElementById(id);
        const I18N = window.I18N || { lang: 'pt', strings: {} };
        const t = (key, params = {}) => Object.entries(params)
            .reduce((text, [k, v]) => text.split(`{${k}}`).join(v), I18N.strings[key] ?? key);

        function getCart() {
            try {
                return JSON.parse(localStorage.getItem(CART_KEY) || '{}') || {};
            } catch (e) {
                return {};
            }
        }

        // Data local no formato AAAA-MM-DD (toISOString() usa UTC e podia dar o dia errado)
        function toLocalISODate(d) {
            const pad = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }

        const priceFormat = new Intl.NumberFormat(t('locale'), { style: 'currency', currency: 'EUR' });
        const money = (v) => priceFormat.format(Number(v) || 0);

        document.addEventListener('DOMContentLoaded', () => {
            const today = new Date();
            const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
            $('customer_birth_date').max = toLocalISODate(maxDate);
            $('customer_birth_date').min = '1900-01-01';

            // Formatar o código postal enquanto se escreve (0000-000)
            $('customer_postal_code').addEventListener('input', (e) => {
                const digits = e.target.value.replace(/\D/g, '').slice(0, 7);
                e.target.value = digits.length > 4 ? digits.slice(0, 4) + '-' + digits.slice(4) : digits;
            });

            $('checkout-form').addEventListener('submit', submitOrder);
            loadCheckoutData();
        });

        function backToShop(message, type = 'warning', delay = 3000) {
            showAlert(type, message);
            setTimeout(() => { window.location.href = '../index.php'; }, delay);
        }

        async function loadCheckoutData() {
            const cart = getCart();

            if (Object.keys(cart).length === 0) {
                backToShop(t('co_empty'));
                return;
            }

            try {
                const response = await fetch('../api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'validate_cart', cart })
                });
                const result = await response.json();

                if (result.success) {
                    displayOrderSummary(result);
                    $('loading-state').hidden = true;
                    $('main-content').hidden = false;
                    $('customer_name').focus();
                } else {
                    backToShop((result.message || t('co_problem')) + t('co_back'), 'error', 5000);
                }
            } catch (error) {
                console.error('Erro ao validar carrinho:', error);
                backToShop(t('co_conn_back'), 'error');
            }
        }

        function displayOrderSummary(result) {
            $('order-summary-items').innerHTML = result.items.map(item => `
                <div class="summary-item">
                    <span>${escapeHtml(item.display_name || item.product_name)} <small>× ${item.quantity} ${escapeHtml(item.unit || '')}</small></span>
                    <span>${money(item.subtotal)}</span>
                </div>`).join('');
            $('order-subtotal').textContent = money(result.subtotal);
            $('order-delivery').textContent = Number(result.delivery_fee) === 0 ? t('free') : money(result.delivery_fee);
            $('order-total').textContent = money(result.total);
        }

        async function submitOrder(event) {
            event.preventDefault();

            const submitBtn = $('submit-order-btn');
            const originalText = submitBtn.textContent;
            const reset = () => { submitBtn.disabled = false; submitBtn.textContent = originalText; };

            submitBtn.disabled = true;
            submitBtn.textContent = t('co_processing');

            const formData = {
                name: $('customer_name').value.trim(),
                email: $('customer_email').value.trim(),
                phone: $('customer_phone').value.trim(),
                birth_date: $('customer_birth_date').value,
                address: $('customer_address').value.trim(),
                postal_code: $('customer_postal_code').value.trim(),
                city: $('customer_city').value.trim(),
                notes: $('customer_notes').value.trim(),
                website: $('website').value
            };

            const errors = validateCheckoutForm(formData);
            if (errors.length > 0) {
                showAlert('error', errors.join(' · '));
                reset();
                return;
            }

            const cart = getCart();
            if (Object.keys(cart).length === 0) {
                showAlert('error', t('co_empty'));
                reset();
                return;
            }

            try {
                const response = await fetch('../api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer: formData, cart, accept_terms: $('accept_terms').checked })
                });
                const result = await response.json();

                if (result.success) {
                    localStorage.removeItem(CART_KEY);
                    window.location.href = `success.php?order_id=${encodeURIComponent(result.order_id)}`;
                } else {
                    showAlert('error', result.message || t('co_error'));
                    reset();
                }
            } catch (error) {
                console.error('Erro ao submeter encomenda:', error);
                showAlert('error', t('connection'));
                reset();
            }
        }

        // Idade exata
        function calculateAge(isoDate) {
            const [y, m, d] = isoDate.split('-').map(Number);
            const today = new Date();
            let age = today.getFullYear() - y;
            const beforeBirthday = (today.getMonth() + 1 < m) || (today.getMonth() + 1 === m && today.getDate() < d);
            if (beforeBirthday) age--;
            return age;
        }

        function validateCheckoutForm(f) {
            const errors = [];
            const phone = f.phone.replace(/[^\d+]/g, '');

            if (f.name.length < 3) errors.push(t('v_name'));
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) errors.push(t('v_email'));
            if (!/^(\+351)?(9[1236]\d{7}|2\d{8})$/.test(phone)) errors.push(t('v_phone'));

            if (!f.birth_date) {
                errors.push(t('v_birth_req'));
            } else if (!/^\d{4}-\d{2}-\d{2}$/.test(f.birth_date) || f.birth_date > toLocalISODate(new Date())) {
                errors.push(t('v_birth'));
            } else if (calculateAge(f.birth_date) < 18) {
                errors.push(t('v_age'));
            }

            if (f.address.length < 5) errors.push(t('v_address'));
            if (!/^\d{4}-\d{3}$/.test(f.postal_code)) errors.push(t('v_postal'));
            if (f.city.length < 2) errors.push(t('v_city'));
            if (!$('accept_terms').checked) errors.push(t('v_terms'));

            return errors;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function showAlert(type, message) {
            const container = $('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            const span = document.createElement('span');
            span.textContent = message;
            const close = document.createElement('button');
            close.className = 'alert-close';
            close.setAttribute('aria-label', t('close'));
            close.textContent = '×';
            close.onclick = () => alertDiv.remove();
            alertDiv.append(span, close);

            container.innerHTML = '';
            container.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 7000);
        }
    </script>
</body>
</html>
