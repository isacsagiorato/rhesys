<?php
require_once __DIR__ . '/config/config.php';

$titulo_pagina = 'Início';
require __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 bg-light rounded-3 text-center">
    <img src="<?php echo BASE_URL; ?>img/logo.svg" alt="Rhesys - Sustentabilidade e Tecnologia" class="img-fluid mb-3" style="max-width: 320px;">
    <p class="col-lg-8 mx-auto fs-5">
        Conscientização e orientação sobre o descarte correto de resíduos sólidos.
        Pesquise materiais, aprenda a descartá-los corretamente, compartilhe itens
        com outras pessoas e teste seus conhecimentos em nossos quizzes.
    </p>
    <a href="pages/residuos.php" class="btn btn-success btn-lg me-2">Pesquisar Resíduos</a>
    <a href="pages/quiz.php" class="btn btn-outline-success btn-lg">Fazer um Quiz</a>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card card-residuo shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Pesquise resíduos</h5>
                <p class="card-text">Descubra como classificar e descartar corretamente cada tipo de material.</p>
                <a href="pages/residuos.php" class="btn btn-sm btn-success">Pesquisar</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-residuo shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Compartilhe itens</h5>
                <p class="card-text">Tem algo que pode ser reaproveitado? Ofereça para quem tiver interesse.</p>
                <a href="pages/compartilhamentos.php" class="btn btn-sm btn-success">Ver compartilhamentos</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-residuo shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Teste seus conhecimentos</h5>
                <p class="card-text">Responda quizzes educativos sobre gestão de resíduos e sustentabilidade.</p>
                <a href="pages/quiz.php" class="btn btn-sm btn-success">Fazer quiz</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
