<?php
/**
 * Cadastro de classificação de resíduos — Sprint 2 (tela simples).
 *
 * Permite cadastrar as classes previstas na ABNT NBR 10004:
 * Classe I (Perigosos), Classe II A (Não Inertes) e Classe II B (Inertes).
 * As três classes iniciais já vêm populadas pelo script do banco
 * (database/rhesys_banco_dados.sql).
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

$titulo_pagina = 'Cadastrar classificação';

$erros     = [];
$sucesso   = '';
$nome      = '';
$descricao = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    // CSRF: recusa requisição forjada antes de qualquer validação
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }

    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe o nome da classificação (mínimo de 3 caracteres).';
    }
    if (mb_strlen($descricao) < 10) {
        $erros[] = 'Descreva a classificação (mínimo de 10 caracteres).';
    }

    // O nome da classificação deve ser único
    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_classificacao FROM classificacao WHERE nome = :nome');
        $stmt->bindValue(':nome', $nome);
        $stmt->execute();
        if ($stmt->fetch()) {
            $erros[] = 'Já existe uma classificação com este nome.';
        }
    }

    // Cadastro efetivo
    if (empty($erros)) {
        $sql = 'INSERT INTO classificacao (nome, descricao) VALUES (:nome, :descricao)';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->execute();

        $sucesso   = 'Classificação "' . $nome . '" cadastrada com sucesso.';
        $nome      = '';
        $descricao = '';
    }
}

// Lista as classificações já cadastradas
$classificacoes = $pdo->query(
    'SELECT c.*, COUNT(r.id_residuo) AS total_residuos
     FROM classificacao c
     LEFT JOIN residuo r ON r.id_classificacao = c.id_classificacao
     GROUP BY c.id_classificacao
     ORDER BY c.nome'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reciclaveis.jpg" alt="Materiais separados por classificação">
    <span class="crumb">Gestão de resíduos</span>
    <h1 class="mt-2">Cadastrar classificação</h1>
    <p class="mt-2">
        Cadastre as classes de resíduos conforme a ABNT NBR 10004:
        Classe I (Perigosos), Classe II A (Não Inertes) e Classe II B (Inertes).
    </p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <h4 class="card-title mb-4"><i class="bi bi-tags me-2"></i>Nova classificação</h4>

                <?php if ($sucesso !== ''): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($sucesso); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?php echo htmlspecialchars($erro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="cadastro_classificacao.php">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da classificação</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               value="<?php echo htmlspecialchars($nome); ?>"
                               placeholder="Ex.: Classe I - Perigosos" required minlength="3" maxlength="100">
                    </div>
                    <div class="mb-4">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4"
                                  placeholder="Descreva as características desta classe..."
                                  required minlength="10"><?php echo htmlspecialchars($descricao); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Cadastrar classificação</button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Precisa cadastrar um resíduo? <a href="cadastro_residuo.php">Cadastre aqui</a>
                </p>
            </div>
        </div>
    </div>
</div>

<h3 class="mt-5 mb-3 reveal">Classificações cadastradas</h3>
<?php if (count($classificacoes) === 0): ?>
    <div class="alert alert-info reveal">Nenhuma classificação cadastrada até o momento.</div>
<?php else: ?>
    <div class="row g-4 reveal">
        <?php foreach ($classificacoes as $class): ?>
            <div class="col-md-4">
                <div class="info-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($class['nome']); ?></h5>
                            <span class="badge-soft"><?php echo (int) $class['total_residuos']; ?> resíduo(s)</span>
                        </div>
                        <p class="card-text text-muted small mb-0"><?php echo htmlspecialchars($class['descricao']); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
