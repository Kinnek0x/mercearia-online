<?php

// Funções auxiliares para o projeto

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/store.php';
require_once __DIR__ . '/i18n.php';

define('ROOT_DIR', dirname(__DIR__));
define('PRODUCT_IMAGE_DIR', ROOT_DIR . '/images/products/');
define('DEFAULT_PRODUCT_IMAGE', 'images/products/default.jpg');


define('ADMIN_SESSION_TIMEOUT', 30 * 60);   // 30 min de inatividade
define('LOGIN_MAX_ATTEMPTS', 5);             // tentativas falhadas permitidas...
define('LOGIN_LOCK_SECONDS', 15 * 60);       // ...por cada 15 minutos


// Cabeçalhos de segurança (enviados em todas as páginas, também com o "php -S",
// que ignora o .htaccess)
function sendSecurityHeaders(): void {
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https: http:; "
        . "connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
}
sendSecurityHeaders();


// Inicializar sessão de forma segura (cookie HttpOnly + SameSite)
function start_session_safe(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}


// Limpar texto vindo do utilizador.
// NOTA: não converte para entidades HTML — o escape é feito na saída com e().
// (Antes os dados eram gravados já escapados e depois escapados de novo,
//  o que mostrava "D&#039;Ouro" em vez de "D'Ouro".)
function sanitizeInput($data) {
    if (is_null($data)) return null;
    $data = trim((string)$data);
    // Remover caracteres de controlo (mantém quebras de linha)
    $data = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
    return $data;
}


// Escapar texto para HTML
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}


// Verificar se utilizador está autenticado como admin
// (termina a sessão de admin após ADMIN_SESSION_TIMEOUT sem atividade)
function isAdminAuthenticated(): bool {
    start_session_safe();
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }

    $last = $_SESSION['admin_last_activity'] ?? 0;
    if (time() - $last > ADMIN_SESSION_TIMEOUT) {
        unset($_SESSION['admin_logged_in'], $_SESSION['admin_user'], $_SESSION['admin_last_activity']);
        return false;
    }

    $_SESSION['admin_last_activity'] = time();
    return true;
}


// ---------- Proteção CSRF ----------
// Token aleatório guardado na sessão; as ações de admin têm de o enviar
// no cabeçalho X-CSRF-Token (ou no campo csrf_token de um formulário).
function csrfToken(): string {
    start_session_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function isValidCsrf(): bool {
    start_session_safe();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return !empty($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], $sent);
}

// Para as APIs: exige admin autenticado + token CSRF válido
function requireAdminApi(): void {
    if (!isAdminAuthenticated()) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Inicie sessão novamente.'], 401);
    }
    if (!isValidCsrf()) {
        jsonResponse(['success' => false, 'message' => 'Pedido inválido (token de segurança). Recarregue a página.'], 403);
    }
}


// ---------- Limite de tentativas de login (por IP) ----------
function loginAttemptsFile(): string {
    return ROOT_DIR . '/logs/login_attempts.json';
}

// Lê/atualiza o ficheiro de tentativas com bloqueio, aplicando $fn aos dados
function withLoginAttempts(callable $fn) {
    $file = loginAttemptsFile();
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);

    $fh = fopen($file, 'c+');
    if (!$fh) return $fn([])[1];
    flock($fh, LOCK_EX);
    $data = json_decode(stream_get_contents($fh) ?: '{}', true) ?: [];

    // Limpar entradas antigas
    $now = time();
    foreach ($data as $k => $entry) {
        if ($now - ($entry['first'] ?? 0) > LOGIN_LOCK_SECONDS) unset($data[$k]);
    }

    [$data, $result] = $fn($data);

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

function loginClientKey(): string {
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

// Devolve os segundos que faltam até poder tentar de novo (0 = pode tentar)
function loginLockRemaining(): int {
    $key = loginClientKey();
    return withLoginAttempts(function ($data) use ($key) {
        $e = $data[$key] ?? null;
        $remaining = 0;
        if ($e && $e['count'] >= LOGIN_MAX_ATTEMPTS) {
            $remaining = max(0, LOGIN_LOCK_SECONDS - (time() - $e['first']));
        }
        return [$data, $remaining];
    });
}

function registerLoginFailure(): void {
    $key = loginClientKey();
    withLoginAttempts(function ($data) use ($key) {
        $e = $data[$key] ?? ['count' => 0, 'first' => time()];
        $e['count']++;
        $data[$key] = $e;
        return [$data, null];
    });
}

function clearLoginFailures(): void {
    $key = loginClientKey();
    withLoginAttempts(function ($data) use ($key) {
        unset($data[$key]);
        return [$data, null];
    });
}


// Redirecionar para uma página
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    }
    exit();
}


