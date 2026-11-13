<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
exige_login();

$titulo_pagina = 'Detalhes do material';
$erros = [];
$mensagens = [
    'interesse' => 'Seu interesse foi registrado. O ofertante poderá aceitá-lo e liberar o contato para a retirada.',
    'interesse_cancelado' => 'Seu interesse foi cancelado.',
];
$sucesso = $mensagens[$_GET['sucesso'] ?? ''] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        try {
            $pdo->beginTransaction();
            if ($acao === 'demonstrar_interesse') {
                $stmt = $pdo->prepare('SELECT id_usuario_ofertante, status FROM compartilhamento_residuo WHERE id_compartilhamento = :id FOR UPDATE');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $oferta = $stmt->fetch();
                if (!$oferta || $oferta['status'] !== 'disponivel') {
                    throw new RuntimeException('Esta oferta não está mais disponível.');
                }
                if ((int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario']) {
                    throw new RuntimeException('Você não pode demonstrar interesse na própria oferta.');
                }
                $stmt = $pdo->prepare('SELECT id_interesse, status FROM interesse_compartilhamento WHERE id_compartilhamento = :oferta AND id_usuario = :usuario FOR UPDATE');
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                $stmt->execute();
                $interesse = $stmt->fetch();
                if ($interesse && in_array($interesse['status'], ['pendente', 'aceito'], true)) {
                    throw new RuntimeException('Você já registrou interesse nesta oferta.');
                }
                if ($interesse) {
                    $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'pendente', data_interesse = CURRENT_TIMESTAMP WHERE id_interesse = :id");
                    $stmt->bindValue(':id', $interesse['id_interesse'], PDO::PARAM_INT);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO interesse_compartilhamento (id_compartilhamento, id_usuario) VALUES (:oferta, :usuario)');
                    $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                }
                $stmt->execute();
                $pdo->commit();
                header('Location: compartilhamento.php?id=' . $id . '&sucesso=interesse');
                exit;
            }
            if ($acao === 'cancelar_interesse') {
                $id_interesse = (int) ($_POST['id_interesse'] ?? 0);
                $stmt = $pdo->prepare('SELECT i.id_interesse, i.status, i.id_compartilhamento, c.status AS status_oferta FROM interesse_compartilhamento i INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento WHERE i.id_interesse = :id AND i.id_usuario = :usuario FOR UPDATE');
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                $stmt->execute();
                $interesse = $stmt->fetch();
                if (!$interesse || !in_array($interesse['status'], ['pendente', 'aceito'], true)) {
                    throw new RuntimeException('O interesse não foi encontrado ou já está encerrado.');
                }
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'cancelado' WHERE id_interesse = :id");
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                if ($interesse['status'] === 'aceito' && $interesse['status_oferta'] === 'reservado') {
                    $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'disponivel' WHERE id_compartilhamento = :id");
                    $stmt->bindValue(':id', $interesse['id_compartilhamento'], PDO::PARAM_INT);
                    $stmt->execute();
                }
                $pdo->commit();
                header('Location: compartilhamento.php?id=' . $id . '&sucesso=interesse_cancelado');
                exit;
            }
            throw new RuntimeException('Ação inválida.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro no compartilhamento: ' . $e->getMessage());
            $erros[] = 'Não foi possível concluir a operação. Tente novamente.';
        }
    }
}
$sql = "SELECT c.*,
               COALESCE(NULLIF(c.nome, ''), r.nome) AS material_nome,
               COALESCE(NULLIF(c.categoria, ''), cl.nome) AS material_categoria,
               u.nome AS ofertante_nome, u.email AS ofertante_email,
               mi.id_interesse AS meu_interesse_id, mi.status AS meu_interesse_status,
               (SELECT COUNT(*) FROM interesse_compartilhamento i
                 WHERE i.id_compartilhamento = c.id_compartilhamento
                   AND i.status IN ('pendente', 'aceito')) AS total_interesses
        FROM compartilhamento_residuo c
        LEFT JOIN residuo r ON r.id_residuo = c.id_residuo
        LEFT JOIN classificacao cl ON cl.id_classificacao = r.id_classificacao
        INNER JOIN usuario u ON u.id_usuario = c.id_usuario_ofertante
        LEFT JOIN interesse_compartilhamento mi
          ON mi.id_compartilhamento = c.id_compartilhamento AND mi.id_usuario = :usuario
        WHERE c.id_compartilhamento = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
$oferta = $stmt->fetch();

if (!$oferta) {
    require __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-warning mt-4">Material não encontrado.</div>';
    echo '<a href="compartilhamentos.php" class="btn btn-success"><i class="bi bi-arrow-left me-1"></i>Voltar aos materiais</a>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$propria = (int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario'];
$meu_interesse = $oferta['meu_interesse_status'];
$categoria_rotulo = ($oferta['categoria'] !== null && $oferta['categoria'] !== '')
    ? rotulo_categoria_material($oferta['categoria'])
    : ($oferta['material_categoria'] !== null && $oferta['material_categoria'] !== '' ? $oferta['material_categoria'] : 'Resíduo');

require __DIR__ . '/../includes/header.php';
?>
<a href="compartilhamentos.php" class="btn btn-outline-success mb-3"><i class="bi bi-arrow-left me-1"></i>Voltar aos materiais</a>

