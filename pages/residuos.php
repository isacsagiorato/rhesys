<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

// Foto ilustrativa conforme a classificação (ABNT NBR 10004)
function imagem_residuo($nome_classificacao) {
    $n = mb_strtolower((string) $nome_classificacao, 'UTF-8');
    if (mb_strpos($n, 'perigoso') !== false) {
        return BASE_URL . 'img/fotos/eletronicos.jpg';   // Classe I - Perigosos
    }
    if (mb_strpos($n, 'não inerte') !== false || mb_strpos($n, 'nao inerte') !== false) {
        return BASE_URL . 'img/fotos/reciclaveis.jpg';   // Classe II A - Não Inertes
    }
    if (mb_strpos($n, 'inerte') !== false) {
        return BASE_URL . 'img/fotos/coleta.jpg';        // Classe II B - Inertes
    }
    return BASE_URL . 'img/fotos/coleta.jpg';
}

$titulo_pagina = 'Resíduos';
require __DIR__ . '/../includes/header.php';

// Parâmetros
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$id_residuo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- Detalhe de um resíduo específico ---
if ($id_residuo > 0) {
    $sql = "SELECT r.*, c.nome AS classificacao_nome, c.descricao AS classificacao_descricao
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            WHERE r.id_residuo = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
    $stmt->execute();
    $residuo = $stmt->fetch();

    if (!$residuo) {
        echo '<div class="alert alert-warning">Resíduo não encontrado.</div>';
        echo '<a href="residuos.php" class="btn btn-success">Voltar</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    ?>

    <a href="residuos.php" class="btn btn-outline-success mb-3">&larr; Voltar à lista</a>

    <div class="detail-photo mb-4">
        <img src="<?php echo imagem_residuo($residuo['classificacao_nome']); ?>" alt="Foto ilustrativa: <?php echo htmlspecialchars($residuo['classificacao_nome']); ?>">
    </div>

    <div class="info-card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                <h1 class="mb-0 me-2 fs-2"><?php echo htmlspecialchars($residuo['nome']); ?></h1>
                <span class="badge-soft"><?php echo htmlspecialchars($residuo['classificacao_nome']); ?></span>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <span class="badge-lr">Logística Reversa</span>
                <?php endif; ?>
            </div>

            <p class="text-muted"><?php echo htmlspecialchars($residuo['classificacao_descricao']); ?></p>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-card-text"></i></span> Descrição</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['descricao'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-trash3"></i></span> Forma de Descarte</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['forma_descarte'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-recycle"></i></span> Reciclagem</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['reciclagem'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="card-body p-4">
                                <h5 class="card-title"><span class="icon-chip-sm" style="background: #fef3c7; color: #92400e;"><i class="bi bi-arrow-repeat"></i></span> Logística Reversa</h5>
                                <p class="card-text">
                                    Este resíduo está sujeito à logística reversa conforme a Lei nº 12.305/2010.
                                    Deve ser entregue em pontos de coleta específicos para reaproveitamento
                                    ou destinação ambientalmente adequada.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($residuo['palavras_chave'])): ?>
                <div class="mt-4">
                    <strong>Palavras-chave:</strong>
                    <?php foreach (explode(',', $residuo['palavras_chave']) as $palavra): ?>
                        <span class="badge-soft me-1 mt-1"><?php echo htmlspecialchars(trim($palavra)); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pontos de coleta que aceitam este resíduo -->
            <?php
            $sql_pontos = "SELECT p.* FROM ponto_coleta p
                           INNER JOIN residuo_ponto_coleta rpc ON p.id_ponto = rpc.id_ponto
                           WHERE rpc.id_residuo = :id";
            $stmt_pontos = $pdo->prepare($sql_pontos);
            $stmt_pontos->bindValue(':id', $id_residuo, PDO::PARAM_INT);
            $stmt_pontos->execute();
            $pontos = $stmt_pontos->fetchAll();
            ?>
            <?php if (count($pontos) > 0): ?>
                <div class="mt-4">
                    <h5>Pontos de Coleta que aceitam este resíduo</h5>
                    <ul class="list-group">
                        <?php foreach ($pontos as $ponto): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($ponto['nome']); ?></strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($ponto['endereco']); ?></span>
                                <?php if ($ponto['telefone']): ?>
                                    <br><span class="text-muted">Tel: <?php echo htmlspecialchars($ponto['telefone']); ?></span>
                                <?php endif; ?>
                                <?php if ($ponto['horario_funcionamento']): ?>
                                    <br><span class="text-muted">Horário: <?php echo htmlspecialchars($ponto['horario_funcionamento']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// --- Listagem com busca ---
if ($busca !== '') {
    $sql = "SELECT r.*, c.nome AS classificacao_nome
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            WHERE r.nome LIKE :busca1
               OR r.palavras_chave LIKE :busca2
               OR r.descricao LIKE :busca3
            ORDER BY r.nome";
    $termo = "%$busca%";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busca1', $termo, PDO::PARAM_STR);
    $stmt->bindValue(':busca2', $termo, PDO::PARAM_STR);
    $stmt->bindValue(':busca3', $termo, PDO::PARAM_STR);
    $stmt->execute();
} else {
    $sql = "SELECT r.*, c.nome AS classificacao_nome
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            ORDER BY r.nome";
    $stmt = $pdo->query($sql);
}
$residuos = $stmt->fetchAll();
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Lixeiras coloridas de coleta seletiva">
    <span class="crumb">Consulte antes de descartar</span>
    <h1 class="mt-2">Resíduos</h1>
    <p class="mt-2">
        Pesquise pelo nome, palavra-chave ou descrição e descubra a classificação
        e a forma correta de descarte.
    </p>
</section>

<div class="search-wrap mt-4 reveal">
    <form method="GET">
        <div class="search-pill">
            <i class="bi bi-search"></i>
            <input type="text" name="busca"
                   placeholder="Ex.: pilha, garrafa PET, papelão..."
                   value="<?php echo htmlspecialchars($busca); ?>">
            <button class="btn btn-success" type="submit">Buscar</button>
            <?php if ($busca !== ''): ?>
                <a href="residuos.php" class="btn btn-outline-secondary border-0 me-1">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-funnel"></i>
        <?php echo count($residuos); ?> resultado(s) encontrado(s)<?php if ($busca !== ''): ?> para
        “<strong><?php echo htmlspecialchars($busca); ?></strong>”<?php endif; ?>.
    </p>
</div>

<?php if (count($residuos) === 0): ?>
    <div class="alert alert-info mt-4">
        <i class="bi bi-search me-1"></i> Nenhum resíduo encontrado para "<strong><?php echo htmlspecialchars($busca); ?></strong>".
    </div>
<?php else: ?>
    <div class="row g-4 mt-2 reveal">
        <?php foreach ($residuos as $res): ?>
            <div class="col-md-6 col-lg-4">
                <a href="residuos.php?id=<?php echo $res['id_residuo']; ?>" class="text-decoration-none text-dark">
                    <div class="card card-residuo">
                        <div class="photo">
                            <img src="<?php echo imagem_residuo($res['classificacao_nome']); ?>" alt="Foto ilustrativa: <?php echo htmlspecialchars($res['nome']); ?>">
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($res['nome']); ?></h5>
                                <?php if ($res['possui_logistica_reversa']): ?>
                                    <span class="badge-lr">LR</span>
                                <?php endif; ?>
                            </div>
                            <span class="badge-soft d-inline-block mb-2"><?php echo htmlspecialchars($res['classificacao_nome']); ?></span>
                            <p class="card-text text-muted small clamp-3">
                                <?php echo htmlspecialchars($res['descricao']); ?>
                            </p>
                            <span class="link-arrow small">Ver detalhes <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>