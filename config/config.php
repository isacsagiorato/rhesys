<?php
/**
 * Configurações gerais do sistema + controle de sessão ($_SESSION).
 */

// ============================================================
// Controle de sessão — mantém o usuário logado entre páginas
// (deve rodar ANTES do session_start)
// ============================================================
ini_set('session.use_strict_mode', '1');   // rejeita IDs de sessão não gerados pelo servidor
ini_set('session.use_only_cookies', '1');  // nunca expõe o ID da sessão na URL
ini_set('session.cookie_httponly', '1');   // cookie inacessível via JavaScript
ini_set('session.cookie_samesite', 'Lax'); // cookie só acompanha navegação same-site
ini_set('session.cookie_lifetime', '0');   // cookie vale até fechar o navegador

// Sessão expira após 8 horas sem nenhuma visita a uma página
// (a cada página aberta o relógio de inatividade é renovado)
define('SESSAO_INATIVIDADE', 8 * 3600);

// Sessão expira no máximo 24 horas após o login,
// mesmo que o usuário continue ativo (limite absoluto)
define('SESSAO_TEMPO_MAXIMO', 24 * 3600);

define('NOME_SITE', 'Rhesys');

// BASE_URL detectada automaticamente a partir do DOCUMENT_ROOT:
// - XAMPP (C:/xampp/htdocs/rhesys)  -> '/rhesys/'
// - php -S localhost:8000 na raiz   -> '/'
// Assim nunca mais é preciso editar este valor ao copiar o projeto.
// (definida ANTES de qualquer função/bloco que a utilize)
$raizProjeto = str_replace('\\', '/', dirname(__DIR__));
$raizServidor = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
$baseUrl = '/';
if ($raizServidor !== ''
    && strlen($raizProjeto) > strlen($raizServidor)
    && stripos($raizProjeto, $raizServidor) === 0
) {
    $baseUrl = substr($raizProjeto, strlen($raizServidor)) . '/';
}
define('BASE_URL', $baseUrl);

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Existe um usuário autenticado na sessão atual?
 */
function usuario_logado()
{
    return isset($_SESSION['id_usuario']);
}

/**
 * Encerra APENAS o login (mantém a sessão e o token CSRF vivos).
 * Usado quando a sessão expira; o logout total fica em pages/logout.php.
 */
function encerrar_login()
{
    unset(
        $_SESSION['id_usuario'],
        $_SESSION['nome_usuario'],
        $_SESSION['tipo_usuario'],
        $_SESSION['sessao_iniciada'],
        $_SESSION['ultimo_acesso']
    );
}

/**
 * Exige login na página atual: sem sessão, volta para o login.
 * Usar logo após o require de config.php em páginas restritas.
 */
function exige_login()
{
    if (!usuario_logado()) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit;
    }
}

// Expiração da sessão: inatividade ou tempo máximo desde o login
if (usuario_logado()) {
    $agora     = time();
    $ultimo    = (int) ($_SESSION['ultimo_acesso'] ?? $agora);
    $iniciada  = (int) ($_SESSION['sessao_iniciada'] ?? $agora);

    if (($agora - $ultimo) > SESSAO_INATIVIDADE
        || ($agora - $iniciada) > SESSAO_TEMPO_MAXIMO
    ) {
        encerrar_login();

        // Avisa quem estava apenas navegando (GET); em POST segue o fluxo
        $paginaAtual = basename($_SERVER['PHP_SELF'] ?? '');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $paginaAtual !== 'logout.php') {
            header('Location: ' . BASE_URL . 'pages/login.php?expirada=1');
            exit;
        }
    } else {
        // Renova a janela de inatividade a cada página visitada
        $_SESSION['ultimo_acesso'] = $agora;
    }
}