// Retornar resposta JSON
function jsonResponse($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}


// Validar idade mínima (data no formato AAAA-MM-DD, não pode estar no futuro)
function validateAge($birthDate, $minAge = 18) {
    $birth = DateTime::createFromFormat('!Y-m-d', (string)$birthDate);
    if (!$birth || $birth->format('Y-m-d') !== $birthDate) {
        return false;
    }

    $today = new DateTime('today');
    if ($birth > $today) {
        return false; // data no futuro (antes o diff() absoluto deixava passar)
    }

    return $today->diff($birth)->y >= $minAge;
}


// Formatar preço para exibição
function formatPrice($price) {
    return currentLang() === 'en'
        ? '€' . number_format((float)$price, 2, '.', ',')
        : number_format((float)$price, 2, ',', '.') . ' €';
}


// Telefone legível: 912345678 -> 912 345 678 (+351 opcional)
function formatPhone($phone): string {
    $phone = (string)$phone;
    if (preg_match('/^(\+351)?(\d{3})(\d{3})(\d{3})$/', $phone, $m)) {
        return trim(($m[1] ? $m[1] . ' ' : '') . "$m[2] $m[3] $m[4]");
    }
    return $phone;
}


// Portes de envio para um subtotal
function deliveryFeeFor(float $subtotal): float {
    return $subtotal >= FREE_DELIVERY_FROM ? 0.0 : DELIVERY_FEE;
}


// Etiqueta da unidade ("kg", "unidade", ...)
function unitLabel($unit): string {
    return PRODUCT_UNITS[$unit] ?? (string)$unit;
}


// Hash do IP do cliente (não guardamos o IP em claro — RGPD)
function clientIpHash(): string {
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . DB_NAME);
}


// Validar dados obrigatórios
function validateRequired($data, $requiredFields) {
    $errors = [];
    $labels = ['name' => 'Nome', 'birth_date' => 'Data de nascimento', 'address' => 'Morada'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
            $label = $labels[$field] ?? $field;
            $errors[] = "O campo '$label' é obrigatório";
        }
    }
    return $errors;
}


// Regras da password de administrador: devolve a mensagem de erro, ou null se for válida.
// Usada ao mudar a password no painel e ao criar o admin (bin/criar_admin.php).
function adminPasswordError(string $password, string $username = ''): ?string {
    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'A palavra-passe tem de ter 8 a 72 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'A palavra-passe tem de ter letras e números.';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return 'A palavra-passe não pode conter o nome de utilizador.';
    }
    return null;
}


// Gerar hash da password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}


// Verificar password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}


// Caminho da imagem do produto (com prefixo relativo para páginas em subpastas)
function productImageUrl($image, $prefix = '') {
    $image = $image ?: DEFAULT_PRODUCT_IMAGE;
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return $prefix . $image;
}


// Validar caminho de imagem indicado pelo admin
function isValidImagePath($image) {
    if ($image === null || $image === '') return true;
    return (bool)(
        preg_match('#^images/products/[\w.-]+\.(jpe?g|png|gif|webp)$#i', $image) ||
        preg_match('#^https?://[^\s"\'<>]+$#i', $image)
    );
}


// Obter todos os produtos
function getProducts() {
    return Database::query("SELECT * FROM products ORDER BY category, name");
}


// Obter produto por ID
function getProduct($id) {
    $result = Database::query("SELECT * FROM products WHERE id = ?", [(int)$id]);
    return $result ? $result[0] : null;
}


// Atualizar stock do produto
function updateProductStock($id, $newQuantity) {
    return Database::execute("UPDATE products SET quantity = ? WHERE id = ?", [$newQuantity, $id]);
}


// Adicionar novo produto
function addProduct($name, $nameEn, $category, $price, $unit, $quantity, $image = null) {
    return Database::execute(
        "INSERT INTO products (name, name_en, category, price, unit, quantity, image) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$name, $nameEn ?: null, $category, $price, $unit, $quantity, $image ?: null]
    );
}


