<?php
/**
 * Configurações gerais do sistema.
 */

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('NOME_SITE', 'Rhesys');
define('BASE_URL', '/'); // '/' para php -S localhost:8000 na raiz do projeto; use '/rhesys/' se rodar via XAMPP (htdocs/rhesys)
