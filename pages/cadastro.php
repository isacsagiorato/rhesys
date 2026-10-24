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
require_once __DIR__ . '/../config/validacao_email.php';
require_once __DIR__ . '/../config/google.php';

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
    // E-mail "existente": formato + não descartável + domínio real (MX/A no DNS)
    $erroEmail = validar_email_cadastro($email);
    if ($erroEmail !== null) {
        $erros[] = $erroEmail;
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
        // Controle de sessão: marcas de expiração (ver config/config.php)
        $_SESSION['sessao_iniciada'] = time();
        $_SESSION['ultimo_acesso']   = time();

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
                        <div class="form-text">
                            O domínio do e-mail é verificado no DNS — não é possível
                            cadastrar com um endereço que não exista.
                        </div>
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

                <div class="divisor-ou"><span>ou</span></div>
                <a class="btn btn-google w-100" href="google_login.php">
                    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 1.39 30.37 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.32 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Criar conta com Google
                </a>

                <p class="text-center text-muted small mt-4 mb-0">
                    Já tem conta? <a href="login.php">Entrar</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
