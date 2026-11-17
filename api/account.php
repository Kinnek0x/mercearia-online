<?php

// API da conta do administrador (alterar palavra-passe)

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

requireAdminApi();

$input = json_decode(file_get_contents('php://input'), true);
$current = is_array($input) ? (string)($input['current_password'] ?? '') : '';
$new = is_array($input) ? (string)($input['new_password'] ?? '') : '';

if ($current === '' || $new === '') {
    jsonResponse(['success' => false, 'message' => 'Preencha a palavra-passe atual e a nova.'], 400);
}

// Regras mínimas: 8+ caracteres, com letras e números, diferente da atual
if (strlen($new) < 8 || strlen($new) > 72 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
    jsonResponse(['success' => false, 'message' => 'A nova palavra-passe tem de ter 8 a 72 caracteres, com letras e números.'], 400);
}
if ($new === $current) {
    jsonResponse(['success' => false, 'message' => 'A nova palavra-passe tem de ser diferente da atual.'], 400);
}

$userId = (int)($_SESSION['admin_user']['id'] ?? 0);
$user = Database::query("SELECT id, password FROM users WHERE id = ?", [$userId]);

if (!$user || !verifyPassword($current, $user[0]['password'])) {
    jsonResponse(['success' => false, 'message' => 'A palavra-passe atual está incorreta.'], 400);
}

if (!Database::execute("UPDATE users SET password = ? WHERE id = ?", [hashPassword($new), $userId])) {
    jsonResponse(['success' => false, 'message' => 'Erro ao guardar a nova palavra-passe.'], 500);
}

session_regenerate_id(true);
logAdminActivity('Password Changed');

jsonResponse(['success' => true, 'message' => 'Palavra-passe alterada com sucesso.']);
