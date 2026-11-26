<?php
/**
 * MODELO dos dados do banco — copie para config/conexao_local.php
 * e preencha com o que a hospedagem mostrar no painel.
 *
 *   copy conexao_local.exemplo.php conexao_local.php
 *
 * O arquivo conexao_local.php fica fora do Git (.gitignore).
 * Sem ele, o sistema usa o banco local do XAMPP (root, sem senha).
 */

define('DB_HOST', 'localhost');        // às vezes é um endereço como mysql.seusite.com
define('DB_PORT', '3306');
define('DB_NAME', 'nome_do_banco');    // o painel costuma criar um nome com prefixo
define('DB_USER', 'usuario_do_banco');
define('DB_PASSWORD', 'senha_do_banco');
