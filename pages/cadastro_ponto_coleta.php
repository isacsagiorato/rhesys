<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$id_ponto = (int) (($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['id_ponto'] ?? 0 : $_GET['id'] ?? 0));
$titulo_pagina = $id_ponto > 0 ? 'Editar ponto de coleta' : 'Cadastrar ponto de coleta';
$erros = [];
$nome = $endereco = $latitude = $longitude = $telefone = $horario = '';
$selecionados = [];
$residuos = $pdo->query('SELECT id_residuo, nome FROM residuo ORDER BY nome')->fetchAll();
$ids_validos = array_map('intval', array_column($residuos, 'id_residuo'));

if ($id_ponto > 0) {
    $stmt = $pdo->prepare('SELECT * FROM ponto_coleta WHERE id_ponto = :id');
    $stmt->bindValue(':id', $id_ponto, PDO::PARAM_INT);
    $stmt->execute();
    $ponto = $stmt->fetch();
    if (!$ponto) {
        $erros[] = 'Ponto de coleta não encontrado.';
        $id_ponto = 0;
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $nome = $ponto['nome'];
        $endereco = (string) $ponto['endereco'];
        $latitude = (string) $ponto['latitude'];
        $longitude = (string) $ponto['longitude'];
        $telefone = (string) $ponto['telefone'];
        $horario = (string) $ponto['horario_funcionamento'];
        $stmt = $pdo->prepare('SELECT id_residuo FROM residuo_ponto_coleta WHERE id_ponto = :id');
        $stmt->bindValue(':id', $id_ponto, PDO::PARAM_INT);
        $stmt->execute();
        $selecionados = array_map('intval', array_column($stmt->fetchAll(), 'id_residuo'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ponto = (int) ($_POST['id_ponto'] ?? 0);
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $endereco = trim((string) ($_POST['endereco'] ?? ''));
    $latitude = trim((string) ($_POST['latitude'] ?? ''));
    $longitude = trim((string) ($_POST['longitude'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $horario = trim((string) ($_POST['horario_funcionamento'] ?? ''));
    $entrada_residuos = $_POST['residuos'] ?? [];
    $selecionados = is_array($entrada_residuos) ? array_values(array_unique(array_filter(array_map('intval', $entrada_residuos)))) : [];

    if (!verificar_csrf()) $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) $erros[] = 'Informe um nome entre 3 e 150 caracteres.';
    if (mb_strlen($endereco) < 5 || mb_strlen($endereco) > 255) $erros[] = 'Informe um endereço entre 5 e 255 caracteres.';
    $lat = filter_var($latitude, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($longitude, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lat < -90 || $lat > 90) $erros[] = 'Informe uma latitude válida entre -90 e 90.';
    if ($lng === false || $lng < -180 || $lng > 180) $erros[] = 'Informe uma longitude válida entre -180 e 180.';
    if (mb_strlen($telefone) > 20) $erros[] = 'O telefone deve ter no máximo 20 caracteres.';
    if (mb_strlen($horario) > 150) $erros[] = 'O horário deve ter no máximo 150 caracteres.';
    if (!$selecionados) $erros[] = 'Selecione ao menos um resíduo aceito neste ponto.';
    if (array_diff($selecionados, $ids_validos)) $erros[] = 'Um ou mais resíduos selecionados não existem.';

    if (!$erros) {
        try {
            $pdo->beginTransaction();
            if ($id_ponto > 0) {
                $stmt = $pdo->prepare('SELECT id_ponto FROM ponto_coleta WHERE id_ponto = :id FOR UPDATE');
                $stmt->bindValue(':id', $id_ponto, PDO::PARAM_INT);
                $stmt->execute();
                if (!$stmt->fetch()) throw new RuntimeException('O ponto de coleta não existe mais.');
                $stmt = $pdo->prepare('UPDATE ponto_coleta SET nome = :nome, endereco = :endereco, latitude = :latitude, longitude = :longitude, telefone = :telefone, horario_funcionamento = :horario WHERE id_ponto = :id');
                $stmt->bindValue(':id', $id_ponto, PDO::PARAM_INT);
            } else {
                $stmt = $pdo->prepare('INSERT INTO ponto_coleta (nome, endereco, latitude, longitude, telefone, horario_funcionamento) VALUES (:nome, :endereco, :latitude, :longitude, :telefone, :horario)');
            }
            $stmt->bindValue(':nome', $nome);
            $stmt->bindValue(':endereco', $endereco);
            $stmt->bindValue(':latitude', $latitude);
            $stmt->bindValue(':longitude', $longitude);
            $stmt->bindValue(':telefone', $telefone !== '' ? $telefone : null, $telefone !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':horario', $horario !== '' ? $horario : null, $horario !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();
            if ($id_ponto === 0) $id_ponto = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('DELETE FROM residuo_ponto_coleta WHERE id_ponto = :id');
            $stmt->bindValue(':id', $id_ponto, PDO::PARAM_INT);
            $stmt->execute();
            $stmt = $pdo->prepare('INSERT INTO residuo_ponto_coleta (id_residuo, id_ponto) VALUES (:residuo, :ponto)');
            $stmt->bindValue(':ponto', $id_ponto, PDO::PARAM_INT);
            foreach ($selecionados as $id_residuo) {
                $stmt->bindValue(':residuo', $id_residuo, PDO::PARAM_INT);
                $stmt->execute();
            }
            $pdo->commit();
            header('Location: pontos_coleta.php?sucesso=ponto#ponto-' . $id_ponto);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro ao salvar ponto de coleta: ' . $e->getMessage());
            $erros[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o ponto de coleta.';
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Local de coleta de resíduos">
    <span class="crumb">Descarte no lugar certo</span>
    <h1 class="mt-2"><?php echo $id_ponto > 0 ? 'Editar ponto de coleta' : 'Cadastrar ponto de coleta'; ?></h1>
    <p class="mt-2">Informe a localização e todos os resíduos aceitos neste ponto.</p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-lg-9">
        <div class="card shadow-sm"><div class="card-body p-4 p-lg-5">
            <?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="cadastro_ponto_coleta.php<?php echo $id_ponto > 0 ? '?id=' . $id_ponto : ''; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id_ponto" value="<?php echo $id_ponto; ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="nome" class="form-label">Nome do ponto</label>
                        <input class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required minlength="3" maxlength="150">
                    </div>
                    <div class="col-12">
                        <label for="endereco" class="form-label">Endereço completo</label>
                        <input class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>" required minlength="5" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label for="latitude" class="form-label">Latitude</label>
                        <input class="form-control" id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="<?php echo htmlspecialchars($latitude); ?>" required placeholder="-21.9696000">
                    </div>
                    <div class="col-md-6">
                        <label for="longitude" class="form-label">Longitude</label>
                        <input class="form-control" id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="<?php echo htmlspecialchars($longitude); ?>" required placeholder="-46.7906000">
                    </div>
                    <div class="col-md-6">
                        <label for="telefone" class="form-label">Telefone (opcional)</label>
                        <input class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($telefone); ?>" maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label for="horario_funcionamento" class="form-label">Horário de funcionamento (opcional)</label>
                        <input class="form-control" id="horario_funcionamento" name="horario_funcionamento" value="<?php echo htmlspecialchars($horario); ?>" maxlength="150" placeholder="Seg-Sex 08:00-17:00">
                    </div>
                </div>
                <fieldset class="mt-4">
                    <legend class="h5">Resíduos aceitos</legend>
                    <?php if (!$residuos): ?>
                        <div class="alert alert-info">Cadastre um resíduo antes de criar o ponto.</div>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($residuos as $residuo): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="residuos[]" value="<?php echo (int) $residuo['id_residuo']; ?>" id="residuo-<?php echo (int) $residuo['id_residuo']; ?>" <?php echo in_array((int) $residuo['id_residuo'], $selecionados, true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="residuo-<?php echo (int) $residuo['id_residuo']; ?>"><?php echo htmlspecialchars($residuo['nome']); ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-success" <?php echo !$residuos ? 'disabled' : ''; ?>><?php echo $id_ponto > 0 ? 'Salvar alterações' : 'Cadastrar ponto'; ?></button>
                    <a href="pontos_coleta.php" class="btn btn-outline-success">Cancelar</a>
                    <a href="https://www.openstreetmap.org/" target="_blank" rel="noopener noreferrer" class="btn btn-link">Consultar coordenadas</a>
                </div>
            </form>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
