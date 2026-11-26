<?php
/**
 * Dados do sino de notificações (JSON), consultado pelo js/notificacoes.js.
 * GET ?filtro=nao_lidas|lidas|todas -> { nao_lidas, lidas, itens: [...] }
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/notificacoes.php';
require_once __DIR__ . '/../config/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!usuario_logado()) {
    http_response_code(401);
    echo json_encode(['erro' => 'login']);
    exit;
}

$id_usuario = (int) $_SESSION['id_usuario'];
$filtro = (string) ($_GET['filtro'] ?? 'nao_lidas');
if (!in_array($filtro, ['todas', 'nao_lidas', 'lidas'], true)) $filtro = 'nao_lidas';

$stmt = $pdo->prepare('SELECT SUM(lida = 0) AS nao_lidas, SUM(lida = 1) AS lidas FROM notificacao WHERE id_usuario = :u');
$stmt->execute([':u' => $id_usuario]);
$contagem = $stmt->fetch();

echo json_encode([
    'nao_lidas' => (int) $contagem['nao_lidas'],
    'lidas' => (int) $contagem['lidas'],
    'filtro' => $filtro,
    'chat_nao_lidas' => chat_nao_lidas_total($pdo, $id_usuario),
    'itens' => array_map('notificacao_para_array', notificacoes_listar($pdo, $id_usuario, 8, $filtro)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
