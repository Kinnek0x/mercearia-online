<?php

// Partes comuns das páginas públicas (cabeçalho, barra superior e rodapé).
// $base = caminho relativo até à raiz do site ('' na raiz, '../' em /pages).

require_once __DIR__ . '/functions.php';

function renderHead(string $title, string $base = '', string $description = ''): void {
    $description = $description ?: t('meta.description', [
        'estimate' => storeText('DELIVERY_ESTIMATE'),
        'area'     => storeText('STORE_DELIVERY_AREA'),
    ]);
    $fullTitle = $title ? "$title — " . STORE_NAME : STORE_NAME . ' — ' . storeText('STORE_TAGLINE');
    ?>
<!DOCTYPE html>
<html lang="<?php echo htmlLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($fullTitle); ?></title>
    <meta name="description" content="<?php echo e($description); ?>">
    <meta name="theme-color" content="#1f5e3b">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo e($fullTitle); ?>">
    <meta property="og:description" content="<?php echo e($description); ?>">
    <meta property="og:locale" content="<?php echo currentLang() === 'en' ? 'en_GB' : 'pt_PT'; ?>">
    <link rel="icon" href="<?php echo $base; ?>images/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500..700;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base; ?>css/style.css">
    <script>window.I18N = <?php echo jsTranslations(); ?>;</script>
</head>
<body>
    <a href="#conteudo" class="skip-link"><?php echo e(t('a11y.skip')); ?></a>
    <?php
}

function renderBrand(string $base = ''): void {
    ?>
    <a href="<?php echo $base; ?>index.php" class="brand">
        <span class="brand-mark" aria-hidden="true">🌿</span>
        <span><?php echo e(STORE_NAME); ?><small><?php echo e(storeText('STORE_TAGLINE')); ?></small></span>
    </a>
    <?php
}

// Seletor de idioma PT | EN
function renderLangSwitch(): void {
    ?>
    <nav class="lang-switch" aria-label="<?php echo e(t('lang.switch')); ?>">
        <?php foreach (SUPPORTED_LANGS as $code => $name): ?>
            <a href="<?php echo e(langUrl($code)); ?>" hreflang="<?php echo $code; ?>" lang="<?php echo $code; ?>"
               title="<?php echo e($name); ?>" <?php echo currentLang() === $code ? 'aria-current="true"' : ''; ?>><?php echo strtoupper($code); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

// Barra superior simples (checkout, sucesso, páginas legais)
function renderTopbar(string $base = ''): void {
    ?>
    <header class="topbar">
        <div class="container topbar-inner">
            <?php renderBrand($base); ?>
            <div class="topbar-actions">
                <a href="tel:<?php echo e(preg_replace('/\s+/', '', STORE_PHONE)); ?>" class="btn btn-ghost btn-sm topbar-phone">📞 <?php echo e(STORE_PHONE); ?></a>
                <?php renderLangSwitch(); ?>
            </div>
        </div>
    </header>
    <?php
}

// Faixa com informação de entrega
function renderDeliveryStrip(): void {
    ?>
    <div class="delivery-strip">
        <div class="container">
            <?php echo t('strip.free', ['amount' => '<strong>' . e(formatPrice(FREE_DELIVERY_FROM)) . '</strong>']); ?>
            <span class="strip-extra"><span class="strip-sep">·</span> <?php echo e(t('strip.delivery', ['estimate' => storeText('DELIVERY_ESTIMATE'), 'area' => storeText('STORE_DELIVERY_AREA')])); ?></span>
        </div>
    </div>
    <?php
}

function renderFooter(string $base = ''): void {
    $phoneLink = preg_replace('/\s+/', '', STORE_PHONE);
    ?>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <?php renderBrand($base); ?>
                <p class="footer-about"><?php echo e(t('footer.about')); ?></p>
            </div>
            <div>
                <h4><?php echo e(t('footer.contacts')); ?></h4>
                <ul>
                    <li><a href="tel:<?php echo e($phoneLink); ?>"><?php echo e(STORE_PHONE); ?></a></li>
                    <li><a href="mailto:<?php echo e(STORE_EMAIL); ?>"><?php echo e(STORE_EMAIL); ?></a></li>
                    <li><?php echo e(STORE_ADDRESS); ?></li>
                    <li><?php echo e(storeText('STORE_HOURS')); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php echo e(t('footer.orders')); ?></h4>
                <ul>
                    <li><?php echo e(t('footer.delivery_in', ['estimate' => storeText('DELIVERY_ESTIMATE')])); ?></li>
                    <li><?php echo e(t('footer.fees', ['fee' => formatPrice(DELIVERY_FEE), 'free' => formatPrice(FREE_DELIVERY_FROM)])); ?></li>
                    <li><?php echo e(t('footer.min', ['min' => formatPrice(MIN_ORDER_TOTAL)])); ?></li>
                    <li><?php echo e(t('footer.payment')); ?></li>
                </ul>
            </div>
            <div>
                <h4><?php echo e(t('footer.info')); ?></h4>
                <ul>
                    <li><a href="<?php echo $base; ?>pages/termos.php"><?php echo e(t('footer.terms')); ?></a></li>
                    <li><a href="<?php echo $base; ?>pages/privacidade.php"><?php echo e(t('footer.privacy')); ?></a></li>
                    <li><a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener noreferrer"><?php echo e(t('footer.complaints')); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            <span>© <?php echo date('Y'); ?> <?php echo e(STORE_NAME); ?> · NIF <?php echo e(STORE_NIF); ?></span>
            <span><?php echo e(t('footer.vat')); ?></span>
        </div>
    </footer>
    <?php
}
