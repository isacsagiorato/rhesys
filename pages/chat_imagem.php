<?php
/**
 * Entrega a foto de uma mensagem do chat, só para os dois participantes
 * da conversa. As fotos ficam em storage/chat (sem acesso direto).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/chat.php';

function nao_encontrada()
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Imagem não encontrada.');
}

if (!usuario_logado()) nao_encontrada();

$stmt = $pdo->prepare('SELECT id_interesse, imagem FROM mensagem WHERE id_mensagem = :id AND imagem IS NOT NULL');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$m = $stmt->fetch();
if (!$m || !chat_conversa($pdo, $m['id_interesse'], $_SESSION['id_usuario'])) nao_encontrada();

$raiz = realpath(chat_pasta_fotos());
$arquivo = realpath(dirname(__DIR__) . '/' . $m['imagem']);
if (!$raiz || !$arquivo || strpos($arquivo, $raiz . DIRECTORY_SEPARATOR) !== 0 || !is_file($arquivo)) nao_encontrada();

$tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
if (!isset($tipos[$ext])) nao_encontrada();

// A foto nunca muda: o navegador pode guardar (só para este usuário)
$etag = '"' . md5($m['imagem']) . '"';
header('Cache-Control: private, max-age=604800, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $tipos[$ext]);
header('Content-Length: ' . filesize($arquivo));
if (isset($_GET['baixar'])) {
    header('Content-Disposition: attachment; filename="foto-rhesys-' . (int) $_GET['id'] . '.' . $ext . '"');
}
readfile($arquivo);
