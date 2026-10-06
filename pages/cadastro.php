<?php
/**
 * Cadastro de usuário (RF11).
 *
 * RF11 — Hash de senha: a senha NUNCA é salva em texto puro.
 * Antes de gravar no banco, ela passa por password_hash(), que gera
 * um hash bcrypt seguro. O texto puro é descartado ao fim da requisição.
 *
 * Segurança: o POST só é processado com token CSRF válido
 * (ver config/seguranca.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';

$titulo_pagina = 'Criar conta';

$erros = [];
$nome  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $senha     = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    // CSRF: recusa requisição forjada antes de qualquer validação
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    }

    // Validação dos campos
    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe seu nome completo (mínimo de 3 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }
    if ($senha !== $confirmar) {
        $erros[] = 'A confirmação de senha não confere.';
    }

    // O e-mail deve ser único na tabela usuario
    if (empty($erros)) {
        $stmt = $pdo->prepare('SELECT id_usuario FROM usuario WHERE email = :email');
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        if ($stmt->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado. Use a página de login.';
        }
    }

    // Cadastro efetivo
    if (empty($erros)) {
        // RF11: gera o hash bcrypt da senha (nunca grava o texto puro)
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO usuario (nome, email, senha, tipo_usuario)
                VALUES (:nome, :email, :senha, :tipo)';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':senha', $hash);
        $stmt->bindValue(':tipo', 'comum');
        $stmt->execute();

        // Já deixa o usuário autenticado
        session_regenerate_id(true);
        $_SESSION['id_usuario']   = (int)$pdo->lastInsertId();
        $_SESSION['nome_usuario'] = $nome;
        $_SESSION['tipo_usuario'] = 'comum';

        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Materiais reaproveitáveis">
    <span class="crumb">Sua conta</span>
    <h1 class="mt-2">Criar conta</h1>
    <p class="mt-2">
        Cadastre-se para compartilhar materiais e salvar seus resultados nos quizzes.
    </p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <h4 class="card-title mb-4"><i class="bi bi-person-plus me-2"></i>Dados do cadastro</h4>

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?php echo htmlspecialchars($erro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="cadastro.php">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome completo</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               value="<?php echo htmlspecialchars($nome); ?>" required minlength="3">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="senha" name="senha"
                                   required minlength="6" autocomplete="new-password">
                            <button class="btn btn-toggle-senha" type="button"
                                    data-toggle-senha="#senha"
                                    aria-label="Mostrar senha" title="Mostrar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Mínimo de 6 caracteres. É armazenada apenas como hash bcrypt.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirmar_senha" class="form-label">Confirmar senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha"
                                   required minlength="6" autocomplete="new-password">
                            <button class="btn btn-toggle-senha" type="button"
                                    data-toggle-senha="#confirmar_senha"
                                    aria-label="Mostrar senha" title="Mostrar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Criar minha conta</button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Já tem conta? <a href="login.php">Entrar</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
