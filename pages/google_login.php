<?php
/**
 * Início do login com Google: redireciona o navegador para a tela de
 * consentimento do Google (OAuth 2.0).
 *
 * O "state" (CSRF do OAuth) é gerado aqui (ver config/google.php) e
 * conferido no callback (pages/google_callback.php) com hash_equals().
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google.php';

// Se já está logado, não há o que fazer
if (usuario_logado()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Sem credenciais configuradas, volta para o login com aviso
if (!google_ativo()) {
    header('Location: ' . BASE_URL . 'pages/login.php?erro=google_nao_configurado');
    exit;
}

header('Location: ' . google_url_autorizacao());
exit;
