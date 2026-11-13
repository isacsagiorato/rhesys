<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
exige_login();

$titulo_pagina = 'Compartilhamentos';
$status_validos = ['todos' => 'Todos', 'disponivel' => 'Disponível', 'reservado' => 'Reservado', 'concluido' => 'Retirado', 'cancelado' => 'Indisponível'];
$erros = [];
$mensagens = [
    'oferta' => 'Material publicado com sucesso.',
    'interesse' => 'Seu interesse foi registrado. O ofertante poderá aceitá-lo no painel.',
    'interesse_cancelado' => 'Seu interesse foi cancelado.',
];
$sucesso = is_string($_GET['sucesso'] ?? '') ? ($mensagens[$_GET['sucesso']] ?? '') : '';

$busca = trim((string) ($_GET['busca'] ?? ''));
$categoria = (string) ($_GET['categoria'] ?? 'todas');
$estado = (string) ($_GET['estado'] ?? 'todos');
$localizacao = trim((string) ($_GET['localizacao'] ?? ''));
$status = (string) ($_GET['status'] ?? 'todos');
if (!isset($status_validos[$status])) $status = 'todos';
if ($categoria !== 'todas' && !isset(CATEGORIAS_MATERIAIS[$categoria])) $categoria = 'todas';
if ($estado !== 'todos' && !isset(ESTADOS_CONSERVACAO[$estado])) $estado = 'todos';

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
$condicoes = [];
$params = [':usuario' => $_SESSION['id_usuario']];
if ($status !== 'todos') { $condicoes[] = 'c.status = :status'; $params[':status'] = $status; }
if ($busca !== '') { $condicoes[] = '(c.nome LIKE :busca OR r.nome LIKE :busca)'; $params[':busca'] = '%' . $busca . '%'; }
if ($categoria !== 'todas') { $condicoes[] = 'c.categoria = :categoria'; $params[':categoria'] = $categoria; }
if ($estado !== 'todos') { $condicoes[] = 'c.estado_conservacao = :estado'; $params[':estado'] = $estado; }
if ($localizacao !== '') { $condicoes[] = 'c.localizacao LIKE :localizacao'; $params[':localizacao'] = '%' . $localizacao . '%'; }
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$sql = "SELECT c.*,
               COALESCE(NULLIF(c.nome, ''), r.nome) AS material_nome,
               COALESCE(NULLIF(c.categoria, ''), cl.nome) AS material_categoria,
               u.nome AS ofertante_nome,
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
        $where
        ORDER BY c.data_publicacao DESC, c.id_compartilhamento DESC";
