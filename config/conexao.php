<?php
/**
 * Conexão com o banco de dados MySQL usando PDO.
 * Este arquivo é incluído em todas as páginas que precisam acessar o banco.
 */

$host = getenv('RHESYS_DB_HOST') ?: 'localhost';
$port = getenv('RHESYS_DB_PORT') ?: '3306';
$dbname = getenv('RHESYS_DB_NAME') ?: 'rhesys';
$usuario_db = getenv('RHESYS_DB_USER') ?: 'root';
$senha_db = getenv('RHESYS_DB_PASSWORD') ?: '';
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
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}
