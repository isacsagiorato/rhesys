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
require_once __DIR__ . '/../config/google.php';

$titulo_pagina = 'Entrar';

$erros = [];
$email = '';

// Se já está logado, vai direto para a home
if (usuario_logado()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Chegou aqui porque a sessão expirou (inatividade ou tempo máximo)
if (isset($_GET['expirada'])) {
    $erros[] = 'Sua sessão expirou por inatividade. Entre novamente para continuar.';
}

// Mensagem exibida quando o usuário tentou ver o resultado do quiz sem login
// (proteção de rotas em pages/quiz.php redireciona para cá com ?aviso=resultado_quiz)
if (($_GET['aviso'] ?? '') === 'resultado_quiz') {
    $erros[] = 'É necessário fazer login para ter o resultado do quiz. '
        . 'Entre na sua conta (ou crie uma) e responda o quiz novamente — '
        . 'assim a pontuação fica salva no seu perfil.';
}

// Mensagens do login com Google (ver pages/google_callback.php)
switch ($_GET['erro'] ?? '') {
    case 'google_nao_configurado':
        $erros[] = 'O login com Google ainda não está configurado neste servidor. '
            . 'Veja as instruções em config/google.php.';
        break;
    case 'google_cancelado':
        $erros[] = 'Você cancelou o login com Google. Tente novamente se quiser.';
        break;
    case 'google_email_nao_verificado':
        $erros[] = 'Seu e-mail do Google não está verificado. Verifique-o no Google e tente novamente.';
        break;
    case 'google_falha':
        $erros[] = 'Não foi possível concluir o login com Google. Tente novamente.';
        break;
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
                // Controle de sessão: marca o início para o cálculo
                // de expiração (SESSAO_TEMPO_MAXIMO) e de inatividade
                $_SESSION['sessao_iniciada'] = time();
                $_SESSION['ultimo_acesso']   = time();

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

                <div class="divisor-ou"><span>ou</span></div>
                <a class="btn btn-google w-100" href="google_login.php">
                    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 1.39 30.37 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.32 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Entrar com Google
                </a>

                <p class="text-center text-muted small mt-4 mb-0">
                    Não tem conta? <a href="cadastro.php">Cadastre-se</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