// Atualizar produto
function updateProduct($id, $nameEn, $category, $price, $unit, $quantity, $image = null) {
    $sql = "UPDATE products SET name_en = ?, category = ?, price = ?, unit = ?, quantity = ?";
    $params = [$nameEn ?: null, $category, $price, $unit, $quantity];
    if ($image) {
        $sql .= ", image = ?";
        $params[] = $image;
    }
    $params[] = $id;
    return Database::execute($sql . " WHERE id = ?", $params);
}


// Fazer upload de imagem de produto
function uploadProductImage($file) {
    if (!is_dir(PRODUCT_IMAGE_DIR)) {
        mkdir(PRODUCT_IMAGE_DIR, 0755, true);
    }

    // Validar tamanho (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB.'];
    }

    // Validar o conteúdo real do ficheiro (não confiar no MIME/extensão enviados pelo browser)
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset($allowed[$info[2]])) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.'];
    }

    // Nome único. Se o servidor suportar WebP, a imagem é convertida para WebP
    // (fotos de telemóvel com vários MB ficam com ~50 KB, a loja carrega mais depressa)
    $name = 'product_' . bin2hex(random_bytes(8));
    $webp = convertToWebp($file['tmp_name'], $info[2], PRODUCT_IMAGE_DIR . $name . '.webp', 800);

    if ($webp) {
        $filename = $name . '.webp';
        return ['success' => true, 'filename' => $filename, 'path' => 'images/products/' . $filename];
    }

    // Sem suporte para WebP: guardar no formato original, redimensionado
    $filename = $name . '.' . $allowed[$info[2]];
    $filepath = PRODUCT_IMAGE_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        resizeImage($filepath, 800, 800);

        return [
            'success'  => true,
            'filename' => $filename,
            'path'     => 'images/products/' . $filename
        ];
    }

    return ['success' => false, 'message' => 'Erro ao fazer upload da imagem.'];
}


// Converter uma imagem para WebP, com o lado maior limitado a $maxSize píxeis.
// Devolve false se o servidor não tiver GD com suporte para WebP.
function convertToWebp(string $source, int $type, string $destination, int $maxSize): bool {
    if (!function_exists('imagewebp')) return false;

    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($source); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($source); break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false; break;
        default: return false;
    }
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);
    $ratio = min(1, $maxSize / max($w, $h));
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));

    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = imagewebp($out, $destination, 80);
    imagedestroy($img);
    imagedestroy($out);
    return $ok;
}


