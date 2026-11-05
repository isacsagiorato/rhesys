<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$titulo_pagina = 'Pontos de Coleta';
$estilos_extras = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];
$scripts_extras = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', BASE_URL . 'js/mapa.js?v=1'];
$id_residuo = (int) ($_GET['id_residuo'] ?? 0);
$residuos = $pdo->query('SELECT id_residuo, nome FROM residuo ORDER BY nome')->fetchAll();

function foto_ponto($nome) {
    $n = mb_strtolower((string) $nome, 'UTF-8');
    return BASE_URL . (mb_strpos($n, 'eletr') !== false ? 'img/fotos/eletronicos.jpg' : 'img/fotos/coleta.jpg');
}

$sql = "SELECT p.*, GROUP_CONCAT(DISTINCT r.nome ORDER BY r.nome SEPARATOR ', ') AS residuos_aceitos
        FROM ponto_coleta p
        LEFT JOIN residuo_ponto_coleta rpc ON p.id_ponto = rpc.id_ponto
        LEFT JOIN residuo r ON rpc.id_residuo = r.id_residuo";
if ($id_residuo > 0) $sql .= ' WHERE EXISTS (SELECT 1 FROM residuo_ponto_coleta filtro WHERE filtro.id_ponto = p.id_ponto AND filtro.id_residuo = :id_residuo)';
$sql .= ' GROUP BY p.id_ponto ORDER BY p.nome';
$stmt = $pdo->prepare($sql);
if ($id_residuo > 0) $stmt->bindValue(':id_residuo', $id_residuo, PDO::PARAM_INT);
$stmt->execute();
$pontos = $stmt->fetchAll();
$pontos_mapa = [];
foreach ($pontos as $ponto) {
    if ($ponto['latitude'] !== null && $ponto['longitude'] !== null) {
        $pontos_mapa[] = [
            'nome' => $ponto['nome'],
            'endereco' => $ponto['endereco'],
            'latitude' => (float) $ponto['latitude'],
            'longitude' => (float) $ponto['longitude'],
        ];
    }
}
require __DIR__ . '/../includes/header.php';
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

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-4 reveal">
    <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label for="id_residuo" class="form-label small mb-1">Filtrar pelo resíduo aceito</label>
            <select name="id_residuo" id="id_residuo" class="form-select">
                <option value="0">Todos os resíduos</option>
                <?php foreach ($residuos as $residuo): ?>
                    <option value="<?php echo (int) $residuo['id_residuo']; ?>" <?php echo $id_residuo === (int) $residuo['id_residuo'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($residuo['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Filtrar</button>
        <?php if ($id_residuo > 0): ?><a href="pontos_coleta.php" class="btn btn-outline-secondary">Limpar</a><?php endif; ?>
    </form>
    <?php if (usuario_logado()): ?><a href="cadastro_ponto_coleta.php" class="btn btn-outline-success"><i class="bi bi-plus-circle me-1"></i>Cadastrar ponto</a><?php endif; ?>
</div>
<?php if (($_GET['sucesso'] ?? '') === 'ponto'): ?><div class="alert alert-success mt-4">Ponto de coleta e vínculos com resíduos salvos com sucesso.</div><?php endif; ?>

<?php if ($pontos_mapa): ?>
    <section class="mt-4 reveal" aria-labelledby="titulo-mapa">
        <h2 class="h4" id="titulo-mapa">Mapa dos pontos de coleta</h2>
        <p class="small text-muted">Clique nos marcadores para ver o endereço. O mapa usa OpenStreetMap.</p>
        <div id="mapa-pontos" class="mapa-pontos" role="region" aria-label="Mapa interativo dos pontos de coleta"></div>
        <script type="application/json" id="dados-pontos"><?php echo json_encode($pontos_mapa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
        <noscript><div class="alert alert-info mt-2">Ative o JavaScript para ver o mapa. Os links individuais continuam disponíveis.</div></noscript>
    </section>
<?php endif; ?>

<?php if (count($pontos) === 0): ?>
    <div class="alert alert-info mt-4">Nenhum ponto de coleta cadastrado no momento.</div>
<?php else: ?>
    <p class="text-muted small mt-4 mb-0">
        <i class="bi bi-geo-alt"></i>
        <?php echo count($pontos); ?> local(is) disponível(is) para consulta.
    </p>
    <div class="row g-4 mt-2 reveal">
        <?php foreach ($pontos as $ponto): ?>
            <div class="col-md-6 col-lg-4" id="ponto-<?php echo (int) $ponto['id_ponto']; ?>">
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

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <?php if ($ponto['latitude'] && $ponto['longitude']): ?>
                                <a href="https://www.openstreetmap.org/?mlat=<?php echo $ponto['latitude']; ?>&mlon=<?php echo $ponto['longitude']; ?>&zoom=18"
                                   target="_blank" rel="noopener noreferrer" class="link-arrow small">
                                    Ver no mapa <i class="bi bi-arrow-up-right"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (usuario_logado()): ?>
                                <a href="cadastro_ponto_coleta.php?id=<?php echo (int) $ponto['id_ponto']; ?>" class="link-arrow small">Editar vínculos <i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>