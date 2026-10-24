<?php
/**
 * Callback do login com Google (OAuth 2.0).
 *
 * Fluxo resumido:
 *   1. O Google devolve ?code= (autorização) + ?state= (anti-CSRF);
 *   2. O state é conferido com o guardado na sessão (hash_equals);
 *   3. O code é trocado por um access token (POST no endpoint do Google);
 *   4. Com o token, busca-se o perfil — e-mail SEMPRE verificado pelo Google;
 *   5. Se o usuário já existe (por google_id ou e-mail), apenas conecta;
 *      senão, cria a conta automaticamente (tipo 'comum').
 *
 * A coluna senha recebe um hash aleatório: contas Google não usam senha
 * local (a coluna é NOT NULL no banco, então nunca fica vazia).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/google.php';

$erro = '';

// Usuário cancelou o consentimento na tela do Google
if (isset($_GET['error'])) {
    $erro = 'google_cancelado';
} elseif (!google_ativo()) {
    $erro = 'google_nao_configurado';
} elseif (!isset($_GET['code'], $_GET['state'])) {
    $erro = 'google_falha';
} elseif (!hash_equals($_SESSION['google_state'] ?? '', (string) $_GET['state'])) {
    // CSRF do OAuth: state divergente → requisição forjada/inválida
    $erro = 'google_falha';
} else {
    // 1) code -> access token
    $token = google_trocar_code_por_token((string) $_GET['code']);
    if ($token === null) {
        $erro = 'google_falha';
    } else {
        // 2) access token -> perfil
        $perfil = google_buscar_perfil($token['access_token']);
        if ($perfil === null) {
            $erro = 'google_falha';
        } elseif (empty($perfil['email_verified'])) {
            // O Google só entrega e-mails verificados; checado por garantia
            $erro = 'google_email_nao_verificado';
        } else {
            $googleId = (string) $perfil['sub'];
            $email    = mb_strtolower(trim((string) $perfil['email']));
            $nome     = trim((string) ($perfil['name'] ?? ''));
            if ($nome === '') {
                $nome = ucfirst(substr($email, 0, strpos($email, '@')));
            }
            $avatar = trim((string) ($perfil['picture'] ?? ''));

            // 3) usuário já vinculado a esta conta Google?
            $stmt = $pdo->prepare('SELECT * FROM usuario WHERE google_id = :gid');
            $stmt->bindValue(':gid', $googleId);
            $stmt->execute();
            $usuario = $stmt->fetch();

            // 4) e-mail já cadastrado pelo fluxo tradicional? vincula a conta Google
            if (!$usuario) {
                $stmt = $pdo->prepare('SELECT * FROM usuario WHERE email = :email');
                $stmt->bindValue(':email', $email);
                $stmt->execute();
                $usuario = $stmt->fetch();

                if ($usuario) {
                    $stmt = $pdo->prepare(
                        'UPDATE usuario
                            SET google_id = :gid, email_verificado = 1
                          WHERE id_usuario = :id'
                    );
                    $stmt->bindValue(':gid', $googleId);
                    $stmt->bindValue(':id', (int) $usuario['id_usuario'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }

            // 5) primeira vez? cria a conta automaticamente
            if (!$usuario) {
                // Hash aleatório: a senha local nunca é usada no fluxo Google
                $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                $sql = 'INSERT INTO usuario
                            (nome, email, senha, tipo_usuario, google_id, email_verificado, avatar)
                        VALUES (:nome, :email, :senha, :tipo, :gid, 1, :avatar)';
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':nome', $nome);
                $stmt->bindValue(':email', $email);
                $stmt->bindValue(':senha', $hash);
                $stmt->bindValue(':tipo', 'comum');
                $stmt->bindValue(':gid', $googleId);
                $stmt->bindValue(':avatar', $avatar);
                $stmt->execute();

                $idUsuario = (int) $pdo->lastInsertId();
                $tipo      = 'comum';
            } else {
                // Atualiza avatar/nome com o retorno mais recente do Google
                $idUsuario = (int) $usuario['id_usuario'];
                $tipo      = $usuario['tipo_usuario'];
                $nome      = $usuario['nome'];
            }

            // 6) abre a sessão igual ao login tradicional (anti-fixation incluído)
            session_regenerate_id(true);
            $_SESSION['id_usuario']      = $idUsuario;
            $_SESSION['nome_usuario']    = $nome;
            $_SESSION['tipo_usuario']    = $tipo;
            $_SESSION['sessao_iniciada'] = time();
            $_SESSION['ultimo_acesso']   = time();

            // state de OAuth é usado uma única vez
            unset($_SESSION['google_state']);

            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
    }
}

// Qualquer problema volta para a tela de login com a mensagem adequada
if ($erro !== '') {
    unset($_SESSION['google_state']);
    header('Location: ' . BASE_URL . 'pages/login.php?erro=' . urlencode($erro));
    exit;
}
