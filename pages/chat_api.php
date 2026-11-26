<?php
/**
 * API do chat (JSON), usada pelo js/chat.js.
 *  GET  ?id=ID&depois=ULTIMO  -> mensagens novas da conversa (e marca como lidas)
 *  GET  ?lista=1              -> lista de conversas
 *  POST acao=enviar, id, texto, foto (opcional), csrf_token -> envia uma mensagem
 *  POST acao=apagar, id, id_mensagem, csrf_token             -> apaga uma mensagem sua
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder($dados, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!usuario_logado()) responder(['ok' => false, 'erro' => 'Sua sessão expirou. Entre novamente.'], 401);
$id_usuario = (int) $_SESSION['id_usuario'];

if (isset($_GET['lista'])) {
    responder([
        'ok' => true,
        'nao_lidas' => chat_nao_lidas_total($pdo, $id_usuario),
        'conversas' => array_map(fn($c) => chat_conversa_para_array($c, $id_usuario), chat_conversas_usuario($pdo, $id_usuario)),
    ]);
}

$id = (int) ($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id'] ?? 0) : ($_GET['id'] ?? 0));
$conversa = chat_conversa($pdo, $id, $id_usuario);
if (!$conversa) responder(['ok' => false, 'erro' => 'Conversa não encontrada.'], 404);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) responder(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 400);
    if (($_POST['acao'] ?? 'enviar') === 'apagar') {
        $erro = chat_apagar_mensagem($pdo, $conversa, (int) ($_POST['id_mensagem'] ?? 0), $id_usuario);
    } else {
        [$id_mensagem, $erro] = chat_enviar($pdo, $conversa, $id_usuario, $_POST['texto'] ?? '', $_FILES['foto'] ?? null);
    }
    if ($erro) responder(['ok' => false, 'erro' => $erro], 422);
}

$depois = (int) ($_GET['depois'] ?? $_POST['depois'] ?? 0);
chat_marcar_lidas($pdo, $conversa['id_interesse'], $id_usuario);

// Quais das minhas mensagens a outra pessoa já leu (para os "vistos")
$stmt = $pdo->prepare('SELECT COALESCE(MAX(id_mensagem), 0) FROM mensagem WHERE id_interesse = :i AND id_remetente = :u AND lida = 1');
$stmt->execute([':i' => $conversa['id_interesse'], ':u' => $id_usuario]);

responder([
    'ok' => true,
    'mensagens' => array_map(fn($m) => chat_mensagem_para_array($m, $id_usuario), chat_mensagens($pdo, $conversa['id_interesse'], $depois)),
    'lida_ate' => (int) $stmt->fetchColumn(),
    'apagadas' => chat_ids_apagadas($pdo, $conversa['id_interesse']),
    'pode_enviar' => $conversa['pode_enviar'],
    'aviso' => $conversa['aviso'],
    'nao_lidas' => chat_nao_lidas_total($pdo, $id_usuario),
]);
