<?php

// API para gestão do carrinho

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['action'])) {
    jsonResponse(['success' => false, 'message' => t('api.invalid')], 400);
}

try {
    switch ($input['action']) {
        case 'check_stock':
        case 'validate_cart':
            handleValidateCart($input['cart'] ?? null);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Ação não reconhecida'], 400);
    }
} catch (Exception $e) {
    error_log("API Cart Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => t('api.server_error')], 500);
}


// Validar carrinho: verifica stock e devolve itens com preços vindos da BD
function handleValidateCart($cart) {
    if (!is_array($cart) || empty($cart)) {
        jsonResponse(['success' => false, 'message' => t('api.cart_empty')], 400);
    }

    $result = buildCartItems($cart);

    if (!empty($result['errors'])) {
        jsonResponse([
            'success' => false,
            'message' => implode(' · ', $result['errors']),
            'errors'  => $result['errors']
        ]);
    }

    if (empty($result['items'])) {
        jsonResponse(['success' => false, 'message' => t('api.no_items')], 400);
    }

    jsonResponse([
        'success' => true,
        'message' => t('api.cart_ok'),
        'items'        => $result['items'],
        'subtotal'     => $result['subtotal'],
        'delivery_fee' => $result['delivery_fee'],
        'total'        => $result['total']
    ]);
}
