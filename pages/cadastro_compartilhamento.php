<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$titulo_pagina = 'Oferecer um item';
$erros = [];
$id_residuo = 0;
$descricao = '';
$residuos = $pdo->query('SELECT id_residuo, nome FROM residuo ORDER BY nome')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_residuo = (int) ($_POST['id_residuo'] ?? 0);
    $descricao = trim((string) ($_POST['descricao'] ?? ''));

    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }
    if ($id_residuo <= 0) {
        $erros[] = 'Selecione o resíduo que deseja compartilhar.';
    } else {
        $stmt = $pdo->prepare('SELECT id_residuo FROM residuo WHERE id_residuo = :id');
        $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            $erros[] = 'O resíduo selecionado não existe.';
        }
    }
    if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 2000) {
        $erros[] = 'Descreva o item usando entre 10 e 2000 caracteres.';
    }

    if (empty($erros)) {
        $stmt = $pdo->prepare(
            'INSERT INTO compartilhamento_residuo (id_usuario_ofertante, id_residuo, descricao)
             VALUES (:usuario, :residuo, :descricao)'
        );
        $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
        $stmt->bindValue(':residuo', $id_residuo, PDO::PARAM_INT);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->execute();
        $id = (int) $pdo->lastInsertId();
        header('Location: compartilhamentos.php?status=disponivel&sucesso=oferta#oferta-' . $id);
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Oferecer um item</h1>
    <p class="mt-2">Cadastre algo que ainda pode ser útil para outra pessoa.</p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <?php if ($erros): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if (!$residuos): ?>
                    <div class="alert alert-info">Cadastre um resíduo antes de criar uma oferta.</div>
                    <a href="cadastro_residuo.php" class="btn btn-success">Cadastrar resíduo</a>
                <?php else: ?>
                    <form method="post" action="cadastro_compartilhamento.php">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="id_residuo" class="form-label">Resíduo</label>
                            <select class="form-select" id="id_residuo" name="id_residuo" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($residuos as $residuo): ?>
                                    <option value="<?php echo (int) $residuo['id_residuo']; ?>" <?php echo $id_residuo === (int) $residuo['id_residuo'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($residuo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="descricao" class="form-label">Descrição do item</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="6" required minlength="10" maxlength="2000" placeholder="Informe estado de conservação, quantidade e orientações para retirada."><?php echo htmlspecialchars($descricao); ?></textarea>
                            <div class="form-text">Não publique senhas, documentos ou outros dados sensíveis.</div>
                        </div>
                        <button type="submit" class="btn btn-success">Publicar oferta</button>
                        <a href="compartilhamentos.php" class="btn btn-outline-success">Cancelar</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
