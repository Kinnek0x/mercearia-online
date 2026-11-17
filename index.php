<?php
require_once 'includes/layout.php';
start_session_safe();

$estimate = storeText('DELIVERY_ESTIMATE');

renderHead('');
renderDeliveryStrip();
?>
    <header class="topbar">
        <div class="container topbar-inner">
            <?php renderBrand(); ?>

            <label class="search">
                <span class="sr-only"><?php echo e(t('shop.search_label')); ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="search-input" placeholder="<?php echo e(t('shop.search')); ?>" autocomplete="off">
            </label>

            <div class="topbar-actions">
                <?php renderLangSwitch(); ?>
                <button type="button" class="btn btn-secondary cart-toggle" id="cart-toggle" aria-controls="cart-sidebar" aria-expanded="false">
                    <span aria-hidden="true">🛒</span><span class="cart-toggle-text"><?php echo e(t('shop.cart')); ?></span>
                    <span class="cart-count" id="cart-count" data-count="0">0</span>
                </button>
            </div>
        </div>
    </header>

    <div id="alert-container" role="status" aria-live="polite"></div>

    <main class="container" id="conteudo">
        <section class="hero">
            <div>
                <span class="hero-eyebrow"><?php echo e(t('hero.eyebrow')); ?></span>
                <h1><?php echo t('hero.title'); ?></h1>
                <p><?php echo e(t('hero.text', ['estimate' => $estimate])); ?></p>
                <a href="#produtos" class="btn btn-accent btn-lg hero-cta"><?php echo e(t('hero.cta')); ?></a>
            </div>
            <ul class="hero-perks">
                <li><span aria-hidden="true">🚚</span> <?php echo e(t('hero.perk_free', ['amount' => formatPrice(FREE_DELIVERY_FROM)])); ?></li>
                <li><span aria-hidden="true">🥬</span> <?php echo e(t('hero.perk_fresh')); ?></li>
                <li><span aria-hidden="true">💶</span> <?php echo e(t('hero.perk_pay')); ?></li>
            </ul>
        </section>

        <div class="shop-layout" id="produtos">
            <section class="products-section" aria-labelledby="products-title">
                <div class="section-head">
                    <div>
                        <h2 id="products-title"><?php echo e(t('shop.title')); ?></h2>
                        <p id="products-summary"><?php echo e(t('shop.loading')); ?></p>
                    </div>
                    <label>
                        <span class="sr-only"><?php echo e(t('shop.sort_label')); ?></span>
                        <select id="sort-select" class="select">
                            <option value="name"><?php echo e(t('shop.sort_name')); ?></option>
                            <option value="price-asc"><?php echo e(t('shop.sort_price_asc')); ?></option>
                            <option value="price-desc"><?php echo e(t('shop.sort_price_desc')); ?></option>
                            <option value="stock"><?php echo e(t('shop.sort_stock')); ?></option>
                        </select>
                    </label>
                </div>

                <div class="category-chips" id="category-chips" role="group" aria-label="<?php echo e(t('shop.filter_label')); ?>"></div>

                <div class="products-grid" id="products-container" aria-busy="true">
                    <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
                    <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>
                </div>
            </section>

            <div class="cart-backdrop" id="cart-backdrop"></div>

            <aside class="cart-sidebar" id="cart-sidebar" aria-labelledby="cart-title">
                <div class="cart-header">
                    <h2 class="cart-title" id="cart-title"><?php echo e(t('cart.title')); ?></h2>
                    <button type="button" class="btn btn-ghost btn-icon cart-close" id="cart-close" aria-label="<?php echo e(t('cart.close')); ?>">✕</button>
                </div>

                <div class="cart-items" id="cart-items"></div>

                <div class="delivery-progress" id="delivery-progress" hidden>
                    <p id="delivery-progress-text"></p>
                    <div class="progress"><span id="delivery-progress-bar"></span></div>
                </div>

                <div class="cart-lines">
                    <div><span><?php echo e(t('common.subtotal')); ?></span><span id="cart-subtotal"><?php echo formatPrice(0); ?></span></div>
                    <div><span><?php echo e(t('common.delivery')); ?></span><span id="cart-delivery">—</span></div>
                </div>
                <div class="cart-total">
                    <span><?php echo e(t('common.total')); ?></span>
                    <strong id="cart-total"><?php echo formatPrice(0); ?></strong>
                </div>

                <p class="cart-note" id="cart-min-note" hidden></p>

                <div class="cart-actions">
                    <button type="button" class="btn btn-accent btn-lg btn-full" id="checkout-btn" disabled>
                        <?php echo e(t('cart.checkout')); ?>
                    </button>
                    <button type="button" class="btn btn-ghost btn-full" id="clear-cart-btn" disabled>
                        <?php echo e(t('cart.clear')); ?>
                    </button>
                </div>
            </aside>
        </div>

        <section class="how-it-works" aria-labelledby="how-title">
            <h2 id="how-title"><?php echo e(t('how.title')); ?></h2>
            <ol>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <li><span><?php echo $i; ?></span><div><strong><?php echo e(t("how.$i.title")); ?></strong><p><?php echo e(t("how.$i.text", ['estimate' => $estimate])); ?></p></div></li>
                <?php endfor; ?>
            </ol>
        </section>
    </main>

<?php renderFooter(); ?>

    <script src="js/script.js"></script>
</body>
</html>
