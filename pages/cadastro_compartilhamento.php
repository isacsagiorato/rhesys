<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
exige_login();

$titulo_pagina = 'Oferecer material';
$erros = [];
$valores = [
    'nome' => '', 'descricao' => '', 'categoria' => '', 'quantidade' => '',
    'estado_conservacao' => 'usado', 'localizacao' => '', 'condicoes_retirada' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nome', 'categoria', 'quantidade', 'localizacao', 'condicoes_retirada'] as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $valores['descricao'] = trim((string) ($_POST['descricao'] ?? ''));
    $valores['estado_conservacao'] = trim((string) ($_POST['estado_conservacao'] ?? 'usado'));
    $id_residuo = (int) ($_POST['id_residuo'] ?? 0);

    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }

    // Compatibilidade com o fluxo antigo: oferta vinculada a um resíduo do catálogo
    // (id_residuo + descricao). É acionado apenas quando o nome do material vem vazio.
    $modo_legado = ($valores['nome'] === '' && $id_residuo > 0);

    if ($modo_legado) {
        $stmt = $pdo->prepare('SELECT id_residuo FROM residuo WHERE id_residuo = :id');
        $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            $erros[] = 'O resíduo selecionado não existe.';
        }
        if (mb_strlen($valores['descricao']) < 10 || mb_strlen($valores['descricao']) > 2000) {
            $erros[] = 'Descreva o item usando entre 10 e 2000 caracteres.';
        }
    } else {
        if (mb_strlen($valores['nome']) < 3 || mb_strlen($valores['nome']) > 150) {
            $erros[] = 'O nome do material deve ter entre 3 e 150 caracteres.';
        }
        if (!isset(CATEGORIAS_MATERIAIS[$valores['categoria']])) {
            $erros[] = 'Selecione uma categoria válida.';
        }
        if ($valores['quantidade'] === '' || mb_strlen($valores['quantidade']) > 100) {
            $erros[] = 'Informe a quantidade (até 100 caracteres).';
        }
        if (!isset(ESTADOS_CONSERVACAO[$valores['estado_conservacao']])) {
            $erros[] = 'Selecione um estado de conservação válido.';
        }
        if (mb_strlen($valores['localizacao']) < 2 || mb_strlen($valores['localizacao']) > 200) {
            $erros[] = 'Informe o local de retirada (entre 2 e 200 caracteres).';
        }
        if (mb_strlen($valores['descricao']) < 10 || mb_strlen($valores['descricao']) > 2000) {
            $erros[] = 'Descreva o material usando entre 10 e 2000 caracteres.';
        }
        if (mb_strlen($valores['condicoes_retirada']) > 1000) {
            $erros[] = 'As condições de retirada devem ter no máximo 1000 caracteres.';
        }
    }

    $upload = compartilhamento_validar_imagem($_FILES['imagem'] ?? null, $erros);

    if (empty($erros)) {
        $caminho_imagem = null;
        try {
            if ($upload !== null) {
                $caminho_imagem = compartilhamento_salvar_imagem($_FILES['imagem'], $upload[1]);
            }
            if ($modo_legado) {
                $stmt = $pdo->prepare(
                    'INSERT INTO compartilhamento_residuo (id_usuario_ofertante, id_residuo, descricao)
                     VALUES (:usuario, :residuo, :descricao)'
                );
                $stmt->bindValue(':residuo', $id_residuo, PDO::PARAM_INT);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO compartilhamento_residuo
                        (id_usuario_ofertante, id_residuo, nome, categoria, quantidade,
                         estado_conservacao, localizacao, condicoes_retirada, descricao, imagem)
                     VALUES
                        (:usuario, NULL, :nome, :categoria, :quantidade,
                         :estado, :localizacao, :condicoes, :descricao, :imagem)'
                );
                $stmt->bindValue(':nome', $valores['nome']);
                $stmt->bindValue(':categoria', $valores['categoria']);
                $stmt->bindValue(':quantidade', $valores['quantidade']);
                $stmt->bindValue(':estado', $valores['estado_conservacao']);
                $stmt->bindValue(':localizacao', $valores['localizacao']);
                $stmt->bindValue(':condicoes', $valores['condicoes_retirada'] !== '' ? $valores['condicoes_retirada'] : null,
                    $valores['condicoes_retirada'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(':imagem', $caminho_imagem, $caminho_imagem === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':descricao', $valores['descricao']);
            $stmt->execute();
            $id = (int) $pdo->lastInsertId();
            header('Location: compartilhamentos.php?status=todos&sucesso=oferta#oferta-' . $id);
            exit;
        } catch (Throwable $e) {
            if ($caminho_imagem !== null) {
                compartilhamento_remover_imagem($caminho_imagem);
            }
            error_log('Erro ao cadastrar material: ' . $e->getMessage());
            $erros[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível publicar o material.';
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Materiais que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Oferecer material</h1>
    <p class="mt-2">Cadastre objetos, materiais ou resíduos que seriam descartados e podem ser úteis para outra pessoa.</p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-lg-9">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <?php if ($erros): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="cadastro_compartilhamento.php" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <?php require __DIR__ . '/../includes/form_material.php'; ?>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-success"><i class="bi bi-box-arrow-up me-1"></i>Publicar material</button>
                        <a href="compartilhamentos.php" class="btn btn-outline-success">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>