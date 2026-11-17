<?php

// API para gestão de produtos

require_once '../includes/functions.php';
start_session_safe();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetProducts();
            break;

        case 'POST':
            handleAddProduct();
            break;

        case 'PUT':
            handleUpdateProduct();
            break;

        case 'DELETE':
            handleDeleteProduct();
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    error_log("API Products Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => t('api.server_error')], 500);
}


// Ler o corpo JSON do pedido
function readJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'message' => 'Dados inválidos'], 400);
    }
    return $input;
}

// Validar preço, quantidade e imagem comuns a POST e PUT
function validateProductFields($input) {
    if (!isset($input['price'], $input['quantity']) || !is_numeric($input['price']) || !is_numeric($input['quantity'])) {
        jsonResponse(['success' => false, 'message' => 'Preço e quantidade têm de ser números'], 400);
    }

    $price = round((float)$input['price'], 2);
    $quantity = (int)$input['quantity'];
    $image = isset($input['image']) ? sanitizeInput($input['image']) : null;

    if ($price < 0) {
        jsonResponse(['success' => false, 'message' => 'Preço deve ser maior ou igual a 0'], 400);
    }
    if ($quantity < 0) {
        jsonResponse(['success' => false, 'message' => 'Quantidade deve ser maior ou igual a 0'], 400);
    }
    $category = sanitizeInput($input['category'] ?? 'Mercearia');
    $unit = sanitizeInput($input['unit'] ?? 'un');
    if (!in_array($category, PRODUCT_CATEGORIES, true)) {
        jsonResponse(['success' => false, 'message' => 'Categoria inválida'], 400);
    }
    if (!array_key_exists($unit, PRODUCT_UNITS)) {
        jsonResponse(['success' => false, 'message' => 'Unidade inválida'], 400);
    }
    if ($price > 10000 || $quantity > 100000) {
        jsonResponse(['success' => false, 'message' => 'Preço ou quantidade fora dos limites'], 400);
    }
    if (!isValidImagePath($image)) {
        jsonResponse(['success' => false, 'message' => 'Caminho de imagem inválido. Use images/products/nome.png ou um URL http(s).'], 400);
    }

    return [$category, $price, $unit, $quantity, $image ?: null];
}


// Nome em inglês (opcional)
function productNameEn($input) {
    $nameEn = sanitizeInput($input['name_en'] ?? '');
    if (mb_strlen($nameEn) > 255) {
        jsonResponse(['success' => false, 'message' => 'O nome em inglês pode ter no máximo 255 caracteres'], 400);
    }
    return $nameEn;
}


// Obter todos os produtos
function handleGetProducts() {
    $products = getProducts();

    if ($products === false) {
        jsonResponse(['success' => false, 'message' => t('api.products_error')], 500);
    }

    // Tipos numéricos e textos no idioma do cliente
    $products = array_map(function ($p) {
        return [
            'id'             => (int)$p['id'],
            'name'           => productName($p),
            'category'       => $p['category'],
            'category_label' => categoryLabel($p['category']),
            'price'          => (float)$p['price'],
            'unit'           => $p['unit'],
            'unit_label'     => unitShort($p['unit']),
            'quantity'       => (int)$p['quantity'],
            'image'          => $p['image'],
        ];
    }, $products);

    jsonResponse([
        'success'  => true,
        'products' => $products,
        // Regras da loja usadas pelo carrinho (os valores finais são sempre recalculados no servidor)
        'settings' => [
            'delivery_fee'       => DELIVERY_FEE,
            'free_delivery_from' => FREE_DELIVERY_FROM,
            'min_order'          => MIN_ORDER_TOTAL,
            'max_qty'            => ORDER_MAX_QTY_PER_PRODUCT,
            'categories'         => array_map(fn($c) => ['key' => $c, 'label' => categoryLabel($c)], PRODUCT_CATEGORIES),
        ],
    ]);
}


// Adicionar novo produto (apenas admin)
function handleAddProduct() {
    requireAdminApi();
    $input = readJsonInput();

    $name = sanitizeInput($input['name'] ?? '');
    if ($name === '' || mb_strlen($name) > 255) {
        jsonResponse(['success' => false, 'message' => 'Nome do produto é obrigatório (máx. 255 caracteres)'], 400);
    }

    [$category, $price, $unit, $quantity, $image] = validateProductFields($input);
    $nameEn = productNameEn($input);

    if (addProduct($name, $nameEn, $category, $price, $unit, $quantity, $image)) {
        $id = Database::lastInsertId();
        logAdminActivity('Product Added', "#$id $name");
        jsonResponse([
            'success' => true,
            'message' => 'Produto adicionado com sucesso',
            'product_id' => (int)$id
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Erro ao adicionar produto'], 500);
}


// Atualizar produto (apenas admin)
function handleUpdateProduct() {
    requireAdminApi();
    $input = readJsonInput();

    if (!isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID do produto é obrigatório'], 400);
    }

    $id = (int)$input['id'];
    [$category, $price, $unit, $quantity, $image] = validateProductFields($input);

    if (!getProduct($id)) {
        jsonResponse(['success' => false, 'message' => 'Produto não encontrado'], 404);
    }

    $nameEn = productNameEn($input);

    if (updateProduct($id, $nameEn, $category, $price, $unit, $quantity, $image)) {
        logAdminActivity('Product Updated', "#$id price=$price qty=$quantity");
        jsonResponse(['success' => true, 'message' => 'Produto atualizado com sucesso']);
    }

    jsonResponse(['success' => false, 'message' => 'Erro ao atualizar produto'], 500);
}


// Eliminar produto (apenas admin)
function handleDeleteProduct() {
    requireAdminApi();
    $input = readJsonInput();

    if (!isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'ID do produto é obrigatório'], 400);
    }

    $id = (int)$input['id'];
    $product = getProduct($id);
    if (!$product) {
        jsonResponse(['success' => false, 'message' => 'Produto não encontrado'], 404);
    }

    if (deleteProduct($id)) {
        logAdminActivity('Product Deleted', "#$id {$product['name']}");
        jsonResponse(['success' => true, 'message' => 'Produto eliminado com sucesso']);
    }

    jsonResponse(['success' => false, 'message' => 'Erro ao eliminar produto'], 500);
}
