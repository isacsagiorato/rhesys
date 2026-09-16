<?php
/**
 * Configurações gerais do sistema.
 */

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('NOME_SITE', 'Rhesys');
define('BASE_URL', '/rhesys/'); // ajuste conforme a pasta do seu servidor local
