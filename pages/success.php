<?php
require_once '../includes/layout.php';
start_session_safe();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order = null;

// Só quem fez a encomenda (nesta sessão) ou um admin pode ver os dados pessoais
$myOrders = $_SESSION['my_orders'] ?? [];
if ($orderId && (in_array($orderId, $myOrders, true) || isAdminAuthenticated())) {
    $order = getOrderWithItems($orderId);
}

// Passos da linha temporal conforme o estado
$stepKeys = ['pendente', 'confirmada', 'enviada', 'entregue'];
$currentStep = $order ? array_search($order['status'], $stepKeys, true) : false;
$estimate = storeText('DELIVERY_ESTIMATE');

renderHead($order ? t('success.title', ['id' => (int)$order['id']]) : t('success.order'), '../');
renderTopbar('../');
?>
    <main class="container" id="conteudo">
        <div class="success-container">
            <?php if ($order): $id = (int)$order['id']; ?>
                <div class="success-hero">
                    <?php if ($order['status'] === 'cancelada'): ?>
                        <div class="success-icon is-muted" aria-hidden="true">✕</div>
                        <h1><?php echo e(t('success.cancelled_title')); ?></h1>
                        <p><?php echo t('success.cancelled_text', ['id' => $id]); ?></p>
                    <?php else: ?>
                        <div class="success-icon" aria-hidden="true">✓</div>
                        <h1><?php echo e(t('success.thanks', ['name' => strtok($order['customer_name'], ' ')])); ?></h1>
                        <p><?php echo t('success.received', ['id' => $id, 'phone' => e(formatPhone($order['phone']))]); ?></p>
                    <?php endif; ?>
                </div>

                <div class="stack">
                    <section class="card">
                        <h3><?php echo e(t('success.details')); ?></h3>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo e(t('success.date')); ?></span>
                            <span><?php echo date(t('date.long'), strtotime($order['order_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo e(t('success.status')); ?></span>
                            <span><span class="status status-<?php echo e($order['status']); ?>"><?php echo e(statusLabel($order['status'])); ?></span></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo e(t('success.customer')); ?></span>
                            <span><?php echo e($order['customer_name']); ?><br><span class="muted"><?php echo e($order['email']); ?> · <?php echo e(formatPhone($order['phone'])); ?></span></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo e(t('success.delivery')); ?></span>
                            <span><?php echo e($order['address']); ?><br><?php echo e(trim($order['postal_code'] . ' ' . $order['city'])); ?></span>
                        </div>
                        <?php if (!empty($order['notes'])): ?>
                            <div class="detail-row">
                                <span class="detail-label"><?php echo e(t('success.notes')); ?></span>
                                <span><?php echo nl2br(e($order['notes'])); ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:18px">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="summary-item">
                                    <span><?php echo e(productName($item)); ?> <small>× <?php echo (int)$item['quantity']; ?> <?php echo e($item['unit'] ? unitShort($item['unit']) : ''); ?> · <?php echo formatPrice($item['price']); ?></small></span>
                                    <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="summary-lines">
                                <div><span><?php echo e(t('common.subtotal')); ?></span><span><?php echo formatPrice($order['subtotal']); ?></span></div>
                                <div><span><?php echo e(t('common.delivery')); ?></span><span><?php echo (float)$order['delivery_fee'] == 0 ? e(t('common.free')) : formatPrice($order['delivery_fee']); ?></span></div>
                            </div>
                            <div class="summary-total">
                                <span><?php echo e(t('common.total')); ?> <small class="muted"><?php echo e(t('success.pay_on_delivery')); ?></small></span>
                                <strong><?php echo formatPrice($order['total']); ?></strong>
                            </div>
                        </div>
                    </section>

                    <?php if ($order['status'] !== 'cancelada'): ?>
                        <section class="card">
                            <h3><?php echo e(t('success.timeline')); ?></h3>
                            <ol class="timeline">
                                <?php foreach ($stepKeys as $i => $key): $done = $i <= $currentStep; ?>
                                    <li class="timeline-item">
                                        <span class="timeline-icon <?php echo $done ? '' : 'pending'; ?>"><?php echo $done ? '✓' : $i + 1; ?></span>
                                        <div>
                                            <strong><?php echo e(t("success.step.$key")); ?></strong><br>
                                            <small>
                                                <?php if ($key === 'pendente'): echo date('d/m/Y H:i', strtotime($order['order_date']));
                                                elseif ($key === 'enviada' && !$done): echo e(t('success.estimate', ['estimate' => $estimate]));
                                                else: echo e($done ? t('success.done') : t('success.waiting'));
                                                endif; ?>
                                            </small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <span><?php echo t('success.help', [
                            'email' => '<a href="mailto:' . e(STORE_EMAIL) . '">' . e(STORE_EMAIL) . '</a>',
                            'phone' => e(STORE_PHONE),
                            'id'    => $id,
                        ]); ?></span>
                    </div>

                    <div class="actions-row">
                        <a href="../index.php" class="btn btn-lg"><?php echo e(t('success.keep_shopping')); ?></a>
                        <button type="button" class="btn btn-secondary btn-lg" onclick="window.print()"><?php echo e(t('success.print')); ?></button>
                    </div>
                </div>
            <?php else: ?>
                <div class="success-hero">
                    <div class="success-icon is-muted" aria-hidden="true">?</div>
                    <h1><?php echo e(t('success.not_found')); ?></h1>
                    <p><?php echo e(t('success.not_found_text')); ?></p>
                </div>
                <div class="actions-row">
                    <a href="../index.php" class="btn btn-lg"><?php echo e(t('common.back_shop')); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </main>

<?php renderFooter('../'); ?>
</body>
</html>
