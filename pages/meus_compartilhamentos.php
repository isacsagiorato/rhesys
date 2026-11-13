<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
exige_login();

$titulo_pagina = 'Meus compartilhamentos';
$erros = [];
$mensagens = [
    'aceito' => 'Interesse aceito. O material agora está reservado.',
    'concluido' => 'Material marcado como retirado.',
    'cancelado' => 'Material tornado indisponível e interesses cancelados.',
    'excluido' => 'Material excluído com sucesso.',
    'editado' => 'Material atualizado com sucesso.',
];
$codigo_sucesso = $_GET['sucesso'] ?? '';
$sucesso = is_string($codigo_sucesso) ? ($mensagens[$codigo_sucesso] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $id = (int) ($_POST['id_compartilhamento'] ?? 0);
        $id_interesse = (int) ($_POST['id_interesse'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT status, imagem FROM compartilhamento_residuo WHERE id_compartilhamento = :id AND id_usuario_ofertante = :usuario FOR UPDATE');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();
            $oferta = $stmt->fetch();
            if (!$oferta) {
                throw new RuntimeException('Oferta não encontrada ou sem permissão de gerenciamento.');
            }

            if ($acao === 'excluir') {
                $stmt = $pdo->prepare('DELETE FROM compartilhamento_residuo WHERE id_compartilhamento = :id');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                compartilhamento_remover_imagem($oferta['imagem']);
                header('Location: meus_compartilhamentos.php?sucesso=excluido');
                exit;
            }
            if ($acao === 'marcar_retirado') {
                if (in_array($oferta['status'], ['concluido', 'cancelado'], true)) {
                    throw new RuntimeException('Esta oferta já está encerrada.');
                }
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'concluido' WHERE id_compartilhamento = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'cancelado' WHERE id_compartilhamento = :id AND status = 'pendente'");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                header('Location: meus_compartilhamentos.php?sucesso=concluido#oferta-' . $id);
                exit;
            }
            if ($acao === 'aceitar_interesse') {
                if ($oferta['status'] !== 'disponivel') {
                    throw new RuntimeException('Somente materiais disponíveis podem aceitar um interessado.');
                }
                $stmt = $pdo->prepare("SELECT id_interesse FROM interesse_compartilhamento WHERE id_interesse = :interesse AND id_compartilhamento = :oferta AND status = 'pendente' FOR UPDATE");
                $stmt->bindValue(':interesse', $id_interesse, PDO::PARAM_INT);
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetch()) {
                    throw new RuntimeException('O interesse selecionado não está pendente.');
                }
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'aceito' WHERE id_interesse = :id");
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'recusado' WHERE id_compartilhamento = :oferta AND id_interesse <> :aceito AND status = 'pendente'");
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->bindValue(':aceito', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'reservado' WHERE id_compartilhamento = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                header('Location: meus_compartilhamentos.php?sucesso=aceito#oferta-' . $id);
                exit;
            }
if ($acao === 'concluir_oferta') {
                if ($oferta['status'] !== 'reservado') {
                    throw new RuntimeException('Aceite um interesse antes de marcar como retirado.');
                }
                $stmt = $pdo->prepare("SELECT id_interesse FROM interesse_compartilhamento WHERE id_compartilhamento = :oferta AND status = 'aceito'");
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetch()) {
                    throw new RuntimeException('Não há um interessado aceito para esta oferta.');
                }
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'concluido' WHERE id_compartilhamento = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                header('Location: meus_compartilhamentos.php?sucesso=concluido#oferta-' . $id);
                exit;
            }
            if ($acao === 'cancelar_oferta') {
                if (in_array($oferta['status'], ['concluido', 'cancelado'], true)) {
                    throw new RuntimeException('Esta oferta já está encerrada.');
                }
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'cancelado' WHERE id_compartilhamento = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'cancelado' WHERE id_compartilhamento = :id AND status IN ('pendente', 'aceito')");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                header('Location: meus_compartilhamentos.php?sucesso=cancelado#oferta-' . $id);
                exit;
            }
            throw new RuntimeException('Ação inválida.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro no painel de ofertas: ' . $e->getMessage());
            $erros[] = 'Não foi possível concluir a operação. Tente novamente.';
        }
    }
}

$stmt = $pdo->prepare("SELECT c.*, COALESCE(NULLIF(c.nome, ''), r.nome) AS material_nome FROM compartilhamento_residuo c LEFT JOIN residuo r ON r.id_residuo = c.id_residuo WHERE c.id_usuario_ofertante = :usuario ORDER BY c.data_publicacao DESC");
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
$ofertas = $stmt->fetchAll();

$interesses = [];
$stmt = $pdo->prepare('SELECT i.*, u.nome, u.email FROM interesse_compartilhamento i INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento INNER JOIN usuario u ON u.id_usuario = i.id_usuario WHERE c.id_usuario_ofertante = :usuario ORDER BY i.data_interesse');
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll() as $interesse) {
    $interesses[$interesse['id_compartilhamento']][] = $interesse;
}

require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Materiais para compartilhamento">
    <span class="crumb">Painel do ofertante</span>
    <h1 class="mt-2">Meus compartilhamentos</h1>
    <p class="mt-2">Acompanhe os interessados e atualize o andamento dos materiais que você ofereceu.</p>
