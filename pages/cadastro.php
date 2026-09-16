<?php
/**
 * RF13 — Cadastro de usuário
 * Cria uma conta com nome, e-mail e senha (armazenada como hash bcrypt).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$erros = [];
$nome  = '';
$email = '';

// Token CSRF para proteger o envio do formulário
if (empty($_SESSION['csrf_cadastro'])) {
    $_SESSION['csrf_cadastro'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_cadastro'];

// Cadastro concluído (redirecionado após o POST — padrão Post/Redirect/Get)
$cadastrou = ($_GET['sucesso'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Valida o token CSRF
    if (!hash_equals($_SESSION['csrf_cadastro'], $_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
    }

    // 2. Recebe e limpa os dados
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $conf  = $_POST['confirmar_senha'] ?? '';

    // 3. Valida os campos
    if ($nome === '') {
        $erros[] = 'Informe seu nome.';
    } elseif (mb_strlen($nome) > 150) {
        $erros[] = 'O nome deve ter no máximo 150 caracteres.';
    }

    if ($email === '') {
        $erros[] = 'Informe seu e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    } elseif (mb_strlen($email) > 150) {
        $erros[] = 'O e-mail deve ter no máximo 150 caracteres.';
    }

    if (strlen($senha) < 8) {
        $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    if ($senha !== $conf) {
        $erros[] = 'A confirmação de senha não confere.';
    }

    // 4. Verifica e-mail duplicado (coluna UNIQUE)
    if (!$erros) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuario WHERE email = ?');
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() > 0) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }
    }

    // 5. Grava o novo usuário e redireciona
    if (!$erros) {
        $stmt = $pdo->prepare(
            'INSERT INTO usuario (nome, email, senha, tipo_usuario) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $nome,
            $email,
            password_hash($senha, PASSWORD_DEFAULT),
            'comum',
        ]);

        unset($_SESSION['csrf_cadastro']);
        session_regenerate_id(true);

        header('Location: cadastro.php?sucesso=1');
        exit;
    }
}

$titulo_pagina = 'Cadastro';
require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <span class="crumb">Sua conta</span>
    <h1 class="mt-2">Crie sua conta no Rhesys</h1>
    <p class="mt-2">
        Com uma conta você pode compartilhar itens com outras pessoas e
        acompanhar seus resultados nos quizzes educativos.
    </p>
</section>


<div class="row justify-content-center mt-5 reveal">
    <div class="col-lg-7">
        <div class="info-card p-4 p-lg-5">
            <?php if ($cadastrou): ?>
                <div class="alert alert-success d-flex align-items-center gap-3" role="alert">
                    <i class="bi bi-check-circle-fill" style="font-size: 1.6rem;"></i>
                    <div>
                        <strong>Conta criada com sucesso!</strong>
                        <div class="small mt-1">
                            Agora você pode <a href="compartilhamentos.php">compartilhar itens</a> ou
                            <a href="quiz.php">testar seus conhecimentos</a>.
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <?php if ($erros): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Corrija os itens abaixo:</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <?php foreach ($erros as $erro): ?>
                                <li><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="cadastro.php">
                    <input type="hidden" name="csrf" value="<?php echo $csrf_token; ?>">

                    <div class="mb-3">
                        <label for="nome" class="form-label fw-semibold">Nome completo</label>
                        <input type="text" class="form-control form-control-lg" id="nome" name="nome"
                               maxlength="150" placeholder="Ex.: Maria da Silva"
                               value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">E-mail</label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email"
                               maxlength="150" placeholder="voce@exemplo.com"
                               value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="senha" class="form-label fw-semibold">Senha</label>
                            <input type="password" class="form-control form-control-lg" id="senha" name="senha"
                                   minlength="8" placeholder="Mínimo de 8 caracteres" required>
                            <div class="form-text">Use pelo menos 8 caracteres.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmar_senha" class="form-label fw-semibold">Confirmar senha</label>
                            <input type="password" class="form-control form-control-lg" id="confirmar_senha"
                                   name="confirmar_senha" placeholder="Repita a senha" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-person-plus me-2"></i>Criar conta
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
