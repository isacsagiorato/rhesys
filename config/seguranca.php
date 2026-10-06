<?php
/**
 * Funções de segurança compartilhadas pelo sistema.
 *
 * 1) CSRF — token único por sessão, exigido em todo formulário POST
 *    (login, cadastro e quiz) para impedir requisições forjadas.
 *
 * 2) Rate limiting — controla tentativas de login na tabela
 *    `tentativa_login`. Após LOGIN_MAX_TENTATIVAS falhas seguidas
 *    (por IP + e-mail), o acesso é bloqueado temporariamente por
 *    LOGIN_BLOQUEIO_MINUTOS minutos.
 */

// Número máximo de tentativas de login com falha antes do bloqueio
if (!defined('LOGIN_MAX_TENTATIVAS')) {
    define('LOGIN_MAX_TENTATIVAS', 5);
}

// Duração do bloqueio temporário, em minutos
if (!defined('LOGIN_BLOQUEIO_MINUTOS')) {
    define('LOGIN_BLOQUEIO_MINUTOS', 15);
}

// ============================================================
// CSRF
// ============================================================

/**
 * Retorna o token CSRF da sessão atual (gera um novo se não existir).
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo hidden com o token CSRF — usar dentro dos <form method="post">.
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Confere o token enviado no POST contra o da sessão (comparação segura).
 */
function verificar_csrf()
{
    $enviado = $_POST['csrf_token'] ?? '';

    return is_string($enviado)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $enviado);
}

// ============================================================
// Rate limiting de login
// ============================================================

// ATENÇÃO: datas/horas são sempre comparadas com o relógio do MySQL
// (NOW()), nunca com time()/date() do PHP — os dois ambientes podem
// estar em fusos diferentes, o que quebraria a contagem/bloqueio.

/**
 * Chave do controle de tentativas: hash SHA-256 de IP + e-mail.
 * Assim o banco não guarda dados sensíveis em texto puro.
 */
function tentativa_chave($email)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    return hash('sha256', $ip . '|' . mb_strtolower(trim($email)));
}

/**
 * Retorna os segundos restantes de bloqueio (0 = não bloqueado).
 * Tudo calculado com o relógio do MySQL (NOW()).
 */
function login_bloqueado(PDO $pdo, $chave)
{
    $sql = 'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), bloqueado_ate)) AS restante
            FROM tentativa_login
            WHERE chave_tentativa = :chave
              AND bloqueado_ate IS NOT NULL
              AND bloqueado_ate > NOW()';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':chave', $chave);
    $stmt->execute();
    $registro = $stmt->fetch();

    return $registro ? (int) $registro['restante'] : 0;
}

/**
 * Registra uma tentativa de login com falha.
 * Zera a contagem se a anterior expirou; bloqueia ao atingir o limite.
 *
 * @return int tentativas restantes antes do bloqueio (0 = bloqueado)
 */
function login_registrar_falha(PDO $pdo, $chave)
{
    $janelaSeg = (int) (LOGIN_BLOQUEIO_MINUTOS * 60);

    // Estado atual, avaliado com o relógio do MySQL
    $sql = "SELECT tentativas,
                   (bloqueado_ate IS NOT NULL AND bloqueado_ate <= NOW()) AS bloqueio_vencido,
                   (atualizado_em > NOW() - INTERVAL $janelaSeg SECOND) AS janela_valida
            FROM tentativa_login
            WHERE chave_tentativa = :chave";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':chave', $chave);
    $stmt->execute();
    $registro = $stmt->fetch();

    if ($registro) {
        // Depois de um bloqueio vencido ou de muito tempo sem falhas,
        // a contagem recomeça do zero nesta tentativa.
        $reiniciar = !empty($registro['bloqueio_vencido']) || empty($registro['janela_valida']);
        $tentativas = $reiniciar ? 1 : (int) $registro['tentativas'] + 1;
    } else {
        $tentativas = 1;
    }

    // Upsert com bloqueio calculado pelo relógio do MySQL
    if ($tentativas >= LOGIN_MAX_TENTATIVAS) {
        $min = (int) LOGIN_BLOQUEIO_MINUTOS;
        $sql = "INSERT INTO tentativa_login (chave_tentativa, tentativas, bloqueado_ate)
                VALUES (:chave, :tentativas, DATE_ADD(NOW(), INTERVAL $min MINUTE))
                ON DUPLICATE KEY UPDATE
                    tentativas = VALUES(tentativas),
                    bloqueado_ate = VALUES(bloqueado_ate)";
    } else {
        $sql = 'INSERT INTO tentativa_login (chave_tentativa, tentativas, bloqueado_ate)
                VALUES (:chave, :tentativas, NULL)
                ON DUPLICATE KEY UPDATE
                    tentativas = VALUES(tentativas),
                    bloqueado_ate = VALUES(bloqueado_ate)';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':chave', $chave);
    $stmt->bindValue(':tentativas', $tentativas, PDO::PARAM_INT);
    $stmt->execute();

    return max(0, LOGIN_MAX_TENTATIVAS - $tentativas);
}

/**
 * Limpa o contador de tentativas após um login bem-sucedido.
 */
function login_limpar(PDO $pdo, $chave)
{
    $stmt = $pdo->prepare('DELETE FROM tentativa_login WHERE chave_tentativa = :chave');
    $stmt->bindValue(':chave', $chave);
    $stmt->execute();
}