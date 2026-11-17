<?php

// Configurações da base de dados
// As credenciais NÃO ficam no código: vêm de config/config.local.php (fora do Git,
// criado a partir de config.local.example.php) ou de variáveis de ambiente do servidor.
$localConfig = __DIR__ . '/config.local.php';
$local = is_file($localConfig) ? require $localConfig : [];

define('DB_HOST', $local['db_host'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', $local['db_name'] ?? getenv('DB_NAME') ?: 'mercearia_online');
define('DB_USER', $local['db_user'] ?? getenv('DB_USER') ?: '');
define('DB_PASS', $local['db_pass'] ?? getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
unset($localConfig, $local);


// Classe para conexão com a base de dados
class Database {
    private static $connection = null;

    // Obter conexão com a base de dados (lança PDOException em caso de erro)
    public static function getConnection() {
        if (self::$connection === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            try {
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Prepared statements nativos: inteiros chegam como int (e não como string)
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$connection->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
            } catch (PDOException $e) {
                // Não mostrar detalhes da BD ao utilizador; apenas registar no log
                error_log("Erro na conexão com a base de dados: " . $e->getMessage());
                throw $e;
            }
        }

        return self::$connection;
    }

    // Executar consulta SELECT
    public static function query($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage());
            return false;
        }
    }

    // Executar consulta INSERT, UPDATE, DELETE
    public static function execute($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database Execute Error: " . $e->getMessage());
            return false;
        }
    }

    // Obter último ID inserido
    public static function lastInsertId() {
        return self::getConnection()->lastInsertId();
    }

    // Iniciar transação
    public static function beginTransaction() {
        return self::getConnection()->beginTransaction();
    }

    // Confirmar transação
    public static function commit() {
        return self::getConnection()->commit();
    }

    // Desfazer transação (só se existir uma ativa)
    public static function rollback() {
        $conn = self::getConnection();
        return $conn->inTransaction() ? $conn->rollBack() : false;
    }
}
