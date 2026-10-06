<?php
/**
 * Login de usuário (RF11).
 *
 * RF11 — Hash de senha: a senha correta NUNCA está no banco em texto puro.
 * A senha informada é comparada com o hash salvo usando password_verify().
 *
 * Segurança:
 * - CSRF: o POST só é processado com token válido (ver config/seguranca.php).
 * - Rate limiting: após LOGIN_MAX_TENTATIVAS falhas, o IP+e-mail é
 *   bloqueado por LOGIN_BLOQUEIO_MINUTOS minutos.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';

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

    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $erros[] = 'Informe e-mail e senha.';
    } else {
        $chave = tentativa_chave($email);

        // Rate limiting: bloqueia antes de consultar o banco
        $restanteBloqueio = login_bloqueado($pdo, $chave);
        if ($restanteBloqueio > 0) {
            $minutos = (int) ceil($restanteBloqueio / 60);
            $erros[] = 'Muitas tentativas de login. Tente novamente em '
                . $minutos . ' minuto(s).';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM usuario WHERE email = :email');
            $stmt->bindValue(':email', $email);
            $stmt->execute();
            $usuario = $stmt->fetch();

            // RF11: compara a senha informada com o hash bcrypt salvo no banco
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                login_limpar($pdo, $chave); // zera o contador de falhas
                session_regenerate_id(true);
                $_SESSION['id_usuario']   = (int)$usuario['id_usuario'];
                $_SESSION['nome_usuario'] = $usuario['nome'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];

                header('Location: ' . BASE_URL . 'index.php');
                exit;
            }

            // Falhou: registra a tentativa (pode atingir o bloqueio)
            $restantes = login_registrar_falha($pdo, $chave);

            // Mensagem genérica: não revela se o e-mail existe ou não
            if ($restantes > 0) {
                $erros[] = 'E-mail ou senha incorretos. Tentativas restantes: '
                    . $restantes . '.';
            } else {
                $erros[] = 'E-mail ou senha incorretos. Acesso bloqueado por '
                    . LOGIN_BLOQUEIO_MINUTOS . ' minutos.';
            }
        }
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
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="senha" name="senha" required
                                   autocomplete="current-password">
                            <button class="btn btn-toggle-senha" type="button"
                                    data-toggle-senha="#senha"
                                    aria-label="Mostrar senha" title="Mostrar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
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