$stmt = $pdo->prepare($sql);
foreach ($params as $chave => $valor) {
    $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$ofertas = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Materiais que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Compartilhamentos</h1>
    <p class="mt-2">Ofereça o que seria descartado e encontre materiais para reaproveitar.</p>
</section>

<div class="d-flex flex-wrap gap-2 mt-4 reveal">
    <a href="cadastro_compartilhamento.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Oferecer material</a>
    <a href="meus_compartilhamentos.php" class="btn btn-outline-success"><i class="bi bi-person-lines-fill me-1"></i>Meus compartilhamentos</a>
</div>

<form method="get" action="compartilhamentos.php" class="filtro-bar mt-4 reveal">
    <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-3">
            <label for="busca" class="form-label small mb-1">Pesquisar por nome</label>
            <input type="search" class="form-control" id="busca" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Ex.: cadeira, papelão, livro...">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="categoria" class="form-label small mb-1">Categoria</label>
            <select name="categoria" id="categoria" class="form-select">
                <option value="todas">Todas</option>
                <?php foreach (CATEGORIAS_MATERIAIS as $v => $rot): ?>
                    <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $categoria === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($rot); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="estado" class="form-label small mb-1">Estado</label>
            <select name="estado" id="estado" class="form-select">
                <option value="todos">Todos</option>
                <?php foreach (ESTADOS_CONSERVACAO as $v => $rot): ?>
                    <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $estado === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($rot); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="localizacao" class="form-label small mb-1">Localização</label>
            <input type="text" class="form-control" id="localizacao" name="localizacao" value="<?php echo htmlspecialchars($localizacao); ?>" placeholder="Bairro ou cidade">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label for="status" class="form-label small mb-1">Status</label>
            <select name="status" id="status" class="form-select">
                <?php foreach ($status_validos as $v => $rot): ?>
                    <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $status === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($rot); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-xl-1 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100"><i class="bi bi-funnel me-1"></i>Filtrar</button>
        </div>
    </div>
</form>

<?php if ($sucesso): ?><div class="alert alert-success mt-4"><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger mt-4"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<p class="text-muted small mt-4"><?php echo count($ofertas); ?> material(is) encontrado(s).</p>
<?php if (!$ofertas): ?>
    <div class="alert alert-info">
        <i class="bi bi-inbox me-1"></i>Nenhum material encontrado com os filtros atuais.
        <a href="cadastro_compartilhamento.php">Seja a primeira pessoa a oferecer!</a>
    </div>
<?php else: ?>
    <div class="row g-4 reveal">
<?php foreach ($ofertas as $oferta):
            $propria = (int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario'];
            $categoria_rotulo = ($oferta['categoria'] !== null && $oferta['categoria'] !== '')
                ? rotulo_categoria_material($oferta['categoria'])
                : ($oferta['material_categoria'] !== null && $oferta['material_categoria'] !== '' ? $oferta['material_categoria'] : 'Resíduo');
        ?>
        <div class="col-md-6 col-lg-4" id="oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>">
            <article class="card card-residuo oferta-card">
                <a class="photo d-block" href="compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>">
                    <img src="<?php echo imagem_material($oferta['imagem']); ?>" alt="<?php echo htmlspecialchars($oferta['material_nome']); ?>">
                </a>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <a href="compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>" class="text-decoration-none">
                            <h2 class="h5 card-title mb-0"><?php echo htmlspecialchars($oferta['material_nome']); ?></h2>
                        </a>
                        <span class="badge text-bg-<?php echo classe_status_compartilhamento($oferta['status']); ?>"><?php echo rotulo_status_compartilhamento($oferta['status']); ?></span>
                    </div>
                    <div class="oferta-meta mb-2">
                        <span class="badge-soft"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($categoria_rotulo); ?></span>
                        <span class="<?php echo classe_estado_conservacao($oferta['estado_conservacao']); ?>"><i class="bi bi-stars"></i> <?php echo htmlspecialchars(rotulo_estado_conservacao($oferta['estado_conservacao'])); ?></span>
                    </div>
                    <p class="card-text small text-muted flex-grow-1 clamp-3 mb-2"><?php echo nl2br(htmlspecialchars($oferta['descricao'])); ?></p>
                    <ul class="oferta-info list-unstyled small text-muted mb-2">
                        <?php if ($oferta['quantidade'] !== null && $oferta['quantidade'] !== ''): ?><li><i class="bi bi-box-seam"></i><?php echo htmlspecialchars($oferta['quantidade']); ?></li><?php endif; ?>
                        <?php if ($oferta['localizacao'] !== null && $oferta['localizacao'] !== ''): ?><li><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($oferta['localizacao']); ?></li><?php endif; ?>
                    </ul>
                    <p class="small text-muted mb-3">Por <?php echo htmlspecialchars($oferta['ofertante_nome']); ?> · <?php echo (int) $oferta['total_interesses']; ?> interesse(s)</p>
                    <div class="d-flex flex-wrap gap-2 mt-auto">
                        <a href="compartilhamento.php?id=<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-sm btn-outline-success">Ver detalhes</a>
                        <?php if ($propria): ?>
                            <a href="meus_compartilhamentos.php#oferta-<?php echo (int) $oferta['id_compartilhamento']; ?>" class="btn btn-sm btn-outline-secondary">Gerenciar</a>
                        <?php elseif (in_array($oferta['meu_interesse_status'], ['pendente', 'aceito'], true)): ?>
                            <span class="badge-soft">Seu interesse: <?php echo $oferta['meu_interesse_status'] === 'aceito' ? 'aceito' : 'pendente'; ?></span>
                            <form method="post" action="compartilhamentos.php?status=<?php echo $status; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="cancelar_interesse">
                                <input type="hidden" name="id_interesse" value="<?php echo (int) $oferta['meu_interesse_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar interesse</button>
                            </form>
                        <?php elseif ($oferta['status'] === 'disponivel'): ?>
                            <form method="post" action="compartilhamentos.php?status=<?php echo $status; ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="acao" value="demonstrar_interesse">
                                <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $oferta['id_compartilhamento']; ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-hand-thumbs-up me-1"></i>Tenho interesse</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>