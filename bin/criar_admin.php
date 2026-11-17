<?php

// =========================================================
// Criar um administrador (ou mudar a password de um existente)
//
//   php bin/criar_admin.php
//   php bin/criar_admin.php --user=maria
//
// Só corre no terminal: não pode ser aberto pelo browser.
// Serve também para recuperar o acesso se a password for esquecida.
// =========================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 - Acesso negado');
}

require_once __DIR__ . '/../includes/functions.php';

$interactive = stream_isatty(STDIN);

// Ler uma linha do terminal (sem mostrar o texto, no caso das passwords)
function ask(string $label, bool $hidden = false): string {
    global $interactive;
    echo $label;
    $sttyOk = false;
    if ($hidden && $interactive) {
        $sttyOk = shell_exec('stty -echo 2>/dev/null') !== false;
    }
    $line = fgets(STDIN);
    if ($sttyOk) {
        shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    }
    if ($line === false) {
        fwrite(STDERR, PHP_EOL . "Cancelado." . PHP_EOL);
        exit(1);
    }
    return rtrim($line, "\r\n");
}

function fail(string $message): void {
    fwrite(STDERR, "✗ $message" . PHP_EOL);
    exit(1);
}

echo "Mercearia Online — criar administrador" . PHP_EOL . PHP_EOL;

// Ligação à base de dados
try {
    Database::getConnection();
} catch (Throwable $e) {
    fail("Não foi possível ligar à base de dados. Confirme config/config.local.php e se o database.sql foi importado.");
}

// Nome de utilizador
$opts = getopt('', ['user:']);
$username = trim((string)($opts['user'] ?? ''));
if ($username === '') {
    $username = trim(ask('Nome de utilizador [admin]: ')) ?: 'admin';
}
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    fail('Nome de utilizador inválido: use 3 a 50 letras, números, ponto, hífen ou _.');
}

$existing = Database::query('SELECT id FROM users WHERE username = ?', [$username]);
if ($existing === false) {
    fail('Erro ao ler a tabela de utilizadores. O database.sql foi importado?');
}

if ($existing) {
    $answer = strtolower(trim(ask("O utilizador \"$username\" já existe. Mudar a password? [s/N]: ")));
    if (!in_array($answer, ['s', 'sim', 'y', 'yes'], true)) {
        echo "Nada foi alterado." . PHP_EOL;
        exit(0);
    }
}

// Password (pedida duas vezes, sem aparecer no ecrã)
echo "Regras: 8 a 72 caracteres, com letras e números." . PHP_EOL;
for ($attempt = 1; ; $attempt++) {
    $password = ask('Password: ', true);
    $error = adminPasswordError($password, $username);
    if ($error === null) {
        if (ask('Repita a password: ', true) === $password) break;
        $error = 'As passwords não coincidem.';
    }
    if ($attempt >= 3 || !$interactive) fail($error);
    echo "✗ $error Tente de novo." . PHP_EOL;
}

$hash = hashPassword($password);

$ok = $existing
    ? Database::execute('UPDATE users SET password = ? WHERE username = ?', [$hash, $username])
    : Database::execute('INSERT INTO users (username, password) VALUES (?, ?)', [$username, $hash]);

if (!$ok) {
    fail('Erro ao guardar na base de dados.');
}

echo PHP_EOL . ($existing ? "✓ Password de \"$username\" alterada." : "✓ Administrador \"$username\" criado.") . PHP_EOL;
echo "  Painel: /admin/login.php" . PHP_EOL;
