<?php
/**
 * Monta o banco do Rhesys no Codespaces.
 *
 * Usa só o PHP, sem depender do programa de linha de comando do MySQL,
 * que nem sempre vem instalado na imagem. Funciona com mysqli ou,
 * se ele não existir, com pdo_mysql.
 */

$host  = getenv('RHESYS_DB_HOST') ?: 'banco';
$porta = (int) (getenv('RHESYS_DB_PORT') ?: 3306);
$banco = getenv('RHESYS_DB_NAME') ?: 'rhesys';
$usuarioSite = getenv('RHESYS_DB_USER') ?: 'rhesys';
$senhaRoot   = getenv('RHESYS_DB_ROOT_PASSWORD');
$senhaRoot   = $senhaRoot === false ? 'root' : $senhaRoot;

$temMysqli = extension_loaded('mysqli');
$temPdo    = extension_loaded('pdo_mysql');
if (!$temMysqli && !$temPdo) {
    fwrite(STDERR, "ERRO: o PHP está sem mysqli e sem pdo_mysql; não dá para falar com o banco.\n");
    exit(1);
}

/**
 * Espera o banco subir e devolve a conexão.
 * Devolve [tipo, conexão] — tipo é 'mysqli' ou 'pdo'.
 */
function conectar($host, $porta, $senha, $temMysqli)
{
    for ($i = 1; $i <= 60; $i++) {
        if ($temMysqli) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $c = @new mysqli($host, 'root', $senha, '', $porta);
            if (!$c->connect_errno) {
                $c->set_charset('utf8mb4');
                return ['mysqli', $c];
            }
        } else {
            try {
                $c = new PDO("mysql:host=$host;port=$porta;charset=utf8mb4", 'root', $senha, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => true, // permite vários comandos de uma vez
                ]);
                return ['pdo', $c];
            } catch (PDOException $e) {
                // ainda subindo
            }
        }
        if ($i === 1) echo "      esperando o banco subir";
        echo '.';
        sleep(2);
    }
    fwrite(STDERR, "\nERRO: o banco não respondeu a tempo.\n");
    exit(1);
}

/** Executa um arquivo .sql inteiro. */
function rodar($tipo, $conexao, $sql, $arquivo)
{
    if ($tipo === 'mysqli') {
        if (!$conexao->multi_query($sql)) {
            fwrite(STDERR, "ERRO em $arquivo: {$conexao->error}\n");
            exit(1);
        }
        do {
            if ($r = $conexao->store_result()) $r->free();
        } while ($conexao->more_results() && $conexao->next_result());
        if ($conexao->error) {
            fwrite(STDERR, "ERRO em $arquivo: {$conexao->error}\n");
            exit(1);
        }
        return;
    }
    try {
        $conexao->exec($sql);
    } catch (PDOException $e) {
        fwrite(STDERR, "ERRO em $arquivo: " . $e->getMessage() . "\n");
        exit(1);
    }
}

/** Consulta que devolve um número. */
function numero($tipo, $conexao, $sql)
{
    return $tipo === 'mysqli'
        ? $conexao->query($sql)->fetch_row()[0]
        : $conexao->query($sql)->fetchColumn();
}

[$tipo, $conexao] = conectar($host, $porta, $senhaRoot, $temMysqli);
echo "\n      banco respondeu (usando $tipo)\n";

// Mesma ordem usada nos testes
$arquivos = [
    'rhesys_banco_dados.sql',
    'residuos_organicos.sql',
    'residuos_lote2.sql',
    'residuos_lote3.sql',
    'residuos_lote4.sql',
    'palavras_chave_ampliadas.sql',
    'residuos_lote5.sql',
    'residuos_lote6.sql',
    'quizzes_lote2.sql',
];

$raiz = dirname(__DIR__);
foreach ($arquivos as $arquivo) {
    $caminho = $raiz . '/database/' . $arquivo;
    if (!is_file($caminho)) {
        fwrite(STDERR, "ERRO: não encontrei database/$arquivo\n");
        exit(1);
    }
    echo "      - $arquivo\n";
    $sql = file_get_contents($caminho);
    if ($banco !== 'rhesys') {
        $sql = str_replace(
            ['CREATE DATABASE IF NOT EXISTS rhesys', 'USE rhesys;'],
            ["CREATE DATABASE IF NOT EXISTS `$banco`", "USE `$banco`;"],
            $sql
        );
    }
    rodar($tipo, $conexao, $sql, $arquivo);
}

// O usuário que o site usa precisa de acesso ao banco
rodar($tipo, $conexao, "GRANT ALL PRIVILEGES ON `$banco`.* TO '$usuarioSite'@'%'; FLUSH PRIVILEGES;", 'permissões');

$total   = numero($tipo, $conexao, "SELECT COUNT(*) FROM `$banco`.residuo");
$quizzes = numero($tipo, $conexao, "SELECT COUNT(*) FROM `$banco`.quiz");
echo "      $total resíduos e $quizzes quizzes cadastrados\n";
