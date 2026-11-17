<?php
require_once '../includes/functions.php';
start_session_safe();

$wasLoggedIn = !empty($_SESSION['admin_logged_in']);
if (!isAdminAuthenticated()) {
    redirect($wasLoggedIn ? 'login.php?message=expired' : 'login.php');
}

// getOrders()/getProducts() devolvem false se a BD falhar — evitar count(false) (erro fatal no PHP 8)
$orders = getOrders() ?: [];
$products = getProducts() ?: [];

createDefaultProductImage();

$activeOrders = array_filter($orders, fn($o) => $o['status'] !== 'cancelada');
$revenue = array_sum(array_map(fn($o) => (float)$o['total'], $activeOrders));
$pending = count(array_filter($orders, fn($o) => $o['status'] === 'pendente'));

// Selects de categoria e unidade
function optionsHtml(array $options, $selected): string {
    $html = '';
    foreach ($options as $value => $label) {
        if (is_int($value)) $value = $label;
        $html .= '<option value="' . e($value) . '"' . ((string)$value === (string)$selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}
$outOfStock = count(array_filter($products, fn($p) => (int)$p['quantity'] === 0));
$username = $_SESSION['admin_user']['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo e(STORE_NAME); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrfToken()); ?>">
    <link rel="icon" href="../images/favicon.svg" type="image/svg+xml">
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500..700;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a href="dashboard.php" class="brand">
                <span class="brand-mark" aria-hidden="true">🌿</span>
                <span><?php echo e(STORE_NAME); ?><small>Administração</small></span>
            </a>
            <div class="topbar-actions admin-nav">
                <span class="muted" style="font-size:.9rem">Olá, <strong><?php echo e($username); ?></strong></span>
                <a href="../index.php" class="btn btn-secondary btn-sm">Ver loja</a>
                <form method="POST" action="logout.php" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Sair</button>
                </form>
            </div>
        </div>
    </header>

    <div id="alert-container" role="status" aria-live="polite"></div>

    <main class="container">
        <div class="page-head">
            <h1>Dashboard</h1>
            <p>Resumo da loja e gestão de produtos.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card <?php echo $pending ? 'warn' : ''; ?>">
                <div class="stat-label">Encomendas pendentes</div>
                <div class="stat-value"><?php echo $pending; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Faturação (sem canceladas)</div>
                <div class="stat-value"><?php echo formatPrice($revenue); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total de encomendas</div>
                <div class="stat-value"><?php echo count($orders); ?></div>
            </div>
            <div class="stat-card <?php echo $outOfStock ? 'warn' : ''; ?>">
                <div class="stat-label">Produtos sem stock</div>
                <div class="stat-value"><?php echo $outOfStock; ?> <small class="muted" style="font-size:1rem">/ <?php echo count($products); ?></small></div>
            </div>
        </div>

        <div class="tabs" role="tablist">
            <button class="tab" role="tab" aria-selected="true" aria-controls="tab-orders" id="tab-btn-orders">Encomendas<?php echo $pending ? " ($pending)" : ''; ?></button>
            <button class="tab" role="tab" aria-selected="false" aria-controls="tab-products" id="tab-btn-products">Produtos</button>
            <button class="tab" role="tab" aria-selected="false" aria-controls="tab-images" id="tab-btn-images">Imagens</button>
            <button class="tab" role="tab" aria-selected="false" aria-controls="tab-account" id="tab-btn-account">Conta</button>
        </div>

        <!-- Produtos -->
        <section class="tab-panel admin-section" id="tab-products" role="tabpanel" aria-labelledby="tab-btn-products" hidden>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço (€)</th>
                            <th>Unidade</th>
                            <th>Stock</th>
                            <th>Caminho da imagem</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="products-table-body">
                        <?php foreach ($products as $product): $id = (int)$product['id']; ?>
                            <tr id="product-<?php echo $id; ?>">
                                <td>
                                    <img src="<?php echo e(productImageUrl($product['image'], '../')); ?>"
                                         alt="<?php echo e($product['name']); ?>"
                                         class="product-image-admin" id="img_<?php echo $id; ?>"
                                         onerror="this.onerror=null;this.src='../images/products/default.jpg'">
                                </td>
                                <td>
                                    <strong><?php echo e($product['name']); ?></strong>
                                    <input type="text" class="input input-name-en" value="<?php echo e($product['name_en'] ?? ''); ?>" placeholder="Nome em inglês" maxlength="255" id="name_en_<?php echo $id; ?>" aria-label="Nome em inglês">
                                    <?php if ((int)$product['quantity'] === 0): ?><br><span class="badge badge-out">Esgotado</span><?php endif; ?>
                                </td>
                                <td><select class="input" id="category_<?php echo $id; ?>" aria-label="Categoria"><?php echo optionsHtml(PRODUCT_CATEGORIES, $product['category']); ?></select></td>
                                <td><input type="number" class="input input-price" value="<?php echo e($product['price']); ?>" step="0.01" min="0" id="price_<?php echo $id; ?>" aria-label="Preço"></td>
                                <td><select class="input" id="unit_<?php echo $id; ?>" aria-label="Unidade"><?php echo optionsHtml(PRODUCT_UNITS, $product['unit']); ?></select></td>
                                <td><input type="number" class="input input-stock" value="<?php echo (int)$product['quantity']; ?>" min="0" step="1" id="stock_<?php echo $id; ?>" aria-label="Stock"></td>
                                <td><input type="text" class="input input-image" value="<?php echo e($product['image'] ?? ''); ?>" placeholder="images/products/exemplo.png" id="image_<?php echo $id; ?>" aria-label="Caminho da imagem"></td>
                                <td>
                                    <div class="row-actions">
                                        <button class="btn btn-sm" data-update="<?php echo $id; ?>">Guardar</button>
                                        <button class="btn btn-danger btn-sm" data-delete="<?php echo $id; ?>">Eliminar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$products): ?>
                            <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">Ainda não há produtos.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <section class="card" style="margin-top:24px">
                <h3>Adicionar novo produto</h3>
                <form id="add-product-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_product_name">Nome do produto</label>
                            <input type="text" id="new_product_name" required maxlength="255">
                        </div>
                        <div class="form-group">
                            <label for="new_product_name_en">Nome em inglês <span class="muted">(opcional)</span></label>
                            <input type="text" id="new_product_name_en" maxlength="255" placeholder="Ex.: Apples">
                        </div>
                        <div class="form-group">
                            <label for="new_product_category">Categoria</label>
                            <select id="new_product_category"><?php echo optionsHtml(PRODUCT_CATEGORIES, 'Mercearia'); ?></select>
                        </div>
                        <div class="form-group">
                            <label for="new_product_price">Preço (€)</label>
                            <input type="number" id="new_product_price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="new_product_unit">Vendido por</label>
                            <select id="new_product_unit"><?php echo optionsHtml(PRODUCT_UNITS, 'un'); ?></select>
                        </div>
                        <div class="form-group">
                            <label for="new_product_quantity">Stock</label>
                            <input type="number" id="new_product_quantity" min="0" step="1" required>
                        </div>
                        <div class="form-group">
                            <label for="new_product_image">Caminho da imagem</label>
                            <input type="text" id="new_product_image" placeholder="images/products/exemplo.png">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_product_image_file">Ou enviar uma imagem</label>
                        <input type="file" id="new_product_image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <p class="form-hint">JPG, PNG, GIF ou WebP até 5 MB.</p>
                        <div class="image-preview" id="new-image-preview"></div>
                    </div>

                    <button type="submit" class="btn btn-accent" id="add-product-btn">Adicionar produto</button>
                </form>
            </section>
        </section>

        <!-- Encomendas -->
        <section class="tab-panel admin-section" id="tab-orders" role="tabpanel" aria-labelledby="tab-btn-orders">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info"><span>Ainda não há encomendas.</span></div>
            <?php else: ?>
                <div class="filter-row" id="order-filters">
                    <button type="button" class="chip" data-filter="todas" aria-pressed="true">Todas</button>
                    <?php foreach (ORDER_STATUSES as $key => $label): ?>
                        <button type="button" class="chip" data-filter="<?php echo e($key); ?>" aria-pressed="false">
                            <?php echo e($label); ?> <small><?php echo count(array_filter($orders, fn($o) => $o['status'] === $key)); ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="orders-list">
                    <?php foreach ($orders as $order): $oid = (int)$order['id']; $st = $order['status']; ?>
                        <details class="order-card" id="order-<?php echo $oid; ?>" data-status="<?php echo e($st); ?>">
                            <summary>
                                <strong>#<?php echo $oid; ?></strong>
                                <span>
                                    <?php echo e($order['customer_name']); ?><br>
                                    <small class="muted"><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></small>
                                </span>
                                <span class="muted hide-sm"><?php echo e($order['city'] ?: '—'); ?></span>
                                <strong class="hide-sm"><?php echo formatPrice($order['total']); ?></strong>
                                <span class="status status-<?php echo e($st); ?>" data-status-badge><?php echo e(ORDER_STATUSES[$st] ?? $st); ?></span>
                            </summary>
                            <div class="order-body">
                                <dl>
                                    <dt>Email</dt><dd><a href="mailto:<?php echo e($order['email']); ?>"><?php echo e($order['email'] ?: '—'); ?></a></dd>
                                    <dt>Telefone</dt><dd><a href="tel:<?php echo e($order['phone']); ?>"><?php echo e($order['phone'] ? formatPhone($order['phone']) : '—'); ?></a></dd>
                                    <dt>Nascimento</dt><dd><?php echo date('d/m/Y', strtotime($order['birth_date'])); ?></dd>
                                    <dt>Morada</dt><dd><?php echo e($order['address']); ?><br><?php echo e(trim($order['postal_code'] . ' ' . $order['city'])); ?></dd>
                                    <?php if (!empty($order['notes'])): ?><dt>Observações</dt><dd><?php echo e($order['notes']); ?></dd><?php endif; ?>
                                </dl>
                                <div>
                                    <p><?php echo e($order['items'] ?? '—'); ?></p>
                                    <p class="muted" style="margin-top:6px">
                                        Subtotal <?php echo formatPrice($order['subtotal']); ?> · Portes <?php echo formatPrice($order['delivery_fee']); ?> ·
                                        <strong style="color:var(--ink)">Total <?php echo formatPrice($order['total']); ?></strong>
                                    </p>
                                    <div class="order-actions">
                                        <?php if ($st === 'cancelada'): ?>
                                            <span class="muted">Encomenda cancelada — o stock foi reposto.</span>
                                        <?php else: ?>
                                            <label class="sr-only" for="status_<?php echo $oid; ?>">Estado</label>
                                            <select class="status-select" id="status_<?php echo $oid; ?>">
                                                <?php echo optionsHtml(array_diff_key(ORDER_STATUSES, ['cancelada' => 1]), $st); ?>
                                            </select>
                                            <button type="button" class="btn btn-sm" data-order-status="<?php echo $oid; ?>">Atualizar estado</button>
                                            <button type="button" class="btn btn-danger btn-sm" data-order-cancel="<?php echo $oid; ?>">Cancelar</button>
                                        <?php endif; ?>
                                        <a class="btn btn-ghost btn-sm" href="../pages/success.php?order_id=<?php echo $oid; ?>" target="_blank">Comprovativo</a>
                                    </div>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Imagens -->
        <section class="tab-panel admin-section" id="tab-images" role="tabpanel" aria-labelledby="tab-btn-images" hidden>
            <div class="image-upload-area" id="bulk-upload-area">
                <p>Arraste imagens para aqui ou clique para selecionar.<br><small>Depois copie o caminho para o campo "Caminho da imagem" do produto.</small></p>
                <input type="file" id="bulk-image-upload" multiple accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                <button type="button" class="btn btn-secondary" id="bulk-select-btn">Selecionar imagens</button>
            </div>
            <div id="upload-progress" class="upload-log"></div>
        </section>
        <!-- Conta -->
        <section class="tab-panel admin-section" id="tab-account" role="tabpanel" aria-labelledby="tab-btn-account" hidden>
            <section class="card" style="max-width:480px">
                <h3>Alterar palavra-passe</h3>
                <form id="password-form" autocomplete="off">
                    <input type="text" name="username" value="<?php echo e($username); ?>" autocomplete="username" hidden>
                    <div class="form-group">
                        <label for="current_password">Palavra-passe atual</label>
                        <input type="password" id="current_password" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nova palavra-passe</label>
                        <input type="password" id="new_password" required minlength="8" autocomplete="new-password">
                        <p class="form-hint">Pelo menos 8 caracteres, com letras e números.</p>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmar nova palavra-passe</label>
                        <input type="password" id="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn" id="password-btn">Guardar nova palavra-passe</button>
                </form>
            </section>
        </section>
    </main>

    <script>
        const $ = (id) => document.getElementById(id);
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        // ---------- Separadores ----------
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', String(t === tab)));
                document.querySelectorAll('.tab-panel').forEach(p => p.hidden = p.id !== tab.getAttribute('aria-controls'));
                try { localStorage.setItem('admin_tab', tab.id); } catch (e) {}
            });
        });
        try {
            const saved = localStorage.getItem('admin_tab');
            if (saved && $(saved)) $(saved).click();
        } catch (e) {}

        // ---------- Pedidos à API ----------
        async function api(method, body, url = '../api/products.php') {
            try {
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body: JSON.stringify(body)
                });
                if (response.status === 401) {
                    window.location.href = 'login.php?message=expired';
                    return { success: false };
                }
                return await response.json();
            } catch (error) {
                console.error('Erro:', error);
                return { success: false, message: 'Erro de conexão com o servidor' };
            }
        }

        // ---------- Atualizar / eliminar (delegação de eventos) ----------
        $('products-table-body').addEventListener('click', (e) => {
            const upd = e.target.closest('[data-update]');
            const del = e.target.closest('[data-delete]');
            if (upd) updateProduct(Number(upd.dataset.update), upd);
            if (del) deleteProduct(Number(del.dataset.delete));
        });

        async function updateProduct(productId, btn) {
            const price = $(`price_${productId}`).value;
            const quantity = $(`stock_${productId}`).value;
            const image = $(`image_${productId}`).value.trim();

            if (price === '' || quantity === '' || Number(price) < 0 || Number(quantity) < 0 || !Number.isInteger(Number(quantity))) {
                showAlert('error', 'O preço e a quantidade têm de ser números maiores ou iguais a 0 (quantidade inteira).');
                return;
            }

            btn.disabled = true;
            const result = await api('PUT', {
                id: productId,
                name_en: $(`name_en_${productId}`).value.trim(),
                category: $(`category_${productId}`).value,
                price: Number(price),
                unit: $(`unit_${productId}`).value,
                quantity: parseInt(quantity, 10),
                image: image || null
            });
            btn.disabled = false;

            if (result.success) {
                showAlert('success', 'Produto atualizado com sucesso');
                if (image) {
                    $(`img_${productId}`).src = /^https?:\/\//i.test(image) ? image : '../' + image;
                }
            } else if (result.message) {
                showAlert('error', result.message);
            }
        }

        async function deleteProduct(productId) {
            if (!confirm('Tem a certeza que quer eliminar este produto? As encomendas antigas mantêm o nome do produto.')) return;

            const result = await api('DELETE', { id: productId });
            if (result.success) {
                showAlert('success', 'Produto eliminado com sucesso');
                $(`product-${productId}`).remove();
            } else if (result.message) {
                showAlert('error', result.message);
            }
        }

        // ---------- Adicionar produto ----------
        $('new_product_image_file').addEventListener('change', function () {
            const preview = $('new-image-preview');
            preview.innerHTML = '';
            if (this.files && this.files[0]) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(this.files[0]);
                preview.appendChild(img);
            }
        });

        $('add-product-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const name = $('new_product_name').value.trim();
            const price = $('new_product_price').value;
            const quantity = $('new_product_quantity').value;
            let image = $('new_product_image').value.trim();
            const imageFile = $('new_product_image_file').files[0];

            if (!name || price === '' || quantity === '' || Number(price) < 0 || Number(quantity) < 0) {
                showAlert('error', 'Preencha nome, preço e quantidade (valores maiores ou iguais a 0).');
                return;
            }

            const btn = $('add-product-btn');
            btn.disabled = true;

            if (imageFile) {
                const upload = await uploadImage(imageFile);
                if (!upload.success) {
                    showAlert('error', upload.message || 'Erro no upload da imagem');
                    btn.disabled = false;
                    return;
                }
                image = upload.path;
            }

            const result = await api('POST', {
                name,
                name_en: $('new_product_name_en').value.trim(),
                category: $('new_product_category').value,
                unit: $('new_product_unit').value,
                price: Number(price),
                quantity: parseInt(quantity, 10),
                image: image || null
            });

            if (result.success) {
                showAlert('success', 'Produto adicionado com sucesso. A atualizar…');
                setTimeout(() => location.reload(), 1200);
            } else {
                showAlert('error', result.message || 'Erro ao adicionar produto');
                btn.disabled = false;
            }
        });

        // ---------- Upload de imagens ----------
        async function uploadImage(file) {
            const formData = new FormData();
            formData.append('image', file);
            try {
                const response = await fetch('../api/upload_image.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF },
                    body: formData
                });
                if (response.status === 401) {
                    window.location.href = 'login.php?message=expired';
                    return { success: false };
                }
                return await response.json();
            } catch (error) {
                console.error('Erro no upload:', error);
                return { success: false, message: 'Erro de conexão' };
            }
        }

        const uploadArea = $('bulk-upload-area');
        const fileInput = $('bulk-image-upload');

        $('bulk-select-btn').addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleBulkUpload(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', (e) => handleBulkUpload(e.target.files));

        function logLine(text, cls = '') {
            const p = document.createElement('p');
            p.className = cls;
            p.textContent = text; // textContent: o nome do ficheiro não é interpretado como HTML
            $('upload-progress').appendChild(p);
            return p;
        }

        async function handleBulkUpload(files) {
            $('upload-progress').innerHTML = '';
            for (const file of files) {
                const line = logLine(`A enviar ${file.name}…`);
                const result = await uploadImage(file);
                line.textContent = result.success ? `✓ ${file.name} → ${result.path}` : `✗ ${file.name} — ${result.message}`;
                line.className = result.success ? 'ok' : 'fail';
            }
            logLine('Upload concluído.');
            fileInput.value = '';
        }

        // ---------- Encomendas: filtros e estados ----------
        const ordersList = $('orders-list');
        if (ordersList) {
            $('order-filters').addEventListener('click', (e) => {
                const chip = e.target.closest('[data-filter]');
                if (!chip) return;
                document.querySelectorAll('#order-filters .chip').forEach(c => c.setAttribute('aria-pressed', String(c === chip)));
                document.querySelectorAll('.order-card').forEach(card => {
                    card.hidden = chip.dataset.filter !== 'todas' && card.dataset.status !== chip.dataset.filter;
                });
            });

            ordersList.addEventListener('click', async (e) => {
                const upd = e.target.closest('[data-order-status]');
                const cancel = e.target.closest('[data-order-cancel]');
                if (!upd && !cancel) return;

                const id = Number((upd || cancel).dataset.orderStatus || (upd || cancel).dataset.orderCancel);
                const status = cancel ? 'cancelada' : $(`status_${id}`).value;

                if (cancel && !confirm(`Cancelar a encomenda #${id}? Os produtos voltam ao stock e esta ação não pode ser desfeita.`)) return;

                const btn = upd || cancel;
                btn.disabled = true;
                const result = await api('PATCH', { id, status }, '../api/orders.php');
                btn.disabled = false;

                if (result.success) {
                    showAlert('success', result.message);
                    if (cancel) {
                        setTimeout(() => location.reload(), 900);
                    } else {
                        const card = $(`order-${id}`);
                        card.dataset.status = status;
                        const badge = card.querySelector('[data-status-badge]');
                        badge.className = `status status-${status}`;
                        badge.textContent = $(`status_${id}`).selectedOptions[0].textContent;
                    }
                } else if (result.message) {
                    showAlert('error', result.message);
                }
            });
        }

        // ---------- Alterar palavra-passe ----------
        $('password-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const current = $('current_password').value;
            const next = $('new_password').value;

            if (next !== $('confirm_password').value) {
                showAlert('error', 'A confirmação não coincide com a nova palavra-passe.');
                return;
            }

            const btn = $('password-btn');
            btn.disabled = true;
            const result = await api('POST', { current_password: current, new_password: next }, '../api/account.php');
            btn.disabled = false;

            if (result.success) {
                showAlert('success', 'Palavra-passe alterada com sucesso.');
                event.target.reset();
            } else if (result.message) {
                showAlert('error', result.message);
            }
        });

        // ---------- Alertas ----------
        function showAlert(type, message) {
            const container = $('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            const span = document.createElement('span');
            span.textContent = message;
            const close = document.createElement('button');
            close.className = 'alert-close';
            close.setAttribute('aria-label', 'Fechar');
            close.textContent = '×';
            close.onclick = () => alertDiv.remove();
            alertDiv.append(span, close);
            container.innerHTML = '';
            container.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>
