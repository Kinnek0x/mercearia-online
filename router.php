<?php
// Router para o servidor de desenvolvimento do PHP, que ignora o .htaccess.
// Uso:  php -S localhost:8000 router.php
// Bloqueia as mesmas pastas e ficheiros que o .htaccess protege no Apache.

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

$blockedDirs = '#^/(bin|config|includes|logs|uploads|arquivos temporario)(/|$)#i';
$blockedFiles = '#(\.(sql|log|ini|env|sh|bak|txt|md|json)$)|(/\.)|(^/router\.php$)#i';
$phpInImages = '#^/images/.*\.(php\d?|phtml|phar)$#i';

if (preg_match($blockedDirs, $path) || preg_match($blockedFiles, $path) || preg_match($phpInImages, $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 - Acesso negado';
    return true;
}

// Cabeçalhos de segurança também nos ficheiros estáticos (CSS, JS, imagens)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Página 404 para endereços que não existem
$file = __DIR__ . $path;
if ($path !== '/' && !is_file($file) && !is_dir($file)) {
    define('SITE_BASE', '/');
    require __DIR__ . '/pages/404.php';
    return true;
}

return false; // servir o ficheiro pedido normalmente
