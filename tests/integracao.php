<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dbName = getenv('RHESYS_TEST_DB_NAME') ?: 'rhesys_test';
if (!preg_match('/^[A-Za-z0-9_]+_test$/', $dbName)) {
    throw new RuntimeException('Por segurança, o banco de testes deve terminar em _test.');
}
$dbHost = getenv('RHESYS_DB_HOST') ?: 'localhost';
$dbPort = getenv('RHESYS_DB_PORT') ?: '3306';
$dbUser = getenv('RHESYS_DB_USER') ?: 'root';
$dbPassword = getenv('RHESYS_DB_PASSWORD') ?: '';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$adminPdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPassword, $options);
$adminPdo->exec("DROP DATABASE IF EXISTS `$dbName`");
$adminPdo->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$sql = file_get_contents($root . '/database/rhesys_banco_dados.sql');
$sql = str_replace('CREATE DATABASE IF NOT EXISTS rhesys', "CREATE DATABASE IF NOT EXISTS `$dbName`", $sql, $createdReplaced);
$sql = str_replace('USE rhesys;', "USE `$dbName`;", $sql, $useReplaced);
if ($createdReplaced !== 1 || $useReplaced !== 1 || strpos($sql, 'USE rhesys;') !== false) {
    throw new RuntimeException('Não foi possível isolar o SQL no banco de testes. Execução cancelada.');
}
$mysqli = new mysqli($dbHost, $dbUser, $dbPassword, '', (int) $dbPort);
$mysqli->set_charset('utf8mb4');
if (!$mysqli->multi_query($sql)) throw new RuntimeException('Falha ao iniciar o SQL de testes: ' . $mysqli->error);
do {
    if ($result = $mysqli->store_result()) $result->free();
    if (!$mysqli->more_results()) break;
    if (!$mysqli->next_result()) throw new RuntimeException('Falha no SQL de testes: ' . $mysqli->error);
} while (true);
$mysqli->close();
$pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPassword, $options);

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException("Não foi possível reservar uma porta: $error");
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$base = 'http://127.0.0.1:' . $port;
$log = sys_get_temp_dir() . '/rhesys_test_server_' . $port . '.log';
$env = array_merge(getenv(), ['RHESYS_DB_HOST' => $dbHost, 'RHESYS_DB_PORT' => $dbPort, 'RHESYS_DB_NAME' => $dbName, 'RHESYS_DB_USER' => $dbUser, 'RHESYS_DB_PASSWORD' => $dbPassword]);
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o servidor PHP de testes.');
fclose($pipes[0]);
$serverRunning = true;
register_shutdown_function(function () use (&$process, &$serverRunning) {
    if ($serverRunning && is_resource($process)) proc_terminate($process);
});
$ready = false;
for ($i = 0; $i < 50; $i++) {
    usleep(100000);
    $response = @file_get_contents($base . '/index.php');
    if ($response !== false) { $ready = true; break; }
}
if (!$ready) throw new RuntimeException("Servidor de testes não respondeu. Log: $log");

$tempDir = sys_get_temp_dir() . '/rhesys_http_' . bin2hex(random_bytes(5));
mkdir($tempDir, 0700, true);
$clients = [];
foreach (['publico', 'interessado', 'admin'] as $name) $clients[$name] = $tempDir . '/' . $name . '.cookie';
$checks = 0;

