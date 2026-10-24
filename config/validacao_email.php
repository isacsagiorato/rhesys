<?php
/**
 * Validação de e-mail "existente" no cadastro de usuários.
 *
 * Além do formato (filter_var), o cadastro só aceita e-mails cujo domínio
 * REALMENTE existe e é capaz de receber mensagens:
 *
 *   1) Bloqueia provedores descartáveis/temporários (10minutemail, mailinator...);
 *   2) Consulta o DNS: o domínio precisa ter registro MX (servidores de e-mail)
 *      ou, como fallback, um registro A/AAAA válido.
 *
 * Limitação honesta: a consulta DNS garante que o DOMÍNIO existe e aceita
 * e-mail, mas não prova que a caixa específica (parte antes do @) existe.
 * Provar 100% que a caixa existe exigiria enviar um e-mail de confirmação
 * (SMTP), o que requer um servidor de e-mail configurado. Com o MX verificado
 * + bloqueio de descartáveis, endereços totalmente inventados (domínio
 * inexistente) já são recusados no cadastro.
 */

// ------------------------------------------------------------
// Lista de provedores descartáveis conhecidos (bloqueados).
// Cadastrar com eles não faz sentido: a caixa deixa de existir em
// minutos e o usuário nunca poderia recuperar a conta.
// ------------------------------------------------------------
if (!defined('DOMINIOS_DESCARTAVEIS')) {
    define('DOMINIOS_DESCARTAVEIS', implode('|', [
        '10minutemail.com', '20minutemail.com', 'tempmail.com', 'temp-mail.org',
        'tempmail.net', 'tempmailo.com', 'guerrillamail.com', 'guerrillamail.net',
        'guerrillamail.org', 'sharklasers.com', 'grr.la', 'mailinator.com',
        'yopmail.com', 'yopmail.net', 'trashmail.com', 'throwawaymail.com',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
        'mohmal.com', 'emailondeck.com', 'mytemp.email', 'tempr.email',
    ]));
}

/**
 * O domínio do e-mail pertence a um provedor descartável/temporário?
 */
function email_dominio_descartavel($email)
{
    $pos = strrpos($email, '@');
    if ($pos === false) {
        return true; // sem domínio tratável
    }
    $dominio = mb_strtolower(substr($email, $pos + 1), 'UTF-8');

    return (bool) preg_match('/^(?:' . DOMINIOS_DESCARTAVEIS . ')$/i', $dominio);
}

/**
 * O domínio do e-mail existe no DNS (tem servidores de e-mail)?
 *
 * Checa o registro MX; se não houver, aceita registro A/AAAA como
 * fallback (alguns domínios pequenos usam A-record com MX implícito).
 */
function email_dominio_existe($email)
{
    $pos = strrpos($email, '@');
    if ($pos === false) {
        return false;
    }
    $dominio = strtolower(substr($email, $pos + 1));

    if (checkdnsrr($dominio, 'MX')) {
        return true;
    }

    return checkdnsrr($dominio, 'A') || checkdnsrr($dominio, 'AAAA');
}

/**
 * Validação completa do e-mail para cadastro:
 * formato + domínio não descartável + domínio existente (MX/A no DNS).
 *
 * @param string $email e-mail informado no formulário
 * @return string|null mensagem de erro, ou null quando o e-mail serve
 */
function validar_email_cadastro($email)
{
    $email = trim((string) $email);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Informe um e-mail válido.';
    }

    if (email_dominio_descartavel($email)) {
        return 'E-mails temporários/descartáveis não são aceitos. Use um e-mail permanente.';
    }

    if (!email_dominio_existe($email)) {
        $pos    = strrpos($email, '@');
        $dominio = substr($email, $pos + 1);
        return 'Este e-mail parece não existir: o domínio "' . $dominio
            . '" não foi encontrado no DNS. Confira se digitou o endereço corretamente.';
    }

    return null;
}
