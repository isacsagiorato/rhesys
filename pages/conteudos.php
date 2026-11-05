<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

function imagem_conteudo($imagem) {
    $imagem = (string) $imagem;
    if (preg_match('#^img/uploads/[a-f0-9]{32}\.(jpg|png|webp|gif)$#', $imagem)) return BASE_URL . $imagem;
    if (filter_var($imagem, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($imagem, PHP_URL_SCHEME)), ['http', 'https'], true)) return $imagem;
    return BASE_URL . 'img/fotos/arvore.jpg';
}
function url_conteudo($url) {
    return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
}

$id_conteudo = (int) ($_GET['id'] ?? 0);
$titulo_pagina = 'Conteúdos educativos';
require __DIR__ . '/../includes/header.php';

if ($id_conteudo > 0) {
    $stmt = $pdo->prepare('SELECT * FROM conteudo_educativo WHERE id_conteudo = :id');
    $stmt->bindValue(':id', $id_conteudo, PDO::PARAM_INT);
    $stmt->execute();
    $conteudo = $stmt->fetch();
    if (!$conteudo) {
        echo '<div class="alert alert-warning">Conteúdo não encontrado.</div><a href="conteudos.php" class="btn btn-success">Voltar aos conteúdos</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    $url = url_conteudo($conteudo['url']);
    ?>
    <a href="conteudos.php" class="btn btn-outline-success mb-4">&larr; Voltar aos conteúdos</a>
    <?php if (($_GET['sucesso'] ?? '') === '1'): ?><div class="alert alert-success">Conteúdo publicado com sucesso.</div><?php endif; ?>
    <article class="info-card reveal">
        <div class="card-body p-4 p-lg-5">
            <span class="crumb text-success">Educação ambiental</span>
            <h1 class="mt-2"><?php echo htmlspecialchars($conteudo['titulo']); ?></h1>
            <p class="text-muted small">Publicado em <?php echo date('d/m/Y', strtotime($conteudo['data_publicacao'])); ?></p>
            <img class="content-cover mb-4" src="<?php echo htmlspecialchars(imagem_conteudo($conteudo['imagem']), ENT_QUOTES, 'UTF-8'); ?>" alt="Imagem ilustrativa do conteúdo">
            <div class="fs-6"><?php echo nl2br(htmlspecialchars($conteudo['texto'])); ?></div>
            <?php if ($url): ?><a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="link-arrow mt-4">Consultar fonte ou material complementar <i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
        </div>
    </article>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$conteudos = $pdo->query('SELECT * FROM conteudo_educativo ORDER BY data_publicacao DESC, id_conteudo DESC')->fetchAll();
?>
<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/arvore.jpg" alt="Luz do sol entre as folhas de uma árvore">
    <span class="crumb">Aprenda e compartilhe conhecimento</span>
    <h1 class="mt-2">Conteúdos educativos</h1>
    <p class="mt-2">Artigos e orientações sobre descarte, reciclagem, logística reversa e sustentabilidade.</p>
</section>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
    <p class="text-muted small mb-0"><?php echo count($conteudos); ?> conteúdo(s) disponível(is).</p>
    <?php if (usuario_logado()): ?><a href="cadastro_conteudo.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Cadastrar conteúdo</a><?php endif; ?>
</div>
<?php if (!$conteudos): ?>
    <div class="alert alert-info mt-4">Nenhum conteúdo publicado até o momento.</div>
<?php else: ?>
    <div class="row g-4 mt-2 reveal">
        <?php foreach ($conteudos as $conteudo): ?>
            <div class="col-md-6 col-lg-4">
                <a href="conteudos.php?id=<?php echo (int) $conteudo['id_conteudo']; ?>" class="text-decoration-none text-dark">
                    <article class="card card-residuo">
                        <div class="photo"><img src="<?php echo htmlspecialchars(imagem_conteudo($conteudo['imagem']), ENT_QUOTES, 'UTF-8'); ?>" alt="Imagem ilustrativa: <?php echo htmlspecialchars($conteudo['titulo'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="card-body">
                            <p class="small text-muted mb-2"><?php echo date('d/m/Y', strtotime($conteudo['data_publicacao'])); ?></p>
                            <h2 class="h5 card-title"><?php echo htmlspecialchars($conteudo['titulo']); ?></h2>
                            <p class="card-text text-muted small clamp-3"><?php echo htmlspecialchars($conteudo['texto']); ?></p>
                            <span class="link-arrow small">Ler conteúdo <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
