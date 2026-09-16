<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$titulo_pagina = 'Pontos de Coleta';
require __DIR__ . '/../includes/header.php';

// Buscar todos os pontos de coleta com os resíduos aceitos
$sql = "SELECT p.*, GROUP_CONCAT(r.nome SEPARATOR ', ') AS residuos_aceitos
        FROM ponto_coleta p
        LEFT JOIN residuo_ponto_coleta rpc ON p.id_ponto = rpc.id_ponto
        LEFT JOIN residuo r ON rpc.id_residuo = r.id_residuo
        GROUP BY p.id_ponto
        ORDER BY p.nome";
$stmt = $pdo->query($sql);
$pontos = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Pontos de Coleta</h1>
    <span class="badge bg-success fs-6"><?php echo count($pontos); ?> local(is)</span>
</div>

<p class="text-muted mb-4">
    Encontre locais para descartar corretamente seus resíduos.
    Verifique endereço, horário de funcionamento e tipos de materiais aceitos.
</p>

<?php if (count($pontos) === 0): ?>
    <div class="alert alert-info">Nenhum ponto de coleta cadastrado no momento.</div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($pontos as $ponto): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-residuo shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?php echo htmlspecialchars($ponto['nome']); ?></h5>

                        <?php if ($ponto['endereco']): ?>
                            <p class="card-text small mb-1">
                                <strong>Endereço:</strong><br>
                                <?php echo htmlspecialchars($ponto['endereco']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['telefone']): ?>
                            <p class="card-text small mb-1">
                                <strong>Telefone:</strong> <?php echo htmlspecialchars($ponto['telefone']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['horario_funcionamento']): ?>
                            <p class="card-text small mb-2">
                                <strong>Horário:</strong><br>
                                <?php echo htmlspecialchars($ponto['horario_funcionamento']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['residuos_aceitos']): ?>
                            <div class="mb-2">
                                <strong class="small">Aceita:</strong><br>
                                <?php foreach (explode(', ', $ponto['residuos_aceitos']) as $res_nome): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($res_nome); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($ponto['latitude'] && $ponto['longitude']): ?>
                            <a href="https://www.openstreetmap.org/?mlat=<?php echo $ponto['latitude']; ?>&mlon=<?php echo $ponto['longitude']; ?>&zoom=18"
                               target="_blank" class="btn btn-sm btn-outline-success mt-2">
                                Ver no mapa &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>