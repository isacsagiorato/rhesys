<?php
/**
 * Sugestões de resíduos enquanto a pessoa digita (autocompletar).
 *
 * GET ?q=texto  ->  JSON {
 *   itens: [{id, nome, classe, classe_css, tipo, tipo_icone, foto, url}],
 *   corrigida: "texto corrigido" ou null,
 *   total: número de resultados da busca,
 *   tipo: {rotulo, icone, total, url} ou null  (quando o texto é um tipo de material),
 *   busca_url: link para a página de resultados completos
 * }
 * Usa a mesma busca tolerante a erros da página de resíduos (config/busca.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/residuos.php';
require_once __DIR__ . '/../config/busca.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=60');

$q = $_GET['q'] ?? '';
$q = is_string($q) ? mb_substr(ltrim($q), 0, 80, 'UTF-8') : '';
$vazio = ['itens' => [], 'corrigida' => null, 'total' => 0, 'tipo' => null, 'busca_url' => null];
if (trim($q) === '') {
    echo json_encode($vazio);
    exit;
}

$residuos = $pdo->query(
    "SELECT r.id_residuo, r.nome, r.palavras_chave, r.descricao, r.tipo_material, c.nome AS classificacao_nome
     FROM residuo r INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
     WHERE r.nome <> 'Resíduos orgânicos'"
)->fetchAll();

[$achados, $corrigida, $total] = sugestoes_residuos($residuos, $q, 6);

$itens = [];
foreach ($achados as $res) {
    $classe = classe_residuo($res['classificacao_nome']);
    $tipo = TIPOS_RESIDUO[$res['tipo_material'] ?? ''] ?? null;
    $itens[] = [
        'id' => (int) $res['id_residuo'],
        'nome' => $res['nome'],
        'classe' => $classe['curto'],
        'classe_css' => $classe['css'],
        'tipo' => $tipo['rotulo'] ?? '',
        'tipo_icone' => $tipo['icone'] ?? 'bi-recycle',
        'foto' => imagem_residuo($res['nome'], $res['tipo_material'], $res['classificacao_nome']),
        'url' => BASE_URL . 'pages/residuos.php?id=' . (int) $res['id_residuo'],
    ];
}

// O texto é um tipo de material? Oferece o atalho "Ver todos os ..."
$atalho_tipo = null;
$chave_tipo = busca_tipo_correspondente($q);
if ($chave_tipo !== null) {
    $qtd = 0;
    foreach ($residuos as $res) if (($res['tipo_material'] ?? '') === $chave_tipo) $qtd++;
    $atalho_tipo = [
        'rotulo' => TIPOS_RESIDUO[$chave_tipo]['rotulo'],
        'icone' => TIPOS_RESIDUO[$chave_tipo]['icone'],
        'total' => $qtd,
        'url' => BASE_URL . 'pages/residuos.php?tipo=' . rawurlencode($chave_tipo) . '#resultados',
    ];
}

echo json_encode([
    'itens' => $itens,
    'corrigida' => $corrigida,
    'total' => $total,
    'tipo' => $atalho_tipo,
    'busca_url' => BASE_URL . 'pages/residuos.php?busca=' . rawurlencode(trim($q)) . '#resultados',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
