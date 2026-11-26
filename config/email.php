<?php
/**
 * Envio de e-mails do Rhesys (notificações).
 *
 * Usa SMTP direto (sem bibliotecas externas), com suporte a STARTTLS
 * (porta 587) e SSL (porta 465) — funciona com Gmail, Outlook, Hostinger etc.
 *
 * Configuração: copie config/email_local.exemplo.php para
 * config/email_local.php e preencha os dados do seu servidor de e-mail.
 * Esse arquivo fica fora do Git (.gitignore).
 *
 * Sem configuração (ex.: rodando localmente), os e-mails NÃO são perdidos:
 * cada mensagem é salva como arquivo .eml em storage/emails/, que pode ser
 * aberto no Outlook/Thunderbird ou no navegador para conferir o conteúdo.
 */

if (is_file(__DIR__ . '/email_local.php')) {
    require_once __DIR__ . '/email_local.php';
}

/** Existe um servidor SMTP configurado? */
function email_configurado()
{
    return defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USUARIO') && SMTP_USUARIO !== '';
}

/** Endereço público do site (para os links dentro do e-mail). */
function email_url_site()
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/') . '/';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . (defined('BASE_URL') ? BASE_URL : '/');
}

/** Codifica um cabeçalho com acentos (RFC 2047). */
function email_cabecalho($texto)
{
    return preg_match('/[^\x20-\x7E]/', $texto) ? '=?UTF-8?B?' . base64_encode($texto) . '?=' : $texto;
}

/**
 * Monta a mensagem MIME completa (texto + HTML).
 */
function email_montar($para, $nome_para, $assunto, $html, $texto)
{
    $remetente = defined('EMAIL_REMETENTE') && EMAIL_REMETENTE !== '' ? EMAIL_REMETENTE : (defined('SMTP_USUARIO') ? SMTP_USUARIO : 'nao-responda@rhesys.local');
    $nome_remetente = defined('EMAIL_NOME') && EMAIL_NOME !== '' ? EMAIL_NOME : 'Rhesys';
    $fronteira = 'rhesys-' . bin2hex(random_bytes(12));
    $dominio = substr(strrchr($remetente, '@') ?: '@rhesys.local', 1);

    $cab = [
        'Date: ' . date('r'),
        'From: ' . email_cabecalho($nome_remetente) . ' <' . $remetente . '>',
        'To: ' . ($nome_para !== '' ? email_cabecalho($nome_para) . ' ' : '') . '<' . $para . '>',
        'Subject: ' . email_cabecalho($assunto),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $fronteira . '"',
        'X-Mailer: Rhesys',
    ];
    $corpo = '--' . $fronteira . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto)) . "\r\n"
        . '--' . $fronteira . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . '--' . $fronteira . "--\r\n";

    return [$remetente, implode("\r\n", $cab) . "\r\n\r\n" . $corpo];
}

/**
 * Envia um e-mail. Devolve true em caso de sucesso.
 * Nunca lança exceção: falhas são registradas no log do PHP.
 */
function enviar_email($para, $nome_para, $assunto, $html, $texto)
{
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [$remetente, $mensagem] = email_montar($para, (string) $nome_para, $assunto, $html, $texto);

    if (!email_configurado()) {
        return email_salvar_local($para, $mensagem);
    }
    try {
        email_smtp($remetente, $para, $mensagem);
        return true;
    } catch (Throwable $e) {
        error_log('Rhesys: falha ao enviar e-mail para ' . $para . ': ' . $e->getMessage());
        return false;
    }
}

/** Sem SMTP: guarda a mensagem em storage/emails/ para conferência. */
function email_salvar_local($para, $mensagem)
{
    $pasta = dirname(__DIR__) . '/storage/emails';
    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
        return false;
    }
    $arquivo = $pasta . '/' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9]+/i', '_', $para) . '-' . bin2hex(random_bytes(3)) . '.eml';
    return file_put_contents($arquivo, $mensagem) !== false;
}

/**
 * Conversa SMTP mínima: EHLO, STARTTLS/SSL, AUTH LOGIN, MAIL, RCPT, DATA.
 */
function email_smtp($remetente, $para, $mensagem)
{
    $seguranca = defined('SMTP_SEGURANCA') ? strtolower(SMTP_SEGURANCA) : 'tls';
    $porta = defined('SMTP_PORTA') ? (int) SMTP_PORTA : ($seguranca === 'ssl' ? 465 : 587);
    $endereco = ($seguranca === 'ssl' ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . $porta;

    $conexao = @stream_socket_client($endereco, $errno, $errstr, 15, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$conexao) {
        throw new RuntimeException("Não conectou em $endereco: $errstr ($errno)");
    }
    stream_set_timeout($conexao, 20);

    $ler = function () use ($conexao) {
        $resposta = '';
        while (($linha = fgets($conexao, 1024)) !== false) {
            $resposta .= $linha;
            if (strlen($linha) < 4 || $linha[3] === ' ') break;
        }
        return $resposta;
    };
    $comando = function ($linha, $esperado) use ($conexao, $ler) {
        if ($linha !== null) fwrite($conexao, $linha . "\r\n");
        $resposta = $ler();
        if (!in_array((int) substr($resposta, 0, 3), (array) $esperado, true)) {
            throw new RuntimeException('SMTP recusou "' . strtok((string) $linha, ' ') . '": ' . trim($resposta));
        }
        return $resposta;
    };

    $ehlo = 'EHLO ' . (gethostname() ?: 'rhesys');
    $comando(null, 220);
    $comando($ehlo, 250);
    if ($seguranca === 'tls') {
        $comando('STARTTLS', 220);
        if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('Falha ao iniciar TLS');
        }
        $comando($ehlo, 250);
    }
    $comando('AUTH LOGIN', 334);
    $comando(base64_encode(SMTP_USUARIO), 334);
    $comando(base64_encode(defined('SMTP_SENHA') ? SMTP_SENHA : ''), 235);
    $comando('MAIL FROM:<' . $remetente . '>', 250);
    $comando('RCPT TO:<' . $para . '>', [250, 251]);
    $comando('DATA', 354);
    // Linhas que começam com "." precisam de um ponto extra (dot-stuffing)
    $comando(preg_replace('/^\./m', '..', $mensagem) . "\r\n.", 250);
    $comando('QUIT', 221);
    fclose($conexao);
}
