<?php
/**
 * Cadastro de resíduo — Sprint 2 (RF13).
 *
 * Formulário com nome, descrição, forma de descarte, reciclagem e
 * logística reversa, além da classificação (ABNT NBR 10004) e das
 * palavras-chave usadas na busca (RF03).
 *
 * Segurança:
 * - Proteção de rotas: exige usuário logado (RNF11).
 * - CSRF: o POST só é processado com token válido (config/seguranca.php).
 * - Prepared statements em todos os acessos ao banco.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';

// RNF11 — Proteção de rotas: cadastro exige usuário logado
exige_login();

$titulo_pagina = 'Cadastrar resíduo';

$erros     = [];
$nome      = '';
$descricao = '';
$descarte  = '';
$reciclagem = '';
$palavras  = '';
$logistica  = '';
$id_classificacao = 0;

// Lista de classificações para o select
$classificacoes = $pdo->query(
    'SELECT id_classificacao, nome FROM classificacao ORDER BY nome'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome             = trim($_POST['nome'] ?? '');
    $descricao        = trim($_POST['descricao'] ?? '');
    $descarte         = trim($_POST['forma_descarte'] ?? '');
    $reciclagem       = trim($_POST['reciclagem'] ?? '');
    $palavras         = trim($_POST['palavras_chave'] ?? '');
    $logistica        = isset($_POST['possui_logistica_reversa']) ? 1 : 0;
    $id_classificacao = (int) ($_POST['id_classificacao'] ?? 0);

    // CSRF: recusa requisição forjada antes de qualquer validação
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }

    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe o nome do resíduo (mínimo de 3 caracteres).';
    }
    if (mb_strlen($descricao) < 10) {
        $erros[] = 'Descreva o resíduo (mínimo de 10 caracteres).';
    }
    if (mb_strlen($descarte) < 10) {
        $erros[] = 'Informe a forma de descarte (mínimo de 10 caracteres).';
    }
    if (mb_strlen($reciclagem) < 10) {
        $erros[] = 'Informe como o resíduo pode ser reciclado (mínimo de 10 caracteres).';
    }

    // A classificação escolhida deve existir no banco
    if ($id_classificacao <= 0) {
        $erros[] = 'Selecione a classificação do resíduo.';
    } else {
        $stmt = $pdo->prepare('SELECT id_classificacao FROM classificacao WHERE id_classificacao = :id');
        $stmt->bindValue(':id', $id_classificacao, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            $erros[] = 'A classificação selecionada não existe. Cadastre-a primeiro.';
        }
    }

    // Nome único (evita duplicar itens na listagem/busca)
    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_residuo FROM residuo WHERE nome = :nome');
        $stmt->bindValue(':nome', $nome);
        $stmt->execute();
        if ($stmt->fetch()) {
            $erros[] = 'Já existe um resíduo cadastrado com este nome.';
        }
    }

    // Cadastro efetivo
    if (empty($erros)) {
        $sql = 'INSERT INTO residuo
                    (id_classificacao, nome, descricao, forma_descarte,
                     reciclagem, palavras_chave, possui_logistica_reversa)
                VALUES
                    (:id_classificacao, :nome, :descricao, :forma_descarte,
                     :reciclagem, :palavras_chave, :logistica)';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_classificacao', $id_classificacao, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->bindValue(':forma_descarte', $descarte);
        $stmt->bindValue(':reciclagem', $reciclagem);
        $stmt->bindValue(':palavras_chave', $palavras !== '' ? $palavras : null);
        $stmt->bindValue(':logistica', $logistica, PDO::PARAM_INT);
        $stmt->execute();

        // Vai para a página de detalhes do resíduo criado (RF04-RF08)
        header('Location: ' . BASE_URL . 'pages/residuos.php?id=' . (int) $pdo->lastInsertId());
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Lixeiras de coleta seletiva">
    <span class="crumb">Gestão de resíduos</span>
    <h1 class="mt-2">Cadastrar resíduo</h1>
    <p class="mt-2">
        Cadastre um material com a classificação (ABNT NBR 10004), a forma de descarte,
        a reciclagem e a logística reversa. O item entra na listagem e na busca.
    </p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <h4 class="card-title mb-4"><i class="bi bi-recycle me-2"></i>Dados do resíduo</h4>

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?php echo htmlspecialchars($erro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (count($classificacoes) === 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Não há nenhuma classificação cadastrada.
                        <a href="cadastro_classificacao.php">Cadastre uma classificação</a> antes de cadastrar resíduos.
                    </div>
                <?php else: ?>
                    <form method="post" action="cadastro_residuo.php">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome"
                                   value="<?php echo htmlspecialchars($nome); ?>"
                                   placeholder="Ex.: Garrafa PET" required minlength="3" maxlength="150">
                        </div>

                        <div class="mb-3">
                            <label for="id_classificacao" class="form-label">Classificação (ABNT NBR 10004)</label>
                            <select class="form-select" id="id_classificacao" name="id_classificacao" required>
                                <option value="" selected disabled>Selecione...</option>
                                <?php foreach ($classificacoes as $class): ?>
                                    <option value="<?php echo (int) $class['id_classificacao']; ?>"
                                        <?php echo $id_classificacao === (int) $class['id_classificacao'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Não encontrou a classe?
                                <a href="cadastro_classificacao.php">Cadastre uma nova classificação</a>.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"
                                      placeholder="O que é o material e por que precisa de cuidado no descarte..."
                                      required minlength="10"><?php echo htmlspecialchars($descricao); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="forma_descarte" class="form-label">Forma de descarte</label>
                            <textarea class="form-control" id="forma_descarte" name="forma_descarte" rows="3"
                                      placeholder="Onde e como este resíduo deve ser descartado..."
                                      required minlength="10"><?php echo htmlspecialchars($descarte); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="reciclagem" class="form-label">Reciclagem</label>
                            <textarea class="form-control" id="reciclagem" name="reciclagem" rows="3"
                                      placeholder="Como o material pode ser reaproveitado..."
                                      required minlength="10"><?php echo htmlspecialchars($reciclagem); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="palavras_chave" class="form-label">Palavras-chave (opcional)</label>
                            <input type="text" class="form-control" id="palavras_chave" name="palavras_chave"
                                   value="<?php echo htmlspecialchars($palavras); ?>"
                                   placeholder="Separe por vírgulas: ex.: pet, plastico, garrafa">
                            <div class="form-text">Melhoram os resultados da busca (RF03).</div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="possui_logistica_reversa"
                                   name="possui_logistica_reversa" value="1" <?php echo $logistica ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="possui_logistica_reversa">
                                Possui logística reversa (Lei nº 12.305/2010)
                            </label>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Cadastrar resíduo</button>
                    </form>
                <?php endif; ?>

                <p class="text-center text-muted small mt-4 mb-0">
                    <a href="residuos.php">Voltar para a listagem de resíduos</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

