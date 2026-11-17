<?php
require_once __DIR__ . '/../includes/layout.php';
http_response_code(404);

// Caminho até à raiz do site: definido pelo router.php, ou calculado a partir de /pages/404.php (Apache)
$base = defined('SITE_BASE') ? SITE_BASE : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/') . '/';

renderHead(t('404.title'), $base);
renderTopbar($base);
?>
    <main class="container" id="conteudo">
        <div class="not-found">
            <div class="code">404</div>
            <h1><?php echo e(t('404.heading')); ?></h1>
            <p><?php echo e(t('404.text')); ?></p>
            <a href="<?php echo e($base); ?>index.php" class="btn btn-lg"><?php echo e(t('common.back_shop')); ?></a>
        </div>
    </main>
<?php renderFooter($base); ?>
</body>
</html>
