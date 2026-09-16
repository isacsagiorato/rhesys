<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexao.php';

$titulo_pagina = 'Início';
require __DIR__ . '/includes/header.php';
?>

<?php
// Números para a seção de estatísticas
$total_residuos = (int) $pdo->query('SELECT COUNT(*) FROM residuo')->fetchColumn();
$total_pontos   = (int) $pdo->query('SELECT COUNT(*) FROM ponto_coleta')->fetchColumn();
$total_quizzes  = (int) $pdo->query('SELECT COUNT(*) FROM quiz')->fetchColumn();
?>

<section class="row align-items-center g-5 reveal">
    <div class="col-lg-6">
        <span class="hero-badge"><i class="bi bi-recycle"></i> Educação ambiental + tecnologia</span>
        <h1 class="hero-title mt-4">
            Descarte certo,<br>
            <span class="grad-text">planeta agradecido.</span>
        </h1>
        <p class="hero-lead mt-3">
            Pesquise como descartar corretamente cada material, compartilhe itens com
            outras pessoas, encontre pontos de coleta próximos e teste seus conhecimentos
            em quizzes educativos.
        </p>
        <div class="d-flex flex-wrap gap-3 mt-4">
            <a href="pages/residuos.php" class="btn btn-success btn-lg">
                <i class="bi bi-search me-2"></i>Pesquisar resíduos
            </a>
            <a href="pages/quiz.php" class="btn btn-outline-success btn-lg">
                <i class="bi bi-lightbulb me-2"></i>Fazer um quiz
            </a>
        </div>
        <div class="d-flex flex-wrap gap-5 mt-5">
            <div>
                <div class="stat-num"><?php echo $total_residuos; ?>+</div>
                <div class="stat-label">resíduos cadastrados</div>
            </div>
            <div>
                <div class="stat-num"><?php echo $total_pontos; ?></div>
                <div class="stat-label">pontos de coleta</div>
            </div>
            <div>
                <div class="stat-num"><?php echo $total_quizzes; ?></div>
                <div class="stat-label">quiz educativo</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="position-relative">
            <div class="hero-photo ratio ratio-4x3">
                <img src="<?php echo BASE_URL; ?>img/fotos/hero-hands.jpg" alt="Mãos segurando terra com uma muda de planta">
            </div>
            <span class="hero-chip" style="top: 1.2rem; left: 1.2rem;">
                <i class="bi bi-patch-check-fill" style="color: var(--rhe-600);"></i> Descarte correto
            </span>
            <span class="hero-chip" style="bottom: 1.4rem; right: 1.2rem;">
                <i class="bi bi-cpu" style="color: var(--rhe-600);"></i> Tecnologia a favor do planeta
            </span>
        </div>
    </div>
</section>

<section class="mt-5 pt-3 reveal">
    <div class="text-center mb-4">
        <div class="section-tag">Comece por aqui</div>
        <h2 class="mt-1">O que você quer fazer hoje?</h2>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card card-feature">
                <div class="photo">
                    <img src="<?php echo BASE_URL; ?>img/fotos/reciclaveis.jpg" alt="Garrafas PET e latas para reciclagem">
                </div>
                <span class="icon-chip"><i class="bi bi-search"></i></span>
                <div class="card-body">
                    <h5 class="card-title">Pesquise resíduos</h5>
                    <p class="card-text text-muted small">Descubra a classificação e a forma correta de descartar cada tipo de material.</p>
                    <a href="pages/residuos.php" class="link-arrow">Pesquisar <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature">
                <div class="photo">
                    <img src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens em bom estado que podem ser reaproveitados">
                </div>
                <span class="icon-chip"><i class="bi bi-people"></i></span>
                <div class="card-body">
                    <h5 class="card-title">Compartilhe itens</h5>
                    <p class="card-text text-muted small">Tem algo que pode ser reaproveitado? Ofereça para quem tiver interesse.</p>
                    <a href="pages/compartilhamentos.php" class="link-arrow">Ver compartilhamentos <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature">
                <div class="photo">
                    <img src="<?php echo BASE_URL; ?>img/fotos/arvore.jpg" alt="Árvore com a luz do sol entre as folhas">
                </div>
                <span class="icon-chip"><i class="bi bi-lightbulb"></i></span>
                <div class="card-body">
                    <h5 class="card-title">Teste seus conhecimentos</h5>
                    <p class="card-text text-muted small">Quizzes educativos sobre gestão de resíduos e sustentabilidade.</p>
                    <a href="pages/quiz.php" class="link-arrow">Fazer quiz <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mt-5 pt-4 reveal">
    <div class="row align-items-center g-5">
        <div class="col-lg-6 order-lg-2">
            <div class="hero-photo ratio ratio-1x1 mx-auto" style="max-width: 440px;">
                <img src="<?php echo BASE_URL; ?>img/fotos/tecnologia.jpg" alt="Placa de circuito eletrônico">
            </div>
        </div>
        <div class="col-lg-6 order-lg-1">
            <div class="section-tag">Nossa essência</div>
            <h2 class="mt-1 mb-3">Tecnologia a serviço da sustentabilidade</h2>
            <p class="text-muted">
                O Rhesys une conhecimento ambiental e recursos digitais para facilitar o
                dia a dia de quem quer descartar melhor: buscas rápidas, orientações claras
                conforme as normas brasileiras e informações sobre logística reversa.
            </p>
            <div class="d-flex flex-column gap-3 mt-4">
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-recycle"></i></div>
                    <div>
                        <strong>Logística reversa</strong>
                        <p class="small text-muted mb-0">Orientação para pilhas, baterias e eletrônicos, conforme a Lei nº 12.305/2010.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-people"></i></div>
                    <div>
                        <strong>Economia circular</strong>
                        <p class="small text-muted mb-0">Compartilhamento de itens entre usuários para reduzir o desperdício.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-mortarboard"></i></div>
                    <div>
                        <strong>Educação contínua</strong>
                        <p class="small text-muted mb-0">Conteúdos educativos e quizzes para aprender enquanto usa o sistema.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="mt-5 pt-2 reveal">
    <div class="cta-band text-center">
        <h2 class="text-white mb-2">Pronto para fazer a diferença?</h2>
        <p class="mb-4" style="color: rgba(255, 255, 255, .8);">Comece pesquisando um material ou explore os pontos de coleta mais próximos de você.</p>
        <a href="pages/pontos_coleta.php" class="btn btn-light btn-lg rounded-pill px-4 fw-semibold">
            Ver pontos de coleta <i class="bi bi-geo-alt ms-1"></i>
        </a>
    </div>
</section>

<!-- Fotos: Unsplash.com (baixadas localmente em img/fotos) -->

<?php require __DIR__ . '/includes/footer.php'; ?>