</section>

<div class="d-flex flex-wrap gap-2 mt-4">
    <a href="cadastro_compartilhamento.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Oferecer outro material</a>
    <a href="compartilhamentos.php" class="btn btn-outline-success"><i class="bi bi-arrow-left me-1"></i>Voltar aos materiais</a>
</div>

<?php if ($sucesso): ?><div class="alert alert-success mt-4"><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger mt-4"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if (!$ofertas): ?>
    <div class="alert alert-info mt-4">Você ainda não ofereceu nenhum material. <a href="cadastro_compartilhamento.php">Comece agora!</a></div>
<?php endif; ?>

<?php foreach ($ofertas as $oferta):
    $lista_interesses = $interesses[$oferta['id_compartilhamento']] ?? [];
    $categoria_rotulo = ($oferta['categoria'] !== null && $oferta['categoria'] !== '')
        ? rotulo_categoria_material($oferta['categoria'])
        : 'Resíduo';
?>
<section class="info-card mt-4 reveal" id="oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>">
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-4 col-lg-3">
                <a class="meus-thumb" href="compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>">
                    <img src="<?php echo imagem_material($oferta['imagem']); ?>" alt="<?php echo htmlspecialchars($oferta['material_nome']); ?>">
                </a>
            </div>
            <div class="col-md-8 col-lg-9">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h2 class="h4 mb-1"><?php echo htmlspecialchars($oferta['material_nome']); ?></h2>
                        <p class="small text-muted mb-0">Publicado em <?php echo date('d/m/Y H:i', strtotime($oferta['data_publicacao'])); ?></p>
                    </div>
                    <span class="badge text-bg-<?php echo classe_status_compartilhamento($oferta['status']); ?>"><?php echo rotulo_status_compartilhamento($oferta['status']); ?></span>
                </div>
                <div class="oferta-meta mb-2">
                    <span class="badge-soft"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($categoria_rotulo); ?></span>
                    <span class="<?php echo classe_estado_conservacao($oferta['estado_conservacao']); ?>"><i class="bi bi-stars"></i> <?php echo htmlspecialchars(rotulo_estado_conservacao($oferta['estado_conservacao'])); ?></span>
                    <span class="badge-soft"><i class="bi bi-people"></i> <?php echo count($lista_interesses); ?> interessado(s)</span>
                </div>
                <p class="small mb-2"><?php echo nl2br(htmlspecialchars($oferta['descricao'])); ?></p>
                <ul class="oferta-info small text-muted mb-3">
                    <?php if ($oferta['quantidade'] !== null && $oferta['quantidade'] !== ''): ?><li><i class="bi bi-box-seam"></i>Quantidade: <?php echo htmlspecialchars($oferta['quantidade']); ?></li><?php endif; ?>
                    <?php if ($oferta['localizacao'] !== null && $oferta['localizacao'] !== ''): ?><li><i class="bi bi-geo-alt"></i>Retirada: <?php echo htmlspecialchars($oferta['localizacao']); ?></li><?php endif; ?>
                </ul>
<div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-sm btn-outline-success">Ver detalhes</a>
                    <?php if (!in_array($oferta['status'], ['concluido', 'cancelado'], true)): ?>
                        <a href="editar_compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-sm btn-outline-success">Editar</a>
                        <?php if ($oferta['status'] === 'reservado'): ?>
                            <form method="post" action="meus_compartilhamentos.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="concluir_oferta">
                                <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Marcar como retirado</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="meus_compartilhamentos.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="marcar_retirado">
                                <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Marcar como retirado</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="meus_compartilhamentos.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="acao" value="cancelar_oferta">
                            <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Tornar indisponível</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="meus_compartilhamentos.php" onsubmit="return confirm('Excluir este material? Esta ação não pode ser desfeita.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Excluir</button>
                    </form>
                </div>
                <hr>
                <h3 class="h6">Interessados (<?php echo count($lista_interesses); ?>)</h3>
                <?php if (!$lista_interesses): ?>
                    <p class="text-muted small mb-0">Nenhum interesse registrado até agora.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($lista_interesses as $interesse): ?>
                            <div class="list-group-item">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                    <div>
                                        <strong><?php echo htmlspecialchars($interesse['nome']); ?></strong>
                                        <span class="badge-soft ms-1"><?php echo htmlspecialchars($interesse['status']); ?></span>
                                        <p class="small mb-1 mt-2"><a href="mailto:<?php echo htmlspecialchars($interesse['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($interesse['email']); ?></a></p>
                                        <p class="small text-muted mb-0">Interesse em <?php echo date('d/m/Y H:i', strtotime($interesse['data_interesse'])); ?></p>
                                    </div>
                                    <?php if ($oferta['status'] === 'disponivel' && $interesse['status'] === 'pendente'): ?>
                                        <form method="post" action="meus_compartilhamentos.php">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="acao" value="aceitar_interesse">
                                            <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                            <input type="hidden" name="id_interesse" value="<?php echo (int) $interesse['id_interesse']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">Aceitar interesse</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>