// Redimensionar imagem mantendo proporção
function resizeImage($filepath, $maxWidth, $maxHeight) {
    if (!function_exists('imagecreatetruecolor')) return false; // extensão GD não instalada

    $imageInfo = getimagesize($filepath);
    if (!$imageInfo) return false;

    [$width, $height, $type] = $imageInfo;

    if ($width <= $maxWidth && $height <= $maxHeight) {
        return true;
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    switch ($type) {
        case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($filepath); break;
        case IMAGETYPE_PNG:  $source = imagecreatefrompng($filepath); break;
        case IMAGETYPE_GIF:  $source = imagecreatefromgif($filepath); break;
        case IMAGETYPE_WEBP: $source = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($filepath) : false; break;
        default: return false;
    }

    if (!$source) return false;

    $destination = imagecreatetruecolor($newWidth, $newHeight);

    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($type) {
        case IMAGETYPE_JPEG: $result = imagejpeg($destination, $filepath, 85); break;
        case IMAGETYPE_PNG:  $result = imagepng($destination, $filepath); break;
        case IMAGETYPE_GIF:  $result = imagegif($destination, $filepath); break;
        case IMAGETYPE_WEBP: $result = imagewebp($destination, $filepath, 85); break;
        default: $result = false;
    }

    imagedestroy($source);
    imagedestroy($destination);

    return $result;
}


// Validar um carrinho { productId: quantidade } contra a BD.
// Devolve ['items' => [...], 'total' => float, 'errors' => [...]]
function buildCartItems($cart) {
    $items = [];
    $errors = [];
    $subtotal = 0.0;

    if (!is_array($cart)) {
        return ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0, 'errors' => [t('api.cart_invalid')]];
    }

    if (count($cart) > ORDER_MAX_LINES) {
        $errors[] = t('api.max_lines', ['n' => ORDER_MAX_LINES]);
    }

    foreach ($cart as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = (int)$quantity;

        if ($quantity <= 0) continue;

        $product = getProduct($productId);

        if (!$product) {
            $errors[] = t('api.product_gone');
            continue;
        }

        $label = productName($product);

        if ($quantity > ORDER_MAX_QTY_PER_PRODUCT) {
            $errors[] = t('api.max_qty', ['n' => ORDER_MAX_QTY_PER_PRODUCT, 'product' => $label]);
            continue;
        }

        $stock = (int)$product['quantity'];
        if ($stock === 0) {
            $errors[] = t('api.out_of_stock', ['product' => $label]);
            continue;
        }
        if ($quantity > $stock) {
            $errors[] = t('api.low_stock', ['product' => $label, 'available' => $stock, 'requested' => $quantity]);
            continue;
        }

        $price = (float)$product['price'];
        $lineTotal = round($price * $quantity, 2);
        $subtotal += $lineTotal;

        $items[] = [
            'product_id'   => (int)$product['id'],
            'product_name' => $product['name'],   // guardado em PT na encomenda (para a loja)
            'display_name' => $label,             // nome no idioma do cliente
            'unit'         => unitShort($product['unit'] ?? 'un'),
            'price'        => $price,
            'quantity'     => $quantity,
            'subtotal'     => $lineTotal,
        ];
    }

    $subtotal = round($subtotal, 2);
    $fee = $items ? deliveryFeeFor($subtotal) : 0.0;

    if ($items && $subtotal < MIN_ORDER_TOTAL) {
        $errors[] = t('api.min_order', ['amount' => formatPrice(MIN_ORDER_TOTAL)]);
    }
    if ($subtotal > ORDER_MAX_TOTAL) {
        $errors[] = t('api.max_total', ['amount' => formatPrice(ORDER_MAX_TOTAL)]);
    }

    return [
        'items'        => $items,
        'subtotal'     => $subtotal,
        'delivery_fee' => $fee,
        'total'        => round($subtotal + $fee, 2),
        'errors'       => $errors,
    ];
}


// ---------- Limites contra encomendas falsas ----------
// Devolve uma mensagem de erro se o cliente ultrapassou algum limite, ou null.
function orderLimitError(string $email): ?string {
    $recent = Database::query(
        "SELECT COUNT(*) AS n FROM orders WHERE ip_hash = ? AND order_date > (NOW() - INTERVAL 1 HOUR)",
        [clientIpHash()]
    );
    if ($recent && (int)$recent[0]['n'] >= ORDER_MAX_PER_HOUR_IP) {
        return t('api.rate_ip', ['phone' => STORE_PHONE]);
    }

    $pending = Database::query(
        "SELECT COUNT(*) AS n FROM orders WHERE email = ? AND status = 'pendente'",
        [$email]
    );
    if ($pending && (int)$pending[0]['n'] >= ORDER_MAX_PENDING_PER_EMAIL) {
        return t('api.rate_email', ['n' => ORDER_MAX_PENDING_PER_EMAIL]);
    }

    return null;
}


// Criar encomenda (transação real: se algo falhar, nada é gravado)
function createOrder($customer, $cartResult) {
    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $stmt = $db->prepare(
            "INSERT INTO orders (customer_name, email, phone, birth_date, address, postal_code, city, notes,
                                 subtotal, delivery_fee, total, status, ip_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?)"
        );
        $stmt->execute([
            $customer['name'], $customer['email'], $customer['phone'], $customer['birth_date'],
            $customer['address'], $customer['postal_code'], $customer['city'], $customer['notes'] ?: null,
            $cartResult['subtotal'], $cartResult['delivery_fee'], $cartResult['total'], clientIpHash(),
        ]);
        $orderId = (int)$db->lastInsertId();

        $insertItem = $db->prepare(
            "INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)"
        );
        // Só desconta se ainda houver stock suficiente (evita stock negativo com compras simultâneas)
        $updateStock = $db->prepare(
            "UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?"
        );

        foreach ($cartResult['items'] as $item) {
            $insertItem->execute([$orderId, $item['product_id'], $item['product_name'], $item['quantity'], $item['price']]);

            $updateStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            if ($updateStock->rowCount() === 0) {
                throw new RuntimeException("Stock insuficiente para {$item['product_name']}");
            }
        }

        $db->commit();
        return $orderId;

    } catch (Exception $e) {
        Database::rollback();
        error_log("Erro ao criar encomenda: " . $e->getMessage());
        return false;
    }
}


