<?php

//  API para upload de imagens de produtos
 

require_once '../includes/functions.php';

// Verificar se é admin
requireAdminApi(); // admin autenticado + token CSRF

// Verificar se é POST e se tem arquivo
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image']) || is_array($_FILES['image']['name'])) {
    jsonResponse(['success' => false, 'message' => 'Método inválido ou arquivo não enviado'], 400);
}

$file = $_FILES['image'];

// Verificar erros de upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = '';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg = 'Arquivo muito grande';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg = 'Upload incompleto';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg = 'Nenhum arquivo enviado';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg = 'Pasta temporária não encontrada';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg = 'Erro ao escrever arquivo';
            break;
        default:
            $errorMsg = 'Erro desconhecido no upload';
    }
    jsonResponse(['success' => false, 'message' => $errorMsg], 400);
}

// Fazer upload da imagem
$result = uploadProductImage($file);

if ($result['success']) {
    // Log da atividade
    logAdminActivity('Image Upload', $result['filename']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Upload realizado com sucesso',
        'filename' => $result['filename'],
        'path' => $result['path']
    ]);
} else {
    jsonResponse([
        'success' => false,
        'message' => $result['message']
    ], 400);
}