<?php if ($sucesso): ?><div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="detail-photo mb-4">
    <img src="<?php echo imagem_material($oferta['imagem']); ?>" alt="Foto do material: <?php echo htmlspecialchars($oferta['material_nome']); ?>">
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="info-card"><div class="card-body p-4 p-lg-5">
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="badge text-bg-<?php echo classe_status_compartilhamento($oferta['status']); ?>"><?php echo rotulo_status_compartilhamento($oferta['status']); ?></span>
                <span class="badge-soft"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($categoria_rotulo); ?></span>
                <span class="<?php echo classe_estado_conservacao($oferta['estado_conservacao']); ?>"><i class="bi bi-stars me-1"></i><?php echo htmlspecialchars(rotulo_estado_conservacao($oferta['estado_conservacao'])); ?></span>
            </div>

            <h1 class="fs-2 mb-2"><?php echo htmlspecialchars($oferta['material_nome']); ?></h1>
            <p class="text-muted small mb-4">Oferecido por <strong><?php echo htmlspecialchars($oferta['ofertante_nome']); ?></strong> em <?php echo date('d/m/Y', strtotime($oferta['data_publicacao'])); ?></p>

            <h2 class="h5 mb-2">Descrição</h2>
            <p class="mb-4"><?php echo nl2br(htmlspecialchars($oferta['descricao'])); ?></p>

            <?php if (!empty($oferta['condicoes_retirada'])): ?>
                <div class="condicoes-box mb-4">
                    <h2 class="h6 mb-1"><i class="bi bi-truck me-1"></i>Condições e orientações para retirada</h2>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($oferta['condicoes_retirada'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($propria): ?>
                <a href="meus_compartilhamentos.php#oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-success"><i class="bi bi-gear me-1"></i>Gerenciar este material</a>
            <?php elseif ($meu_interesse === 'aceito'): ?>
                <div class="contato-box mb-3">
                    <h2 class="h6 mb-2"><i class="bi bi-check-circle me-1"></i>Seu interesse foi aceito!</h2>
                    <p class="small mb-2">Entre em contato com o ofertante para combinar a retirada:</p>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($oferta['ofertante_nome']); ?></strong></p>
                    <p class="mb-2 contato-mail"><?php echo htmlspecialchars($oferta['ofertante_email']); ?></p>
                    <a href="mailto:<?php echo htmlspecialchars($oferta['ofertante_email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-success"><i class="bi bi-envelope me-1"></i>Enviar e-mail</a>
                </div>
                <form method="post" action="compartilhamento.php?id=<?php echo $id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="acao" value="cancelar_interesse">
                    <input type="hidden" name="id_interesse" value="<?php echo (int) $oferta['meu_interesse_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar meu interesse</button>
                </form>
            <?php elseif ($meu_interesse === 'pendente'): ?>
                <p class="mb-2"><span class="badge-soft"><i class="bi bi-hourglass-split me-1"></i>Seu interesse está pendente de aceite pelo ofertante.</span></p>
                <form method="post" action="compartilhamento.php?id=<?php echo $id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="acao" value="cancelar_interesse">
                    <input type="hidden" name="id_interesse" value="<?php echo (int) $oferta['meu_interesse_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar meu interesse</button>
                </form>
            <?php elseif ($oferta['status'] === 'disponivel'): ?>
                <form method="post" action="compartilhamento.php?id=<?php echo $id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="acao" value="demonstrar_interesse">
                    <input type="hidden" name="id_compartilhamento" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-hand-thumbs-up me-1"></i>Tenho interesse</button>
                </form>
                <p class="small text-muted mt-2 mb-0">Ao demonstrar interesse, o ofertante será avisado e poderá liberar o contato para combinar a retirada.</p>
            <?php else: ?>
                <span class="badge text-bg-secondary">Este material não está mais recebendo interesses.</span>
            <?php endif; ?>
        </div></div>
    </div>
<div class="col-lg-4">
        <div class="info-card"><div class="card-body p-4">
            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-info-circle"></i></span>Informações</h5>
            <ul class="list-unstyled mb-0 oferta-info">
                <li><i class="bi bi-box-seam"></i><span><strong>Quantidade:</strong> <?php echo htmlspecialchars($oferta['quantidade'] !== null && $oferta['quantidade'] !== '' ? $oferta['quantidade'] : '—'); ?></span></li>
                <li><i class="bi bi-geo-alt"></i><span><strong>Retirada:</strong> <?php echo htmlspecialchars($oferta['localizacao'] !== null && $oferta['localizacao'] !== '' ? $oferta['localizacao'] : '—'); ?></span></li>
                <li><i class="bi bi-tag"></i><span><strong>Categoria:</strong> <?php echo htmlspecialchars($categoria_rotulo); ?></span></li>
                <li><i class="bi bi-stars"></i><span><strong>Estado:</strong> <?php echo htmlspecialchars(rotulo_estado_conservacao($oferta['estado_conservacao'])); ?></span></li>
                <li><i class="bi bi-calendar3"></i><span><strong>Publicado:</strong> <?php echo date('d/m/Y', strtotime($oferta['data_publicacao'])); ?></span></li>
                <li><i class="bi bi-people"></i><span><strong>Interessados:</strong> <?php echo (int) $oferta['total_interesses']; ?></span></li>
            </ul>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>