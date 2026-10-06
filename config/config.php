<?php
/**
 * Configurações gerais do sistema.
 */

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('NOME_SITE', 'Rhesys');

// BASE_URL detectada automaticamente a partir do DOCUMENT_ROOT:
// - XAMPP (C:/xampp/htdocs/rhesys)  -> '/rhesys/'
// - php -S localhost:8000 na raiz   -> '/'
// Assim nunca mais é preciso editar este valor ao copiar o projeto.
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
