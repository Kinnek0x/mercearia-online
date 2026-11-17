<?php

// Logout do administrador (só por POST com token CSRF, para que um link
// externo não consiga terminar a sessão)

require_once '../includes/functions.php';
start_session_safe();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isValidCsrf()) {
    redirect(isAdminAuthenticated() ? 'dashboard.php' : 'login.php');
}

logAdminActivity('Logout');

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

redirect('login.php?message=logout_success');