function request(string $base, string $jar, string $method, string $path, array $fields = []): array {
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'RhesysIntegrationTests/1.0']);
    if ($method === 'POST') {
        $postFields = [];
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) $postFields[$key . '[' . $subkey . ']'] = $subvalue;
            } else {
                $postFields[$key] = $value;
            }
        }
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
    }
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException('Erro HTTP: ' . curl_error($curl));
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    curl_close($curl);
    preg_match_all('/^Location:\s*(.+)$/mi', $headers, $matches);
    $location = $matches[1] ? trim(end($matches[1])) : '';
    return ['status' => $status, 'body' => $body, 'location' => $location, 'headers' => $headers];
}
function csrf(array $response): string {
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $response['body'], $match)) throw new RuntimeException('Token CSRF não encontrado na resposta.');
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}
function verify(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException('FALHA: ' . $message);
    echo "[OK] $message\n";
}
function contains(string $needle, array $response, string $message): void {
    verify(strpos($response['body'], $needle) !== false, $message);
}
function scalar(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

try {
    $response = request($base, $clients['publico'], 'GET', '/index.php');
    verify($response['status'] === 200, 'página inicial responde com HTTP 200');
    $response = request($base, $clients['publico'], 'GET', '/pages/compartilhamentos.php');
    verify($response['status'] === 302 && strpos($response['location'], 'login.php') !== false, 'compartilhamento exige autenticação');

    $response = request($base, $clients['interessado'], 'GET', '/pages/cadastro.php');
    $response = request($base, $clients['interessado'], 'POST', '/pages/cadastro.php', [
        'csrf_token' => csrf($response), 'nome' => 'Usuário de Integração',
        'email' => 'interessado.rhesys@gmail.com', 'senha' => 'teste123', 'confirmar_senha' => 'teste123',
    ]);
    verify($response['status'] === 302, 'cadastro de usuário concluído');
    $idInteressado = (int) scalar($pdo, 'SELECT id_usuario FROM usuario WHERE email = ?', ['interessado.rhesys@gmail.com']);
    verify($idInteressado > 0, 'usuário cadastrado foi persistido');
    $hash = scalar($pdo, 'SELECT senha FROM usuario WHERE id_usuario = ?', [$idInteressado]);
    verify(is_string($hash) && password_verify('teste123', $hash), 'senha armazenada como hash');

    $response = request($base, $clients['admin'], 'GET', '/pages/login.php');
    $response = request($base, $clients['admin'], 'POST', '/pages/login.php', [
        'csrf_token' => csrf($response), 'email' => 'admin@rhesys.com', 'senha' => 'admin123',
    ]);
    verify($response['status'] === 302, 'login do ofertante concluído');
    $response = request($base, $clients['publico'], 'GET', '/pages/residuos.php?busca=pilha');
    contains('Pilha', $response, 'busca de resíduo retorna o item esperado');

    $response = request($base, $clients['admin'], 'GET', '/pages/cadastro_compartilhamento.php');
    $tokenAdmin = csrf($response);
    $dadosOferta = ['csrf_token' => $tokenAdmin, 'id_residuo' => 1, 'descricao' => 'Item em ótimo estado para retirada. <script>alert(1)</script>'];
    $invalid = $dadosOferta;
    $invalid['csrf_token'] = 'token-invalido';
    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_compartilhamento.php', $invalid);
    contains('formulário inválido', $response, 'CSRF inválido é recusado');
    verify((int) scalar($pdo, 'SELECT COUNT(*) FROM compartilhamento_residuo') === 0, 'requisição sem CSRF não grava dados');
    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_compartilhamento.php', $dadosOferta);
    verify($response['status'] === 302, 'oferta criada pelo usuário autenticado');
    $idOferta = (int) scalar($pdo, 'SELECT MAX(id_compartilhamento) FROM compartilhamento_residuo');
    verify($idOferta > 0, 'oferta persistida no banco');

    $response = request($base, $clients['admin'], 'GET', '/pages/compartilhamentos.php?status=todos');
    contains('&lt;script&gt;alert(1)&lt;/script&gt;', $response, 'descrição da oferta escapa XSS');
    $response = request($base, $clients['admin'], 'POST', '/pages/compartilhamentos.php', ['csrf_token' => $tokenAdmin, 'acao' => 'demonstrar_interesse', 'id_compartilhamento' => $idOferta]);
    contains('própria oferta', $response, 'auto-interesse é bloqueado');

    $response = request($base, $clients['interessado'], 'GET', '/pages/compartilhamentos.php?status=todos');
    $tokenInteressado = csrf($response);
    $interesseData = ['csrf_token' => $tokenInteressado, 'acao' => 'demonstrar_interesse', 'id_compartilhamento' => $idOferta];
    $response = request($base, $clients['interessado'], 'POST', '/pages/compartilhamentos.php', $interesseData);
    verify($response['status'] === 302, 'interesse registrado por outro usuário');
    $idInteresse = (int) scalar($pdo, 'SELECT id_interesse FROM interesse_compartilhamento WHERE id_compartilhamento = ? AND id_usuario = ?', [$idOferta, $idInteressado]);
    verify($idInteresse > 0, 'interesse persistido no banco');
    $response = request($base, $clients['interessado'], 'POST', '/pages/compartilhamentos.php', $interesseData);
    contains('já registrou interesse', $response, 'interesse duplicado é bloqueado');
    verify((int) scalar($pdo, 'SELECT COUNT(*) FROM interesse_compartilhamento WHERE id_compartilhamento = ?', [$idOferta]) === 1, 'duplicidade não gera nova linha');

    $response = request($base, $clients['interessado'], 'POST', '/pages/meus_compartilhamentos.php', ['csrf_token' => $tokenInteressado, 'acao' => 'aceitar_interesse', 'id_compartilhamento' => $idOferta, 'id_interesse' => $idInteresse]);
    contains('sem permissão', $response, 'interessado não gerencia oferta alheia');
    verify(scalar($pdo, 'SELECT status FROM compartilhamento_residuo WHERE id_compartilhamento = ?', [$idOferta]) === 'disponivel', 'tentativa sem permissão não altera status');

    $response = request($base, $clients['admin'], 'GET', '/pages/meus_compartilhamentos.php');
    $tokenAdmin = csrf($response);
    $response = request($base, $clients['admin'], 'POST', '/pages/meus_compartilhamentos.php', ['csrf_token' => $tokenAdmin, 'acao' => 'aceitar_interesse', 'id_compartilhamento' => $idOferta, 'id_interesse' => $idInteresse]);
    verify($response['status'] === 302, 'ofertante aceita o interesse');
    verify(scalar($pdo, 'SELECT status FROM compartilhamento_residuo WHERE id_compartilhamento = ?', [$idOferta]) === 'reservado', 'aceite reserva a oferta');
    verify(scalar($pdo, 'SELECT status FROM interesse_compartilhamento WHERE id_interesse = ?', [$idInteresse]) === 'aceito', 'interesse fica aceito');
    $response = request($base, $clients['admin'], 'POST', '/pages/meus_compartilhamentos.php', ['csrf_token' => $tokenAdmin, 'acao' => 'concluir_oferta', 'id_compartilhamento' => $idOferta]);
    verify($response['status'] === 302 && scalar($pdo, 'SELECT status FROM compartilhamento_residuo WHERE id_compartilhamento = ?', [$idOferta]) === 'concluido', 'ofertante conclui o compartilhamento');

    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_ponto_coleta.php', [
        'csrf_token' => $tokenAdmin, 'id_ponto' => 0, 'nome' => 'Ponto Automatizado Rhesys',
        'endereco' => 'Rua dos Testes, 123 - Centro', 'latitude' => '-21.9700000', 'longitude' => '-46.7900000',
        'telefone' => '(19) 99999-0000', 'horario_funcionamento' => 'Seg-Sex 08:00-17:00', 'residuos' => [1, 2],
    ]);
    verify($response['status'] === 302, 'ponto de coleta criado com vínculos N:N');
    $idPonto = (int) scalar($pdo, 'SELECT MAX(id_ponto) FROM ponto_coleta');
    verify((int) scalar($pdo, 'SELECT COUNT(*) FROM residuo_ponto_coleta WHERE id_ponto = ?', [$idPonto]) === 2, 'dois resíduos vinculados ao ponto');
    $response = request($base, $clients['publico'], 'GET', '/pages/pontos_coleta.php?id_residuo=1');
    contains('Ponto Automatizado Rhesys', $response, 'filtro encontra ponto que aceita o resíduo');
    contains('dados-pontos', $response, 'dados do mapa são renderizados');
    $response = request($base, $clients['publico'], 'GET', '/pages/pontos_coleta.php?id_residuo=3');
    verify(strpos($response['body'], 'Ponto Automatizado Rhesys') === false, 'filtro exclui ponto sem o resíduo');
    $response = request($base, $clients['publico'], 'GET', '/pages/residuos.php?id=1');
    contains('Ponto Automatizado Rhesys', $response, 'detalhe do resíduo mostra pontos compatíveis');

    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_ponto_coleta.php?id=' . $idPonto, [
        'csrf_token' => $tokenAdmin, 'id_ponto' => $idPonto, 'nome' => 'Ponto Automatizado Rhesys',
        'endereco' => 'Rua dos Testes, 123 - Centro', 'latitude' => '-21.9700000', 'longitude' => '-46.7900000',
        'telefone' => '', 'horario_funcionamento' => '', 'residuos' => [1],
    ]);
    verify($response['status'] === 302, 'edição do ponto concluída');
    verify((int) scalar($pdo, 'SELECT COUNT(*) FROM residuo_ponto_coleta WHERE id_ponto = ?', [$idPonto]) === 1, 'edição atualiza os vínculos N:N');

    $response = request($base, $clients['admin'], 'GET', '/pages/cadastro_conteudo.php');
    $tokenAdmin = csrf($response);
    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_conteudo.php', [
        'csrf_token' => $tokenAdmin, 'titulo' => 'Conteúdo automatizado Rhesys',
        'texto' => 'Texto educativo para validar a publicação. <script>alert(2)</script>',
        'url' => 'https://www.gov.br/mma/pt-br/temas/residuos-solidos', 'data_publicacao' => '2026-11-05T14:00',
        'imagem' => new CURLFile($root . '/img/fotos/reaproveitar.jpg', 'image/jpeg', 'conteudo-teste.jpg'),
    ]);
    verify($response['status'] === 302, 'conteúdo com imagem publicado');
    $idConteudo = (int) scalar($pdo, "SELECT id_conteudo FROM conteudo_educativo WHERE titulo = 'Conteúdo automatizado Rhesys'");
    $imagem = scalar($pdo, 'SELECT imagem FROM conteudo_educativo WHERE id_conteudo = ?', [$idConteudo]);
    verify(is_string($imagem) && is_file($root . '/' . $imagem), 'upload de imagem salvo no diretório público');
    $response = request($base, $clients['publico'], 'GET', '/pages/conteudos.php?id=' . $idConteudo);
    contains('Conteúdo automatizado Rhesys', $response, 'conteúdo fica disponível publicamente');
    verify(strpos($response['body'], '<script>alert(2)</script>') === false, 'texto do conteúdo não executa XSS');

    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_quiz.php', [
        'csrf_token' => $tokenAdmin, 'titulo' => 'Quiz automatizado Rhesys', 'descricao' => 'Questionário criado para o teste completo de integração.',
    ]);
    verify($response['status'] === 302, 'quiz cadastrado');
    $idQuiz = (int) scalar($pdo, "SELECT id_quiz FROM quiz WHERE titulo = 'Quiz automatizado Rhesys'");
    $response = request($base, $clients['admin'], 'GET', '/pages/cadastro_pergunta.php?id_quiz=' . $idQuiz);
    $tokenAdmin = csrf($response);
    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_pergunta.php?id_quiz=' . $idQuiz, [
        'csrf_token' => $tokenAdmin, 'id_quiz' => $idQuiz, 'enunciado' => 'Qual alternativa representa o descarte correto?',
        'alternativa_a' => 'Lixo comum', 'alternativa_b' => 'Ponto de coleta', 'alternativa_c' => 'Rio', 'alternativa_d' => 'Terreno abandonado', 'resposta_correta' => 'b',
    ]);
    verify($response['status'] === 302, 'pergunta cadastrada com quatro alternativas');
    $idPergunta = (int) scalar($pdo, 'SELECT id_pergunta FROM pergunta WHERE id_quiz = ?', [$idQuiz]);
    verify($idPergunta > 0, 'pergunta persistida no banco');

    $response = request($base, $clients['publico'], 'GET', '/pages/quiz.php?id=' . $idQuiz);
    $response = request($base, $clients['publico'], 'POST', '/pages/quiz.php?id=' . $idQuiz, ['csrf_token' => csrf($response), 'respostas' => [$idPergunta => 'b']]);
    verify($response['status'] === 302 && strpos($response['location'], 'aviso=resultado_quiz') !== false, 'resultado sem login é protegido');
    verify((int) scalar($pdo, 'SELECT COUNT(*) FROM resultado_quiz WHERE id_quiz = ?', [$idQuiz]) === 0, 'acesso anônimo não salva resultado');

    $response = request($base, $clients['interessado'], 'GET', '/pages/quiz.php?id=' . $idQuiz);
    $tokenInteressado = csrf($response);
    $response = request($base, $clients['interessado'], 'POST', '/pages/quiz.php?id=' . $idQuiz, ['csrf_token' => $tokenInteressado, 'respostas' => [$idPergunta => 'b']]);
    contains('100%', $response, 'gabarito correto calcula 100%');
    verify((int) scalar($pdo, 'SELECT pontuacao FROM resultado_quiz WHERE id_quiz = ? AND id_usuario = ? ORDER BY id_resultado DESC LIMIT 1', [$idQuiz, $idInteressado]) === 100, 'pontuação correta é persistida');
    $response = request($base, $clients['interessado'], 'POST', '/pages/quiz.php?id=' . $idQuiz, ['csrf_token' => $tokenInteressado, 'respostas' => [$idPergunta => 'x']]);
    contains('0%', $response, 'alternativa inválida não gera erro nem pontuação');
    verify(strpos($response['body'], 'Undefined array key') === false && strpos($response['body'], 'Warning') === false, 'resposta maliciosa não produz warnings');

    $response = request($base, $clients['admin'], 'POST', '/pages/cadastro_compartilhamento.php', ['csrf_token' => $tokenAdmin, 'id_residuo' => 2, 'descricao' => 'Segunda oferta para testar os cancelamentos do fluxo.']);
    $idOferta2 = (int) scalar($pdo, 'SELECT MAX(id_compartilhamento) FROM compartilhamento_residuo');
    $response = request($base, $clients['interessado'], 'POST', '/pages/compartilhamentos.php', ['csrf_token' => $tokenInteressado, 'acao' => 'demonstrar_interesse', 'id_compartilhamento' => $idOferta2]);
    $idInteresse2 = (int) scalar($pdo, 'SELECT id_interesse FROM interesse_compartilhamento WHERE id_compartilhamento = ? AND id_usuario = ?', [$idOferta2, $idInteressado]);
    $response = request($base, $clients['interessado'], 'POST', '/pages/compartilhamentos.php', ['csrf_token' => $tokenInteressado, 'acao' => 'cancelar_interesse', 'id_compartilhamento' => $idOferta2, 'id_interesse' => $idInteresse2]);
    verify($response['status'] === 302 && scalar($pdo, 'SELECT status FROM interesse_compartilhamento WHERE id_interesse = ?', [$idInteresse2]) === 'cancelado', 'usuário cancela o próprio interesse');
    $response = request($base, $clients['admin'], 'POST', '/pages/meus_compartilhamentos.php', ['csrf_token' => $tokenAdmin, 'acao' => 'cancelar_oferta', 'id_compartilhamento' => $idOferta2]);
    verify($response['status'] === 302 && scalar($pdo, 'SELECT status FROM compartilhamento_residuo WHERE id_compartilhamento = ?', [$idOferta2]) === 'cancelado', 'ofertante torna a oferta indisponível');

    echo "\nTodos os $checks testes de integração passaram.\n";
    $exitCode = 0;
} catch (Throwable $e) {
    echo "\n" . $e->getMessage() . "\n";
    if (is_file($log)) echo "\nÚltimas linhas do servidor:\n" . implode('', array_slice(file($log), -30));
    $exitCode = 1;
} finally {
    try {
        $stmt = $pdo->query("SELECT imagem FROM conteudo_educativo WHERE imagem LIKE 'img/uploads/%'");
        foreach ($stmt->fetchAll() as $row) {
            $file = $root . '/' . $row['imagem'];
            if (is_file($file)) unlink($file);
        }
    } catch (Throwable $cleanupError) {
        echo 'Falha ao limpar uploads de teste: ' . $cleanupError->getMessage() . "\n";
        $exitCode = 1;
    }
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    $serverRunning = false;
    foreach ($clients as $jar) if (is_file($jar)) unlink($jar);
    if (is_dir($tempDir)) rmdir($tempDir);
}
exit($exitCode);
