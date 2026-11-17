<?php

// =========================================================
// Idiomas (PT / EN) da loja pública
//   ?lang=en      -> muda o idioma e guarda num cookie por 1 ano
//   cookie "lang" -> idioma escolhido antes
//   senão         -> idioma preferido do browser (Accept-Language), por defeito PT
// =========================================================

define('SUPPORTED_LANGS', ['pt' => 'Português', 'en' => 'English']);
define('DEFAULT_LANG', 'pt');

function currentLang(): string {
    static $lang = null;
    if ($lang !== null) return $lang;

    // O painel de administração é só em português (preços em "1,50 €" mesmo com o cookie em EN)
    if (str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
        return $lang = DEFAULT_LANG;
    }

    $requested = $_GET['lang'] ?? null;
    if (is_string($requested) && isset(SUPPORTED_LANGS[$requested])) {
        $lang = $requested;
        if (!headers_sent()) {
            setcookie('lang', $lang, [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        }
        return $lang;
    }

    $cookie = $_COOKIE['lang'] ?? null;
    if (is_string($cookie) && isset(SUPPORTED_LANGS[$cookie])) {
        return $lang = $cookie;
    }

    $accept = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2));
    return $lang = isset(SUPPORTED_LANGS[$accept]) ? $accept : DEFAULT_LANG;
}

// Todas as traduções do idioma atual (com o PT como reserva)
function translations(): array {
    static $cache = [];
    $lang = currentLang();
    if (!isset($cache[$lang])) {
        $pt = require __DIR__ . '/lang/pt.php';
        $cache[$lang] = $lang === 'pt' ? $pt : array_merge($pt, require __DIR__ . "/lang/$lang.php");
    }
    return $cache[$lang];
}

// Traduzir uma chave. Os parâmetros {nome} são substituídos (sem escape: use e() ao imprimir texto simples).
function t(string $key, array $params = []): string {
    $text = translations()[$key] ?? $key;
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

// Textos da loja que existem nas duas línguas (config/store.php: X e X_EN)
function storeText(string $name): string {
    $en = $name . '_EN';
    return (currentLang() === 'en' && defined($en)) ? constant($en) : constant($name);
}

function categoryLabel(string $category): string {
    return translations()['cat.' . $category] ?? $category;
}

function unitShort(string $unit): string {
    return translations()['unit.' . $unit] ?? $unit;
}

function statusLabel(string $status): string {
    return translations()['status.' . $status] ?? $status;
}

// Nome do produto no idioma atual (name_en se existir)
function productName(array $product): string {
    if (currentLang() === 'en' && !empty($product['name_en'])) {
        return $product['name_en'];
    }
    return $product['name'] ?? $product['product_name'] ?? '';
}

// URL da página atual noutro idioma
function langUrl(string $lang): string {
    $query = $_GET;
    $query['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    return $path . '?' . http_build_query($query);
}

function htmlLang(): string {
    return currentLang() === 'en' ? 'en' : 'pt-PT';
}

// Traduções usadas pelo JavaScript (chaves "js.*"), para injetar na página
function jsTranslations(): string {
    $js = [];
    foreach (translations() as $key => $value) {
        if (strncmp($key, 'js.', 3) === 0) $js[substr($key, 3)] = $value;
    }
    return json_encode(['lang' => currentLang(), 'strings' => $js], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
}
