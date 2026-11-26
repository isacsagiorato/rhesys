<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/notificacoes.php';
exige_login();

$id_usuario = (int) $_SESSION['id_usuario'];
$ajax = ($_POST['ajax'] ?? '') === '1';
$filtros = ['todas' => 'Todas', 'nao_lidas' => 'Não lidas', 'lidas' => 'Lidas'];
$filtro = (string) ($_GET['filtro'] ?? 'todas');
if (!isset($filtros[$filtro])) $filtro = 'todas';

/** Contagem de não lidas e lidas do usuário. */
function notificacoes_contagem(PDO $pdo, $id_usuario)
{
    $stmt = $pdo->prepare('SELECT SUM(lida = 0) AS nao_lidas, SUM(lida = 1) AS lidas FROM notificacao WHERE id_usuario = :u');
    $stmt->execute([':u' => $id_usuario]);
    $c = $stmt->fetch();
    return ['nao_lidas' => (int) $c['nao_lidas'], 'lidas' => (int) $c['lidas']];
}

// Abrir uma notificação: marca como lida e leva até o que ela fala
if (isset($_GET['abrir'])) {
    $id = (int) $_GET['abrir'];
    $stmt = $pdo->prepare('SELECT link FROM notificacao WHERE id_notificacao = :id AND id_usuario = :u');
    $stmt->execute([':id' => $id, ':u' => $id_usuario]);
    $link = $stmt->fetchColumn();
    if ($link !== false) {
        $pdo->prepare('UPDATE notificacao SET lida = 1 WHERE id_notificacao = :id AND id_usuario = :u')->execute([':id' => $id, ':u' => $id_usuario]);
    }
    $destino = $link !== false ? notificacao_link_seguro($link) : null;
    header('Location: ' . BASE_URL . ($destino ?? 'pages/notificacoes.php'));
    exit;
}

// Ações: marcar lida/não lida, marcar todas, excluir, limpar lidas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = verificar_csrf();
    if ($ok) {
        $acao = $_POST['acao'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);
        $params = [':u' => $id_usuario];
        $sqls = [
            'marcar_todas'    => 'UPDATE notificacao SET lida = 1 WHERE id_usuario = :u AND lida = 0',
            'marcar_lida'     => 'UPDATE notificacao SET lida = 1 WHERE id_usuario = :u AND id_notificacao = :id',
            'marcar_nao_lida' => 'UPDATE notificacao SET lida = 0 WHERE id_usuario = :u AND id_notificacao = :id',
            'excluir'         => 'DELETE FROM notificacao WHERE id_usuario = :u AND id_notificacao = :id',
            'limpar_lidas'    => 'DELETE FROM notificacao WHERE id_usuario = :u AND lida = 1',
        ];
        if (isset($sqls[$acao])) {
            if (strpos($sqls[$acao], ':id') !== false) $params[':id'] = $id;
            $pdo->prepare($sqls[$acao])->execute($params);
        } else {
            $ok = false;
        }
    }
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($ok ? 200 : 400);
        echo json_encode(['ok' => $ok] + notificacoes_contagem($pdo, $id_usuario));
        exit;
    }
    header('Location: notificacoes.php' . ($filtro !== 'todas' ? '?filtro=' . $filtro : ''));
    exit;
}

$por_pagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$itens = notificacoes_listar($pdo, $id_usuario, $por_pagina + 1, $filtro, ($pagina - 1) * $por_pagina);
$tem_mais = count($itens) > $por_pagina;
$itens = array_slice($itens, 0, $por_pagina);
$contagem = notificacoes_contagem($pdo, $id_usuario);
$nao_lidas = $contagem['nao_lidas'];
$lidas = $contagem['lidas'];
$total = $nao_lidas + $lidas;
$acao_form = 'notificacoes.php' . ($filtro !== 'todas' ? '?filtro=' . $filtro : '');

// Agrupa por dia: Hoje, Ontem, Esta semana, Anteriores
$grupos = [];
foreach ($itens as $n) {
    $d = (int) $n['dias'];
    $grupo = $d === 0 ? 'Hoje' : ($d === 1 ? 'Ontem' : ($d < 7 ? 'Esta semana' : 'Anteriores'));
    $grupos[$grupo][] = $n;
}

// Notificação a abrir já expandida (vinda do sino: ?ver=ID)
$ver = (int) ($_GET['ver'] ?? 0);

