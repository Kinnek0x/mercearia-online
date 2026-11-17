<?php
require_once '../includes/layout.php';
start_session_safe();
renderHead(t('footer.terms'), '../');
renderTopbar('../');
?>
    <main class="container" id="conteudo">
        <div class="page-head">
            <a href="../index.php" class="back-link"><?php echo e(t('common.back_shop')); ?></a>
            <h1><?php echo e(t('footer.terms')); ?></h1>
            <p><?php echo e(t('common.updated', ['date' => date('d/m/Y', filemtime(__DIR__ . '/../includes/legal/terms.' . currentLang() . '.php'))])); ?></p>
        </div>

        <?php require __DIR__ . '/../includes/legal/terms.' . currentLang() . '.php'; ?>
    </main>
<?php renderFooter('../'); ?>
</body>
</html>
