<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$titulo_pagina = 'Compartilhamentos';
$status_validos = ['todos' => 'Todos', 'disponivel' => 'Disponível', 'reservado' => 'Reservado', 'concluido' => 'Concluído', 'cancelado' => 'Indisponível'];
$status = $_GET['status'] ?? 'disponivel';
$status = is_string($status) && isset($status_validos[$status]) ? $status : 'disponivel';
$erros = [];
$mensagens = [
    'oferta' => 'Oferta publicada com sucesso.',
    'interesse' => 'Seu interesse foi registrado. O ofertante poderá aceitá-lo no painel.',
    'interesse_cancelado' => 'Seu interesse foi cancelado.',
];
$codigo_sucesso = $_GET['sucesso'] ?? '';
$sucesso = is_string($codigo_sucesso) ? ($mensagens[$codigo_sucesso] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $id = (int) ($_POST['id_compartilhamento'] ?? 0);
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
                header('Location: compartilhamentos.php?status=todos&sucesso=interesse#oferta-' . $id);
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
                header('Location: compartilhamentos.php?status=todos&sucesso=interesse_cancelado#oferta-' . $interesse['id_compartilhamento']);
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

$sql = 'SELECT c.*, r.nome AS residuo_nome, u.nome AS ofertante_nome,
               mi.id_interesse AS meu_interesse_id, mi.status AS meu_interesse_status,
               (SELECT COUNT(*) FROM interesse_compartilhamento i
                WHERE i.id_compartilhamento = c.id_compartilhamento
                  AND i.status IN (\'pendente\', \'aceito\')) AS total_interesses
        FROM compartilhamento_residuo c
        INNER JOIN residuo r ON r.id_residuo = c.id_residuo
        INNER JOIN usuario u ON u.id_usuario = c.id_usuario_ofertante
        LEFT JOIN interesse_compartilhamento mi
          ON mi.id_compartilhamento = c.id_compartilhamento AND mi.id_usuario = :usuario';
if ($status !== 'todos') $sql .= ' WHERE c.status = :status';
$sql .= ' ORDER BY c.data_publicacao DESC, c.id_compartilhamento DESC';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
if ($status !== 'todos') $stmt->bindValue(':status', $status);
$stmt->execute();
$ofertas = $stmt->fetchAll();

function classe_status_oferta($status) {
    return ['disponivel' => 'success', 'reservado' => 'warning', 'concluido' => 'secondary', 'cancelado' => 'danger'][$status] ?? 'secondary';
}
function rotulo_status_oferta($status) {
    return ['disponivel' => 'Disponível', 'reservado' => 'Reservado', 'concluido' => 'Concluído', 'cancelado' => 'Indisponível'][$status] ?? $status;
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens usados em bom estado que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Compartilhamentos</h1>
    <p class="mt-2">Oferte itens que podem ser reaproveitados e encontre o que você precisa.</p>
</section>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4 reveal">
    <div class="d-flex flex-wrap gap-2">
        <a href="cadastro_compartilhamento.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Oferecer item</a>
        <a href="meus_compartilhamentos.php" class="btn btn-outline-success"><i class="bi bi-person-lines-fill me-1"></i>Minhas ofertas</a>
    </div>
    <form method="get" class="d-flex gap-2 align-items-end">
        <div>
            <label for="status" class="form-label small mb-1">Filtrar por status</label>
            <select name="status" id="status" class="form-select">
                <?php foreach ($status_validos as $valor => $rotulo): ?>
                    <option value="<?php echo $valor; ?>" <?php echo $status === $valor ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-outline-success" type="submit">Filtrar</button>
    </form>
</div>

<?php if ($sucesso): ?><div class="alert alert-success mt-4"><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?>
    <div class="alert alert-danger mt-4"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<p class="text-muted small mt-4"><?php echo count($ofertas); ?> oferta(s) encontrada(s).</p>
<?php if (!$ofertas): ?>
    <div class="alert alert-info">Nenhuma oferta neste status. Que tal publicar a primeira?</div>
<?php else: ?>
    <div class="row g-4 mt-1 reveal">
        <?php foreach ($ofertas as $oferta): ?>
            <?php $propria = (int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario']; ?>
            <div class="col-md-6 col-lg-4" id="oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>">
                <article class="card card-residuo">
                    <div class="photo"><img src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Item disponível para reaproveitamento"></div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h2 class="h5 card-title mb-0"><?php echo htmlspecialchars($oferta['residuo_nome']); ?></h2>
                            <span class="badge text-bg-<?php echo classe_status_oferta($oferta['status']); ?>"><?php echo rotulo_status_oferta($oferta['status']); ?></span>
                        </div>
                        <p class="small text-muted mb-2">Por <?php echo htmlspecialchars($oferta['ofertante_nome']); ?> em <?php echo date('d/m/Y', strtotime($oferta['data_publicacao'])); ?></p>
                        <p class="card-text small flex-grow-1"><?php echo nl2br(htmlspecialchars($oferta['descricao'])); ?></p>
                        <p class="small text-muted"><?php echo (int) $oferta['total_interesses']; ?> interesse(s) ativo(s).</p>
                        <?php if ($propria): ?>
                            <a href="meus_compartilhamentos.php#oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-sm btn-outline-success align-self-start">Gerenciar sua oferta</a>
                        <?php elseif (in_array($oferta['meu_interesse_status'], ['pendente', 'aceito'], true)): ?>
                            <p class="small mb-2"><span class="badge-soft">Seu interesse: <?php echo $oferta['meu_interesse_status'] === 'aceito' ? 'aceito' : 'pendente'; ?></span></p>
                            <form method="post" action="compartilhamentos.php?status=<?php echo $status; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="cancelar_interesse">
                                <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                <input type="hidden" name="id_interesse" value="<?php echo (int) $oferta['meu_interesse_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar meu interesse</button>
                            </form>
                        <?php elseif ($oferta['status'] === 'disponivel'): ?>
                            <form method="post" action="compartilhamentos.php?status=<?php echo $status; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="demonstrar_interesse">
                                <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-hand-thumbs-up me-1"></i>Tenho interesse</button>
                            </form>
                        <?php else: ?>
                            <span class="small text-muted">Esta oferta não está recebendo interesses.</span>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
