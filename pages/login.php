<?php
/**
 * Login de usuário (RF11).
 *
 * RF11 — Hash de senha: a senha correta NUNCA está no banco em texto puro.
 * A senha informada é comparada com o hash salvo usando password_verify().
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

$titulo_pagina = 'Entrar';

$erros = [];
$email = '';

// Se já está logado, vai direto para a home
if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $erros[] = 'Informe e-mail e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM usuario WHERE email = :email');
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $usuario = $stmt->fetch();

        // RF11: compara a senha informada com o hash bcrypt salvo no banco
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['id_usuario']   = (int)$usuario['id_usuario'];
            $_SESSION['nome_usuario'] = $usuario['nome'];
            $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }

        // Mensagem genérica: não revela se o e-mail existe ou não
        $erros[] = 'E-mail ou senha incorretos.';
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reciclaveis.jpg" alt="Recicláveis separados para coleta">
    <span class="crumb">Sua conta</span>
    <h1 class="mt-2">Entrar</h1>
    <p class="mt-2">Acesse sua conta para compartilhar e acompanhar seus resultados.</p>
</section>

<div class="row justify-content-center mt-5 reveal">
    <div class="col-md-8 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <h4 class="card-title mb-4"><i class="bi bi-box-arrow-in-right me-2"></i>Acesso ao sistema</h4>

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($erros as $erro): ?>
                                <li><?php echo htmlspecialchars($erro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required
                               autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-success w-100">Entrar</button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Não tem conta? <a href="cadastro.php">Cadastre-se</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
