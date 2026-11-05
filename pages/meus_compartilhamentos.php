<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$titulo_pagina = 'Minhas ofertas';
$erros = [];
$mensagens = ['aceito' => 'Interesse aceito. A oferta agora está reservada.', 'concluido' => 'Compartilhamento concluído com sucesso.', 'cancelado' => 'Oferta marcada como indisponível e interesses cancelados.'];
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
            $stmt = $pdo->prepare('SELECT status FROM compartilhamento_residuo WHERE id_compartilhamento = :id AND id_usuario_ofertante = :usuario FOR UPDATE');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();
            $oferta = $stmt->fetch();
            if (!$oferta) throw new RuntimeException('Oferta não encontrada ou sem permissão de gerenciamento.');

            if ($acao === 'aceitar_interesse') {
                if ($oferta['status'] !== 'disponivel') throw new RuntimeException('Somente ofertas disponíveis podem aceitar um interessado.');
                $stmt = $pdo->prepare("SELECT id_interesse FROM interesse_compartilhamento WHERE id_interesse = :interesse AND id_compartilhamento = :oferta AND status = 'pendente' FOR UPDATE");
                $stmt->bindValue(':interesse', $id_interesse, PDO::PARAM_INT);
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetch()) throw new RuntimeException('O interesse selecionado não está pendente.');
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'aceito' WHERE id_interesse = :id");
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'recusado' WHERE id_compartilhamento = :oferta AND id_interesse <> :aceito AND status = 'pendente'");
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->bindValue(':aceito', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'reservado' WHERE id_compartilhamento = :id");
                $codigo = 'aceito';
            } elseif ($acao === 'concluir_oferta') {
                if ($oferta['status'] !== 'reservado') throw new RuntimeException('Aceite um interesse antes de concluir a oferta.');
                $stmt = $pdo->prepare("SELECT id_interesse FROM interesse_compartilhamento WHERE id_compartilhamento = :oferta AND status = 'aceito'");
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetch()) throw new RuntimeException('Não há um interessado aceito para esta oferta.');
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'concluido' WHERE id_compartilhamento = :id");
                $codigo = 'concluido';
            } elseif ($acao === 'cancelar_oferta') {
                if (in_array($oferta['status'], ['concluido', 'cancelado'], true)) throw new RuntimeException('Esta oferta já está encerrada.');
                $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'cancelado' WHERE id_compartilhamento = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'cancelado' WHERE id_compartilhamento = :id AND status IN ('pendente', 'aceito')");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $pdo->commit();
                header('Location: meus_compartilhamentos.php?sucesso=cancelado#oferta-' . $id);
                exit;
            } else {
                throw new RuntimeException('Ação inválida.');
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $pdo->commit();
            header('Location: meus_compartilhamentos.php?sucesso=' . $codigo . '#oferta-' . $id);
            exit;
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

$stmt = $pdo->prepare('SELECT c.*, r.nome AS residuo_nome FROM compartilhamento_residuo c INNER JOIN residuo r ON r.id_residuo = c.id_residuo WHERE c.id_usuario_ofertante = :usuario ORDER BY c.data_publicacao DESC');
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
$ofertas = $stmt->fetchAll();
$interesses = [];
$stmt = $pdo->prepare('SELECT i.*, u.nome, u.email FROM interesse_compartilhamento i INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento INNER JOIN usuario u ON u.id_usuario = i.id_usuario WHERE c.id_usuario_ofertante = :usuario ORDER BY i.data_interesse');
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll() as $interesse) $interesses[$interesse['id_compartilhamento']][] = $interesse;

function classe_status_oferta($status) {
    return ['disponivel' => 'success', 'reservado' => 'warning', 'concluido' => 'secondary', 'cancelado' => 'danger'][$status] ?? 'secondary';
}
function rotulo_status_oferta($status) {
    return ['disponivel' => 'Disponível', 'reservado' => 'Reservado', 'concluido' => 'Concluído', 'cancelado' => 'Indisponível'][$status] ?? $status;
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens para compartilhamento">
    <span class="crumb">Painel do ofertante</span>
    <h1 class="mt-2">Minhas ofertas</h1>
    <p class="mt-2">Acompanhe os interessados e atualize o andamento dos seus compartilhamentos.</p>
</section>

<div class="d-flex flex-wrap gap-2 mt-4">
    <a href="cadastro_compartilhamento.php" class="btn btn-success">Oferecer outro item</a>
    <a href="compartilhamentos.php" class="btn btn-outline-success">Voltar às ofertas</a>
</div>
<?php if ($sucesso): ?><div class="alert alert-success mt-4"><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger mt-4"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if (!$ofertas): ?>
    <div class="alert alert-info mt-4">Você ainda não publicou ofertas.</div>
<?php endif; ?>
<?php foreach ($ofertas as $oferta): ?>
    <?php $lista_interesses = $interesses[$oferta['id_compartilhamento']] ?? []; ?>
    <section class="info-card mt-4 reveal" id="oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h4 mb-1"><?php echo htmlspecialchars($oferta['residuo_nome']); ?></h2>
                    <p class="small text-muted mb-2">Publicada em <?php echo date('d/m/Y H:i', strtotime($oferta['data_publicacao'])); ?></p>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($oferta['descricao'])); ?></p>
                </div>
                <span class="badge text-bg-<?php echo classe_status_oferta($oferta['status']); ?>"><?php echo rotulo_status_oferta($oferta['status']); ?></span>
            </div>
            <?php if (!in_array($oferta['status'], ['concluido', 'cancelado'], true)): ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($oferta['status'] === 'reservado'): ?>
                        <form method="post" action="meus_compartilhamentos.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="acao" value="concluir_oferta">
                            <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">Marcar como concluído</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="meus_compartilhamentos.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="acao" value="cancelar_oferta">
                        <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Tornar indisponível</button>
                    </form>
                </div>
            <?php endif; ?>

            <hr>
            <h3 class="h5">Interessados (<?php echo count($lista_interesses); ?>)</h3>
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
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