$titulo_pagina = 'Notificações';
require __DIR__ . '/../includes/header.php';
?>
<section class="notif-pagina" data-acao="notificacoes.php" data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <header class="notif-topo reveal">
        <div class="notif-topo-icone" aria-hidden="true"><i class="bi bi-bell"></i><?php if ($nao_lidas): ?><span></span><?php endif; ?></div>
        <div class="flex-grow-1">
            <h1 class="h2 mb-1">Notificações</h1>
            <p class="text-muted mb-0 notif-resumo">
                <?php echo $nao_lidas === 0 ? 'Você está em dia com tudo.' : ($nao_lidas === 1 ? 'Você tem 1 notificação não lida.' : "Você tem $nao_lidas notificações não lidas."); ?>
                <span class="d-none d-md-inline">Clique em uma notificação para ver tudo. Os avisos importantes também chegam no seu e-mail.</span>
            </p>
        </div>
        <div class="notif-topo-acoes">
            <?php if ($nao_lidas): ?>
                <form method="post" action="<?php echo $acao_form; ?>">
                    <?php echo csrf_field(); ?><input type="hidden" name="acao" value="marcar_todas">
                    <button class="btn btn-success btn-sm"><i class="bi bi-check2-all me-1"></i>Marcar todas como lidas</button>
                </form>
            <?php endif; ?>
            <?php if ($lidas): ?>
                <form method="post" action="<?php echo $acao_form; ?>" onsubmit="return confirm('Apagar todas as notificações já lidas?');">
                    <?php echo csrf_field(); ?><input type="hidden" name="acao" value="limpar_lidas">
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash3 me-1"></i>Limpar lidas</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <nav class="comp-abas notif-abas reveal" aria-label="Filtrar notificações">
        <?php foreach (['todas' => ['bi-inbox', $total], 'nao_lidas' => ['bi-envelope', $nao_lidas], 'lidas' => ['bi-envelope-open', $lidas]] as $v => [$icone, $qtd]): ?>
            <a href="notificacoes.php<?php echo $v !== 'todas' ? '?filtro=' . $v : ''; ?>" class="<?php echo $filtro === $v ? 'ativo' : ''; ?>" data-filtro="<?php echo $v; ?>" <?php echo $filtro === $v ? 'aria-current="page"' : ''; ?>>
                <i class="bi <?php echo $icone; ?>"></i><?php echo $filtros[$v]; ?> <span class="comp-aba-num"><?php echo $qtd; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$itens): ?>
        <div class="notif-vazio reveal">
            <div class="notif-vazio-arte" aria-hidden="true"><i class="bi <?php echo $filtro === 'lidas' ? 'bi-envelope-open' : 'bi-bell-slash'; ?>"></i></div>
            <h2 class="h5"><?php echo ['todas' => 'Nenhuma notificação ainda', 'nao_lidas' => 'Nenhuma notificação não lida', 'lidas' => 'Nenhuma notificação lida'][$filtro]; ?></h2>
            <p class="text-muted mb-3"><?php echo $filtro === 'nao_lidas' ? 'Tudo em dia! Quando chegar algo novo, aparece aqui e no seu e-mail.' : 'Quando alguém se interessar por um material seu, ou algo mudar nos materiais que você quer, o aviso aparece aqui e no seu e-mail.'; ?></p>
            <a href="compartilhamentos.php" class="btn btn-outline-success"><i class="bi bi-arrow-left-right me-1"></i>Ver compartilhamentos</a>
        </div>
    <?php else: ?>
        <?php $i = 0; foreach ($grupos as $grupo => $lista): ?>
            <h2 class="notif-grupo"><?php echo $grupo; ?></h2>
            <div class="notif-lista">
                <?php foreach ($lista as $n): $d = notificacao_para_array($n); ?>
                <details class="notif-item <?php echo $d['lida'] ? '' : 'nao-lida'; ?>" id="notificacao-<?php echo $d['id']; ?>" data-id="<?php echo $d['id']; ?>" style="--i: <?php echo min($i++, 12); ?>" <?php echo $ver === $d['id'] ? 'open' : ''; ?>>
                    <summary class="notif-resumo-item">
                        <span class="notif-icone cor-<?php echo $d['cor']; ?>"><i class="bi <?php echo $d['icone']; ?>"></i></span>
                        <span class="notif-texto">
                            <strong><?php echo htmlspecialchars($d['titulo']); ?></strong>
                            <span class="notif-previa"><?php echo htmlspecialchars($d['mensagem']); ?></span>
                            <small><i class="bi bi-clock"></i><?php echo $d['quando']; ?><span class="notif-status"><?php echo $d['lida'] ? 'Lida' : 'Não lida'; ?></span></small>
                        </span>
                        <i class="bi bi-chevron-down notif-seta" aria-hidden="true"></i>
                    </summary>
                    <div class="notif-corpo">
                        <p class="notif-mensagem"><?php echo nl2br(htmlspecialchars($d['mensagem'])); ?></p>
                        <p class="notif-data"><i class="bi bi-calendar3"></i>Recebida em <?php echo $d['data']; ?></p>
                        <div class="notif-corpo-acoes">
                            <?php if ($d['tem_link']): ?>
                                <a href="<?php echo htmlspecialchars($d['url']); ?>" class="btn btn-success btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i><?php echo htmlspecialchars($d['rotulo_link']); ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?php echo $acao_form; ?>" class="notif-form-lida">
                                <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                <input type="hidden" name="acao" value="<?php echo $d['lida'] ? 'marcar_nao_lida' : 'marcar_lida'; ?>">
                                <button class="btn btn-outline-success btn-sm"><i class="bi <?php echo $d['lida'] ? 'bi-envelope' : 'bi-envelope-open'; ?> me-1"></i><?php echo $d['lida'] ? 'Marcar como não lida' : 'Marcar como lida'; ?></button>
                            </form>
                            <form method="post" action="<?php echo $acao_form; ?>" class="notif-form-excluir">
                                <?php echo csrf_field(); ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Excluir</button>
                            </form>
                        </div>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($pagina > 1 || $tem_mais): ?>
        <nav class="d-flex justify-content-center gap-2 mt-4" aria-label="Páginas">
            <?php $base = 'notificacoes.php?' . ($filtro !== 'todas' ? 'filtro=' . $filtro . '&' : ''); ?>
            <?php if ($pagina > 1): ?><a class="btn btn-outline-success btn-sm" href="<?php echo $base . 'pagina=' . ($pagina - 1); ?>"><i class="bi bi-chevron-left"></i> Mais recentes</a><?php endif; ?>
            <?php if ($tem_mais): ?><a class="btn btn-outline-success btn-sm" href="<?php echo $base . 'pagina=' . ($pagina + 1); ?>">Mais antigas <i class="bi bi-chevron-right"></i></a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
