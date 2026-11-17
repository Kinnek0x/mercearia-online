<?php

// API de encomendas
//   GET   -> listar (admin)
//   POST  -> criar encomenda (cliente)
//   PATCH -> mudar estado (admin)

require_once '../includes/functions.php';
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':   handleGetOrders(); break;
        case 'POST':  handleCreateOrder(); break;
        case 'PATCH': handleUpdateStatus(); break;
        default:
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    error_log("API Orders Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => t('api.server_error')], 500);
}


// Listar encomendas (apenas admin)
function handleGetOrders() {
    if (!isAdminAuthenticated()) {
        jsonResponse(['success' => false, 'message' => 'Acesso não autorizado'], 401);
    }

    $orders = getOrders();
    if ($orders === false) {
        jsonResponse(['success' => false, 'message' => 'Erro ao carregar encomendas'], 500);
    }

    // Não expor o hash do IP
    $orders = array_map(function ($o) { unset($o['ip_hash']); return $o; }, $orders);
    jsonResponse(['success' => true, 'orders' => $orders]);
}


// Mudar estado de uma encomenda (apenas admin)
function handleUpdateStatus() {
    requireAdminApi();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $status = (string)($input['status'] ?? '');

    $result = updateOrderStatus($id, $status);
    if ($result !== true) {
        jsonResponse(['success' => false, 'message' => $result], 400);
    }

    logAdminActivity('Order Status', "#$id -> $status");
    jsonResponse(['success' => true, 'message' => 'Encomenda #' . $id . ': ' . ORDER_STATUSES[$status]]);
}


// Criar nova encomenda
function handleCreateOrder() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input) || !isset($input['customer'], $input['cart']) || !is_array($input['customer'])) {
        jsonResponse(['success' => false, 'message' => t('api.incomplete')], 400);
    }

    $c = $input['customer'];

    // --- Proteções anti-bot ---
    // 1) Campo "website" escondido: pessoas não o veem, bots preenchem-no
    if (!empty($c['website'])) {
        error_log('Encomenda bloqueada (honeypot)');
        jsonResponse(['success' => false, 'message' => t('api.blocked')], 400);
    }
    // 2) O checkout tem de ter sido aberto há pelo menos alguns segundos
    $started = $_SESSION['checkout_started'] ?? 0;
    if (!$started || time() - $started < ORDER_MIN_FORM_SECONDS) {
        jsonResponse(['success' => false, 'message' => t('api.too_fast')], 400);
    }

    // --- Validação dos dados do cliente ---
    $customer = [
        'name'        => sanitizeInput($c['name'] ?? ''),
        'email'       => mb_strtolower(sanitizeInput($c['email'] ?? '')),
        'phone'       => preg_replace('/[^\d+]/', '', (string)($c['phone'] ?? '')),
        'birth_date'  => sanitizeInput($c['birth_date'] ?? ''),
        'address'     => sanitizeInput($c['address'] ?? ''),
        'postal_code' => sanitizeInput($c['postal_code'] ?? ''),
        'city'        => sanitizeInput($c['city'] ?? ''),
        'notes'       => sanitizeInput($c['notes'] ?? ''),
    ];

    $errors = [];
    if (mb_strlen($customer['name']) < 3 || mb_strlen($customer['name']) > 255) $errors[] = t('val.name');
    if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($customer['email']) > 255) $errors[] = t('val.email');
    if (!preg_match('/^(\+351)?9[1236]\d{7}$|^(\+351)?2\d{8}$/', $customer['phone'])) $errors[] = t('val.phone');
    if (!validateAge($customer['birth_date'])) $errors[] = t('val.birth');
    if (mb_strlen($customer['address']) < 5 || mb_strlen($customer['address']) > 500) $errors[] = t('val.address');
    if (!preg_match('/^\d{4}-\d{3}$/', $customer['postal_code'])) $errors[] = t('val.postal');
    if (mb_strlen($customer['city']) < 2 || mb_strlen($customer['city']) > 100) $errors[] = t('val.city');
    if (mb_strlen($customer['notes']) > 500) $errors[] = t('val.notes');
    if (empty($input['accept_terms'])) $errors[] = t('val.terms');

    if ($errors) {
        jsonResponse(['success' => false, 'message' => implode(' · ', $errors)], 400);
    }

    // --- Limites por IP e por email ---
    if ($limit = orderLimitError($customer['email'])) {
        jsonResponse(['success' => false, 'message' => $limit], 429);
    }

    // --- Carrinho (preços e stock vêm sempre da base de dados) ---
    if (!is_array($input['cart']) || empty($input['cart'])) {
        jsonResponse(['success' => false, 'message' => t('api.cart_empty')], 400);
    }

    $cart = buildCartItems($input['cart']);

    if (!empty($cart['errors'])) {
        jsonResponse(['success' => false, 'message' => implode(' · ', $cart['errors'])], 400);
    }
    if (empty($cart['items'])) {
        jsonResponse(['success' => false, 'message' => t('api.no_items')], 400);
    }

    $orderId = createOrder($customer, $cart);

    if (!$orderId) {
        jsonResponse(['success' => false, 'message' => t('api.stock_changed')], 409);
    }

    // Só este cliente (esta sessão) pode ver o comprovativo
    $_SESSION['my_orders'][] = $orderId;
    unset($_SESSION['checkout_started']);

    jsonResponse([
        'success'  => true,
        'message'  => t('api.order_created'),
        'order_id' => $orderId,
        'total'    => $cart['total']
    ]);
}
