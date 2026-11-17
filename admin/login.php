<?php
require_once '../includes/functions.php';
start_session_safe();

if (isAdminAuthenticated()) {
    redirect('dashboard.php');
}

// Iniciar sessão do admin (regenera o ID para evitar "session fixation"
// e não guarda o hash da password na sessão)
function loginAdmin($user) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = ['id' => (int)$user['id'], 'username' => $user['username']];
    $_SESSION['admin_last_activity'] = time();
    unset($_SESSION['csrf_token']); // novo token para a nova sessão
    clearLoginFailures();
    logAdminActivity('Login');
}

$error = '';
$status = 401;
$isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $isJson ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST;
    $username = sanitizeInput($input['username'] ?? '');
    $password = (string)($input['password'] ?? '');

    $lock = loginLockRemaining();

    if ($lock > 0) {
        // Demasiadas tentativas falhadas a partir deste IP
        $error = 'Demasiadas tentativas falhadas. Tente novamente dentro de ' . ceil($lock / 60) . ' minuto(s).';
        $status = 429;
    } elseif ($username === '' || $password === '') {
        $error = 'Por favor, preencha todos os campos.';
        $status = 400;
    } else {
        $user = checkAdminLogin($username, $password);
        if ($user) {
            loginAdmin($user);
            if ($isJson) {
                jsonResponse(['success' => true, 'message' => 'Login realizado com sucesso']);
            }
            redirect('dashboard.php');
        }
        registerLoginFailure();
        error_log("Login falhado para '$username' a partir de " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        $error = 'Nome de utilizador ou palavra-passe incorretos.';
    }

    if ($isJson) {
        jsonResponse(['success' => false, 'message' => $error], $status);
    }
}

$messages = [
    'logout_success' => 'Sessão terminada com sucesso.',
    'expired'        => 'A sua sessão expirou por inatividade. Inicie sessão novamente.',
];
$info = $messages[$_GET['message'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração — <?php echo e(STORE_NAME); ?></title>
    <link rel="icon" href="../images/favicon.svg" type="image/svg+xml">
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500..700;1,9..144,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="auth-page">
        <aside class="auth-aside">
            <a href="../index.php" class="brand">
                <span class="brand-mark" aria-hidden="true">🌿</span>
                <span><?php echo e(STORE_NAME); ?><small>Área de administração</small></span>
            </a>
            <div>
                <h2>Gerir a loja num só lugar.</h2>
                <p>Atualize preços e stock, acompanhe as encomendas e adicione novos produtos.</p>
            </div>
            <small style="opacity:.6">Acesso restrito a administradores.</small>
        </aside>

        <main class="auth-main">
            <div class="login-container">
                <h1>Iniciar sessão</h1>
                <p>Introduza as suas credenciais de administrador.</p>

                <div id="alert-container-inline">
                    <?php if ($error): ?>
                        <div class="alert alert-error"><span><?php echo e($error); ?></span></div>
                    <?php elseif ($info): ?>
                        <div class="alert alert-success"><span><?php echo e($info); ?></span></div>
                    <?php endif; ?>
                </div>

                <form method="POST" id="login-form">
                    <div class="form-group">
                        <label for="username">Nome de utilizador</label>
                        <input type="text" id="username" name="username" required autocomplete="username" autofocus
                               value="<?php echo e($_POST['username'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Palavra-passe</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>

                    <div class="stack" style="gap:10px">
                        <button type="submit" class="btn btn-lg btn-full" id="login-btn">Entrar</button>
                        <a href="../index.php" class="btn btn-ghost btn-full">← Voltar à loja</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Login via AJAX (se falhar a ligação, o formulário é submetido normalmente)
        document.getElementById('login-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('login-btn');
            btn.disabled = true;
            btn.textContent = 'A entrar…';

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = 'dashboard.php';
                    return;
                }
                showAlert('error', result.message);
            } catch (error) {
                this.submit();
                return;
            }
            btn.disabled = false;
            btn.textContent = 'Entrar';
        });

        function showAlert(type, message) {
            const container = document.getElementById('alert-container-inline');
            const div = document.createElement('div');
            div.className = `alert alert-${type}`;
            const span = document.createElement('span');
            span.textContent = message;
            div.appendChild(span);
            container.innerHTML = '';
            container.appendChild(div);
        }
    </script>
</body>
</html>
