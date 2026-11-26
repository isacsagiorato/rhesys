<?php
/**
 * Conexão com o banco de dados MySQL usando PDO.
 * Este arquivo é incluído em todas as páginas que precisam acessar o banco.
 */

// Dados do banco da hospedagem: copie config/conexao_local.exemplo.php
// para config/conexao_local.php e preencha. Esse arquivo fica fora do Git.
if (is_file(__DIR__ . '/conexao_local.php')) {
    require_once __DIR__ . '/conexao_local.php';
}

$host = defined('DB_HOST') ? DB_HOST : (getenv('RHESYS_DB_HOST') ?: 'localhost');
$port = defined('DB_PORT') ? DB_PORT : (getenv('RHESYS_DB_PORT') ?: '3306');
$dbname = defined('DB_NAME') ? DB_NAME : (getenv('RHESYS_DB_NAME') ?: 'rhesys');
$usuario_db = defined('DB_USER') ? DB_USER : (getenv('RHESYS_DB_USER') ?: 'root');
$senha_db = defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('RHESYS_DB_PASSWORD') ?: '');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $usuario_db, $senha_db, $opcoes);
} catch (PDOException $e) {
    error_log('Rhesys: falha na conexão com o banco: ' . $e->getMessage());
    $local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true);
    die($local
        ? 'Erro na conexão com o banco de dados: ' . $e->getMessage()
        : 'O site está em manutenção. Tente novamente em alguns minutos.');
}