// Mudar o estado de uma encomenda. Cancelar devolve os produtos ao stock.
// Devolve true ou uma mensagem de erro.
function updateOrderStatus(int $orderId, string $status) {
    if (!array_key_exists($status, ORDER_STATUSES)) {
        return 'Estado inválido';
    }

    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            $db->rollBack();
            return 'Encomenda não encontrada';
        }
        if ($current === 'cancelada') {
            $db->rollBack();
            return 'Uma encomenda cancelada já não pode ser alterada';
        }
        if ($current === $status) {
            $db->rollBack();
            return true;
        }

        if ($status === 'cancelada') {
            $db->prepare(
                "UPDATE products p
                 JOIN order_items oi ON oi.product_id = p.id
                 SET p.quantity = p.quantity + oi.quantity
                 WHERE oi.order_id = ?"
            )->execute([$orderId]);
        }

        $db->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $orderId]);
        $db->commit();
        return true;

    } catch (Exception $e) {
        Database::rollback();
        error_log("Erro ao mudar estado da encomenda: " . $e->getMessage());
        return 'Erro ao atualizar a encomenda';
    }
}


// Obter todas as encomendas
function getOrders() {
    return Database::query("
        SELECT o.*,
               GROUP_CONCAT(CONCAT(oi.product_name, ' × ', oi.quantity) ORDER BY oi.id SEPARATOR ', ') AS items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ");
}


// Obter uma encomenda e os seus itens
function getOrderWithItems($orderId) {
    $order = Database::query("SELECT * FROM orders WHERE id = ?", [(int)$orderId]);
    if (!$order) return null;

    $order = $order[0];
    $order['items'] = Database::query(
        "SELECT oi.product_name, p.name_en, oi.quantity, oi.price, p.unit
         FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ? ORDER BY oi.id",
        [(int)$orderId]
    ) ?: [];
    return $order;
}


// Verificar login do admin
function checkAdminLogin($username, $password) {
    $user = Database::query("SELECT * FROM users WHERE username = ?", [$username]);

    if ($user && verifyPassword($password, $user[0]['password'])) {
        return $user[0];
    }

    return false;
}


// Verificar se há stock suficiente
function checkStock($productId, $requestedQuantity) {
    $product = getProduct($productId);
    return $product && $product['quantity'] >= $requestedQuantity;
}


// Criar imagem padrão se não existir
function createDefaultProductImage() {
    $imagePath = ROOT_DIR . '/' . DEFAULT_PRODUCT_IMAGE;

    if (file_exists($imagePath) || !function_exists('imagecreate')) {
        return;
    }

    if (!is_dir(dirname($imagePath))) {
        mkdir(dirname($imagePath), 0755, true);
    }

    $image = imagecreate(400, 400);
    imagecolorallocate($image, 240, 240, 240);
    $textColor = imagecolorallocate($image, 100, 100, 100);

    $text = "Sem Imagem";
    $font = 5;
    $x = (int)((400 - imagefontwidth($font) * strlen($text)) / 2);
    $y = (int)((400 - imagefontheight($font)) / 2);

    imagestring($image, $font, $x, $y, $text, $textColor);
    imagejpeg($image, $imagePath, 85);
    imagedestroy($image);
}


// Log de atividades do admin
function logAdminActivity($action, $details = '') {
    if (!isAdminAuthenticated()) return false;

    $username = $_SESSION['admin_user']['username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $logEntry = date('Y-m-d H:i:s') . " - User: $username, IP: $ip, Action: $action";
    if ($details) {
        $logEntry .= ", Details: $details";
    }
    $logEntry .= "\n";

    $logDir = ROOT_DIR . '/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logDir . 'admin_' . date('Y-m') . '.log', $logEntry, FILE_APPEND | LOCK_EX);

    return true;
}


// Eliminar produto
function deleteProduct($id) {
    return Database::execute("DELETE FROM products WHERE id = ?", [$id]);
}
