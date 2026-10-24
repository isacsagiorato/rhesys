<?php
/**
 * Login com Google — OAuth 2.0 em PHP puro (sem bibliotecas externas).
 *
 * COMO CONFIGURAR (uma vez só):
 *   1. Acesse https://console.cloud.google.com/apis/credentials
 *   2. No menu "APIs e Serviços" > "Tela de permissão OAuth":
 *      escolha o tipo "Externo", informe o nome do app (Rhesys) e um
 *      e-mail de suporte. Não precisa adicionar escopos sensíveis.
 *   3. Em "Credenciais" > "Criar credenciais" > "ID do cliente OAuth",
 *      tipo "Aplicativo da Web".
 *   4. Em "URIs de redirecionamento JavaScript e HTTP autorizadas",
 *      adicione exatamente (ajuste a porta conforme seu servidor):
 *        http://localhost:8000/pages/google_callback.php
 *   5. Copie o "ID do cliente" e a "Chave do cliente" e cole abaixo em
 *      GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.
 *
 * Enquanto as constantes estiverem vazias, o clique no botão "Entrar com
 * Google" leva de volta ao login com um aviso (google_ativo() = false).
 */

// ============================================================
// CREDENCIAIS LOCAIS — NÃO VERSIONADAS NO GIT
// ------------------------------------------------------------
// As chaves reais ficam em config/google_local.php (fora do Git,
// listado no .gitignore). Copie config/google_local.exemplo.php
// para config/google_local.php e preencha com suas chaves.
// ============================================================
if (file_exists(__DIR__ . '/google_local.php')) {
    require_once __DIR__ . '/google_local.php';
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '');      // preencha em config/google_local.php
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', '');  // preencha em config/google_local.php
}

// Endpoints fixos do Google (developers.google.com/identity/protocols/oauth2)
define('GOOGLE_URL_AUTORIZACAO', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_URL_TOKEN',       'https://oauth2.googleapis.com/token');
define('GOOGLE_URL_USUARIO',     'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * O login com Google está configurado neste servidor?
 */
function google_ativo()
{
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

/**
 * URI de redirecionamento cadastrada no console do Google.
 * Derivada de BASE_URL (igual ao esquema de config.php), então funciona
 * em qualquer pasta/servidor sem precisar ser editada.
 */
function google_redirect_uri()
{
    $https   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $esquema = $https ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $esquema . '://' . $host . BASE_URL . 'pages/google_callback.php';
}

/**
 * Gera (ou reaproveita) o "state" — proteção CSRF do fluxo OAuth.
 * Guardado na sessão e conferido no callback com hash_equals().
 */
function google_state()
{
    if (empty($_SESSION['google_state'])) {
        $_SESSION['google_state'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['google_state'];
}

/**
 * URL da tela de consentimento do Google (início do fluxo).
 */
function google_url_autorizacao()
{
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => google_state(),
        'prompt'        => 'select_account',
    ]);

    return GOOGLE_URL_AUTORIZACAO . '?' . $params;
}


/**
 * POST HTTP genérico (cURL se disponível; senão stream context).
 *
 * @return array|null resposta JSON decodificada, ou null em falha
 */
function google_http_post_json($url, array $dados)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($dados),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resposta = curl_exec($ch);
        $erro     = curl_errno($ch);
        curl_close($ch);
        if ($erro || $resposta === false) {
            return null;
        }
    } else {
        $contexto = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($dados),
            'timeout' => 15,
        ]]);
        $resposta = @file_get_contents($url, false, $contexto);
        if ($resposta === false) {
            return null;
        }
    }

    $json = json_decode((string) $resposta, true);

    return is_array($json) ? $json : null;
}

/**
 * GET HTTP com Authorization: Bearer (busca os dados do perfil).
 *
 * @return array|null perfil decodificado, ou null em falha
 */
function google_http_get_json($url, $accessToken)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        ]);
        $resposta = curl_exec($ch);
        $erro     = curl_errno($ch);
        curl_close($ch);
        if ($erro || $resposta === false) {
            return null;
        }
    } else {
        $contexto = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $accessToken\r\n",
            'timeout' => 15,
        ]]);
        $resposta = @file_get_contents($url, false, $contexto);
        if ($resposta === false) {
            return null;
        }
    }

    $json = json_decode((string) $resposta, true);

    return is_array($json) ? $json : null;
}

/**
 * Troca o código de autorização pelo access token.
 *
 * @return array|null resposta com access_token, ou null em falha
 */
function google_trocar_code_por_token($code)
{
    $resposta = google_http_post_json(GOOGLE_URL_TOKEN, [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    if ($resposta === null || empty($resposta['access_token'])) {
        return null;
    }

    return $resposta;
}

/**
 * Busca o perfil do usuário logado
 * (sub, email, email_verified, name, picture).
 *
 * @return array|null perfil, ou null em falha
 */
function google_buscar_perfil($accessToken)
{
    $perfil = google_http_get_json(GOOGLE_URL_USUARIO, $accessToken);

    if ($perfil === null || empty($perfil['sub']) || empty($perfil['email'])) {
        return null;
    }

    return $perfil;
}
