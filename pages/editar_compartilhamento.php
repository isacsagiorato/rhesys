<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
exige_login();

$titulo_pagina = 'Editar material';
$erros = [];
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: meus_compartilhamentos.php');
    exit;
}

$valores = [
    'nome' => '', 'descricao' => '', 'categoria' => '', 'quantidade' => '',
    'estado_conservacao' => 'usado', 'localizacao' => '', 'condicoes_retirada' => '',
];
$imagem_atual = null;

function editar_carregar_registro(PDO $pdo, $id, $usuario) {
    $stmt = $pdo->prepare('SELECT * FROM compartilhamento_residuo WHERE id_compartilhamento = :id AND id_usuario_ofertante = :usuario');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nome', 'categoria', 'quantidade', 'localizacao', 'condicoes_retirada'] as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }
    $valores['descricao'] = trim((string) ($_POST['descricao'] ?? ''));
    $valores['estado_conservacao'] = trim((string) ($_POST['estado_conservacao'] ?? 'usado'));

    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }
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

    $upload = compartilhamento_validar_imagem($_FILES['imagem'] ?? null, $erros);

    if (empty($erros)) {
        $caminho_novo = null;
        try {
            $pdo->beginTransaction();
            $registro = $pdo->prepare('SELECT imagem, id_residuo FROM compartilhamento_residuo WHERE id_compartilhamento = :id AND id_usuario_ofertante = :usuario FOR UPDATE');
            $registro->bindValue(':id', $id, PDO::PARAM_INT);
            $registro->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $registro->execute();
            $atual = $registro->fetch();
            if (!$atual) {
                throw new RuntimeException('Material não encontrado ou sem permissão de edição.');
            }

            if ($upload !== null) {
                $caminho_novo = compartilhamento_salvar_imagem($_FILES['imagem'], $upload[1]);
            }
            $nova_imagem = $caminho_novo !== null ? $caminho_novo : $atual['imagem'];

            $stmt = $pdo->prepare(
                'UPDATE compartilhamento_residuo
                    SET nome = :nome, categoria = :categoria, quantidade = :quantidade,
                        estado_conservacao = :estado, localizacao = :localizacao,
                        condicoes_retirada = :condicoes, descricao = :descricao,
                        imagem = :imagem, id_residuo = NULL
                  WHERE id_compartilhamento = :id AND id_usuario_ofertante = :usuario'
            );
            $stmt->bindValue(':nome', $valores['nome']);
            $stmt->bindValue(':categoria', $valores['categoria']);
            $stmt->bindValue(':quantidade', $valores['quantidade']);
            $stmt->bindValue(':estado', $valores['estado_conservacao']);
            $stmt->bindValue(':localizacao', $valores['localizacao']);
            $stmt->bindValue(':condicoes', $valores['condicoes_retirada'] !== '' ? $valores['condicoes_retirada'] : null,
                $valores['condicoes_retirada'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':descricao', $valores['descricao']);
            $stmt->bindValue(':imagem', $nova_imagem, $nova_imagem === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();
            $pdo->commit();

            if ($caminho_novo !== null && $atual['imagem'] !== null && $atual['imagem'] !== $caminho_novo) {
                compartilhamento_remover_imagem($atual['imagem']);
            }

            header('Location: meus_compartilhamentos.php?sucesso=editado#oferta-' . $id);
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($caminho_novo !== null) compartilhamento_remover_imagem($caminho_novo);
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($caminho_novo !== null) compartilhamento_remover_imagem($caminho_novo);
            error_log('Erro ao editar material: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar as alterações. Tente novamente.';
        }
    }
} else {
    $registro = editar_carregar_registro($pdo, $id, $_SESSION['id_usuario']);
    if (!$registro) {
        header('Location: meus_compartilhamentos.php');
        exit;
    }
    $valores = [
        'nome' => (string) $registro['nome'],
        'descricao' => (string) $registro['descricao'],
        'categoria' => (string) $registro['categoria'],
        'quantidade' => (string) $registro['quantidade'],
        'estado_conservacao' => (string) ($registro['estado_conservacao'] ?? 'usado'),
        'localizacao' => (string) $registro['localizacao'],
        'condicoes_retirada' => (string) $registro['condicoes_retirada'],
    ];
    $imagem_atual = $registro['imagem'];
}

require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Materiais que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Editar material</h1>
    <p class="mt-2">Atualize as informações do material que você está oferecendo.</p>
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
                <?php if ($imagem_atual): ?>
                    <div class="mb-3">
                        <span class="form-label d-block">Imagem atual</span>
                        <img src="<?php echo BASE_URL . htmlspecialchars($imagem_atual); ?>" alt="Imagem atual do material" style="max-width: 320px; border-radius: 1rem;" class="img-fluid border">
                        <div class="form-text">Envie uma nova imagem abaixo para substituí-la.</div>
                    </div>
                <?php endif; ?>
                <form method="post" action="editar_compartilhamento.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <?php require __DIR__ . '/../includes/form_material.php'; ?>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Salvar alterações</button>
                        <a href="meus_compartilhamentos.php" class="btn btn-outline-success">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>