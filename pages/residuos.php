<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

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

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-3">
                <h1 class="card-title mb-0 me-3"><?php echo htmlspecialchars($residuo['nome']); ?></h1>
                <span class="badge bg-success fs-6"><?php echo htmlspecialchars($residuo['classificacao_nome']); ?></span>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <span class="badge bg-warning text-dark fs-6 ms-1">Logística Reversa</span>
                <?php endif; ?>
            </div>

            <p class="text-muted"><?php echo htmlspecialchars($residuo['classificacao_descricao']); ?></p>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success">Descrição</h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($residuo['descricao'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success">Forma de Descarte</h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($residuo['forma_descarte'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success">Reciclagem</h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($residuo['reciclagem'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <div class="col-md-6">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h5 class="card-title text-warning">Logística Reversa</h5>
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
                        <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($palavra)); ?></span>
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
            WHERE r.nome LIKE :busca
               OR r.palavras_chave LIKE :busca
               OR r.descricao LIKE :busca
            ORDER BY r.nome";
    $termo = "%$busca%";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busca', $termo, PDO::PARAM_STR);
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Resíduos</h1>
    <span class="badge bg-success fs-6"><?php echo count($residuos); ?> encontrado(s)</span>
</div>

<!-- Formulário de busca -->
<form method="GET" class="mb-4">
    <div class="input-group input-group-lg">
        <input type="text" name="busca" class="form-control"
               placeholder="Pesquisar por nome, palavra-chave ou descrição..."
               value="<?php echo htmlspecialchars($busca); ?>">
        <button class="btn btn-success" type="submit">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a href="residuos.php" class="btn btn-outline-secondary">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<?php if (count($residuos) === 0): ?>
    <div class="alert alert-info">
        Nenhum resíduo encontrado para "<strong><?php echo htmlspecialchars($busca); ?></strong>".
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($residuos as $res): ?>
            <div class="col-md-6 col-lg-4">
                <a href="residuos.php?id=<?php echo $res['id_residuo']; ?>" class="text-decoration-none text-dark">
                    <div class="card card-residuo shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="card-title text-success"><?php echo htmlspecialchars($res['nome']); ?></h5>
                                <?php if ($res['possui_logistica_reversa']): ?>
                                    <span class="badge bg-warning text-dark">LR</span>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-light text-dark mb-2"><?php echo htmlspecialchars($res['classificacao_nome']); ?></span>
                            <p class="card-text text-muted small">
                                <?php
                                $desc = $res['descricao'];
                                echo strlen($desc) > 100 ? htmlspecialchars(substr($desc, 0, 100)) . '...' : htmlspecialchars($desc);
                                ?>
                            </p>
                            <span class="text-success small fw-bold">Ver detalhes &rarr;</span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>