<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$titulo_pagina = 'Pontos de Coleta';
require __DIR__ . '/../includes/header.php';

// Foto ilustrativa conforme o tipo de ponto
function foto_ponto($nome) {
    $n = mb_strtolower((string) $nome, 'UTF-8');
    if (mb_strpos($n, 'eletr') !== false) {
        return BASE_URL . 'img/fotos/eletronicos.jpg';
    }
    return BASE_URL . 'img/fotos/coleta.jpg';
}

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

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/montanhas.jpg" alt="Paisagem verde de montanhas ao amanhecer">
    <span class="crumb">Descarte no lugar certo</span>
    <h1 class="mt-2">Pontos de Coleta</h1>
    <p class="mt-2">
        Encontre locais para descartar corretamente seus resíduos. Verifique endereço,
        horário de funcionamento e tipos de materiais aceitos.
    </p>
</section>

<?php if (count($pontos) === 0): ?>
    <div class="alert alert-info mt-4">Nenhum ponto de coleta cadastrado no momento.</div>
<?php else: ?>
    <p class="text-muted small mt-4 mb-0">
        <i class="bi bi-geo-alt"></i>
        <?php echo count($pontos); ?> local(is) disponível(is) para consulta.
    </p>
    <div class="row g-4 mt-2 reveal">
        <?php foreach ($pontos as $ponto): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-residuo point-card">
                    <div class="photo">
                        <img src="<?php echo foto_ponto($ponto['nome']); ?>" alt="Foto ilustrativa do ponto de coleta">
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($ponto['nome']); ?></h5>

                        <?php if ($ponto['endereco']): ?>
                            <p class="small mb-1">
                                <i class="bi bi-geo-alt me-1" style="color: var(--rhe-600);"></i>
                                <?php echo htmlspecialchars($ponto['endereco']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['telefone']): ?>
                            <p class="small mb-1">
                                <i class="bi bi-telephone me-1" style="color: var(--rhe-600);"></i>
                                <?php echo htmlspecialchars($ponto['telefone']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['horario_funcionamento']): ?>
                            <p class="small mb-2">
                                <i class="bi bi-clock me-1" style="color: var(--rhe-600);"></i>
                                <?php echo htmlspecialchars($ponto['horario_funcionamento']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($ponto['residuos_aceitos']): ?>
                            <div class="mb-2">
                                <strong class="small">Aceita:</strong><br>
                                <?php foreach (explode(', ', $ponto['residuos_aceitos']) as $res_nome): ?>
                                    <span class="badge-soft me-1 mt-1" style="font-size: .8rem;"><?php echo htmlspecialchars($res_nome); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($ponto['latitude'] && $ponto['longitude']): ?>
                            <a href="https://www.openstreetmap.org/?mlat=<?php echo $ponto['latitude']; ?>&mlon=<?php echo $ponto['longitude']; ?>&zoom=18"
                               target="_blank" class="link-arrow small">
                                Ver no mapa <i class="bi bi-arrow-up-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>