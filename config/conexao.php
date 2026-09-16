<?php
/**
 * Conexão com o banco de dados MySQL usando PDO.
 * Este arquivo é incluído em todas as páginas que precisam acessar o banco.
 */

$host = 'localhost';
$dbname = 'rhesys';
$usuario_db = 'root';       // altere conforme seu ambiente
$senha_db = '';             // altere conforme seu ambiente
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

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
