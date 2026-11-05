<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
exige_login();

$titulo_pagina = 'Cadastrar conteúdo educativo';
$erros = [];
$titulo = $texto = $url = '';
$data_publicacao = date('Y-m-d\TH:i');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $texto = trim((string) ($_POST['texto'] ?? ''));
    $url = trim((string) ($_POST['url'] ?? ''));
    $data_publicacao = trim((string) ($_POST['data_publicacao'] ?? ''));
    $data = DateTime::createFromFormat('Y-m-d\TH:i', $data_publicacao);
    $arquivo = $_FILES['imagem'] ?? null;
    $mime = null;
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    if (!verificar_csrf()) $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 200) $erros[] = 'O título deve ter entre 3 e 200 caracteres.';
    if (mb_strlen($texto) < 10 || mb_strlen($texto) > 20000) $erros[] = 'O texto deve ter entre 10 e 20000 caracteres.';
    if ($data === false || $data->format('Y-m-d\TH:i') !== $data_publicacao) $erros[] = 'Informe uma data de publicação válida.';
    if ($url !== '') {
        $esquema = parse_url($url, PHP_URL_SCHEME);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) $esquema), ['http', 'https'], true) || mb_strlen($url) > 255) {
            $erros[] = 'Informe uma URL http(s) válida com no máximo 255 caracteres.';
        }
    }
    if ($arquivo && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $erros[] = 'Não foi possível enviar a imagem. Tente novamente.';
        } elseif (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
            $erros[] = 'A imagem deve ter no máximo 5 MB.';
        } elseif (!is_uploaded_file($arquivo['tmp_name'])) {
            $erros[] = 'O arquivo enviado não é um upload válido.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
            if (!isset($extensoes[$mime]) || getimagesize($arquivo['tmp_name']) === false) {
                $erros[] = 'Use uma imagem JPG, PNG, WEBP ou GIF válida.';
            }
        }
    }

    if (!$erros) {
        $caminho_imagem = null;
        try {
            if ($arquivo && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $diretorio = dirname(__DIR__) . '/img/uploads';
                if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
                    throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
                }
                $nome_arquivo = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
                if (!move_uploaded_file($arquivo['tmp_name'], $diretorio . '/' . $nome_arquivo)) {
                    throw new RuntimeException('Não foi possível salvar a imagem enviada.');
                }
                $caminho_imagem = 'img/uploads/' . $nome_arquivo;
            }
            $stmt = $pdo->prepare('INSERT INTO conteudo_educativo (titulo, texto, imagem, url, data_publicacao) VALUES (:titulo, :texto, :imagem, :url, :data)');
            $stmt->bindValue(':titulo', $titulo);
            $stmt->bindValue(':texto', $texto);
            $stmt->bindValue(':imagem', $caminho_imagem, $caminho_imagem === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':url', $url !== '' ? $url : null, $url === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':data', $data->format('Y-m-d H:i:s'));
            $stmt->execute();
            header('Location: conteudos.php?id=' . (int) $pdo->lastInsertId() . '&sucesso=1');
            exit;
        } catch (Throwable $e) {
            if ($caminho_imagem && is_file(dirname(__DIR__) . '/' . $caminho_imagem)) unlink(dirname(__DIR__) . '/' . $caminho_imagem);
            error_log('Erro ao cadastrar conteúdo: ' . $e->getMessage());
            $erros[] = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível salvar o conteúdo.';
        }
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/arvore.jpg" alt="Árvore iluminada pelo sol">
    <span class="crumb">Educação ambiental</span>
    <h1 class="mt-2">Cadastrar conteúdo educativo</h1>
    <p class="mt-2">Publique orientações e materiais para a comunidade.</p>
</section>
<div class="row justify-content-center mt-5 reveal"><div class="col-lg-8">
    <div class="card shadow-sm"><div class="card-body p-4 p-lg-5">
        <?php if ($erros): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="cadastro_conteudo.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label for="titulo" class="form-label">Título</label>
                <input class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($titulo); ?>" required minlength="3" maxlength="200">
            </div>
            <div class="mb-3">
                <label for="texto" class="form-label">Texto</label>
                <textarea class="form-control" id="texto" name="texto" rows="9" required minlength="10" maxlength="20000"><?php echo htmlspecialchars($texto); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="imagem" class="form-label">Imagem (opcional)</label>
                <input class="form-control" type="file" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="form-text">JPG, PNG, WEBP ou GIF, até 5 MB.</div>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">Fonte ou link complementar (opcional)</label>
                <input class="form-control" type="url" id="url" name="url" value="<?php echo htmlspecialchars($url); ?>" maxlength="255" placeholder="https://...">
            </div>
            <div class="mb-4">
                <label for="data_publicacao" class="form-label">Data de publicação</label>
                <input class="form-control" type="datetime-local" id="data_publicacao" name="data_publicacao" value="<?php echo htmlspecialchars($data_publicacao); ?>" required>
            </div>
            <button type="submit" class="btn btn-success">Publicar conteúdo</button>
            <a href="conteudos.php" class="btn btn-outline-success">Cancelar</a>
        </form>
    </div></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
