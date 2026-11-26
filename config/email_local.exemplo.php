<?php
/**
 * MODELO de configuração de e-mail — copie para config/email_local.php
 * e preencha com os dados do SEU servidor de e-mail.
 *
 *   copy email_local.exemplo.php email_local.php
 *
 * O arquivo email_local.php fica fora do Git (.gitignore).
 *
 * Exemplo com Gmail: ative a verificação em duas etapas na conta Google e
 * crie uma "senha de app" em https://myaccount.google.com/apppasswords
 * (use essa senha de 16 letras abaixo, não a senha normal da conta).
 *
 * Enquanto este arquivo não existir, os e-mails são salvos em
 * storage/emails/ (arquivos .eml) em vez de serem enviados.
 */

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORTA', 587);
define('SMTP_SEGURANCA', 'tls');          // 'tls' (porta 587) ou 'ssl' (porta 465)
define('SMTP_USUARIO', 'seu.email@gmail.com');
define('SMTP_SENHA', 'sua senha de app');

define('EMAIL_REMETENTE', 'seu.email@gmail.com');
define('EMAIL_NOME', 'Rhesys');

// Endereço público do site, usado nos links dos e-mails.
// Deixe vazio para detectar automaticamente (ex.: http://localhost/rhesys/).
define('SITE_URL', '');
