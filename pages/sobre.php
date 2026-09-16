<?php
require_once __DIR__ . '/../config/config.php';

$titulo_pagina = 'Sobre';
require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/floresta.jpg" alt="Floresta com a luz entrando entre as árvores">
    <span class="crumb">Sobre o projeto</span>
    <h1 class="mt-2">Rhesys: sustentabilidade e tecnologia</h1>
    <p class="mt-2">
        Um sistema digital voltado à conscientização ambiental e à orientação
        da população sobre o descarte correto de resíduos sólidos.
    </p>
</section>

<div class="row g-5 mt-4 align-items-center reveal">
    <div class="col-lg-7">
        <div class="section-tag">O que é o Rhesys</div>
        <p class="fs-5 mt-2">
            O Rhesys é um sistema digital voltado à conscientização ambiental e à orientação
            da população sobre o descarte correto de resíduos sólidos.
        </p>
        <p class="text-muted">
            O objetivo do sistema é oferecer uma plataforma na qual os usuários possam pesquisar
            diferentes tipos de materiais, entender sua classificação e receber orientações
            práticas sobre a forma adequada de descarte. Além disso, o Rhesys disponibiliza
            informações sobre logística reversa para itens específicos, como pilhas e
            equipamentos eletrônicos, e conta com conteúdos educativos e quizzes interativos
            para tornar o aprendizado mais dinâmico.
        </p>
        <p class="text-muted mb-0">
            O sistema também permite que os próprios usuários compartilhem materiais entre si,
            incentivando o reaproveitamento e a economia circular, e disponibiliza a localização
            de pontos de coleta próximos.
        </p>
    </div>
    <div class="col-lg-5">
        <div class="hero-photo ratio ratio-1x1 mx-auto" style="max-width: 420px;">
            <img src="<?php echo BASE_URL; ?>img/fotos/tecnologia.jpg" alt="Placa de circuito eletrônico">
        </div>
    </div>
</div>

<div class="row g-4 mt-4 reveal">
    <div class="col-md-4">
        <div class="info-card p-4">
            <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem;"><i class="bi bi-search"></i></span>
            <h5 class="card-title">Pesquise</h5>
            <p class="small text-muted mb-0">Classificação e orientações de descarte conforme a ABNT NBR 10004.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-card p-4">
            <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem;"><i class="bi bi-people"></i></span>
            <h5 class="card-title">Compartilhe</h5>
            <p class="small text-muted mb-0">Reaproveitamento de itens entre usuários, incentivando a economia circular.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-card p-4">
            <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem;"><i class="bi bi-mortarboard"></i></span>
            <h5 class="card-title">Aprenda</h5>
            <p class="small text-muted mb-0">Conteúdos educativos e quizzes interativos sobre gestão de resíduos.</p>
        </div>
    </div>
</div>

<div class="mt-5 reveal">
    <div class="info-card p-4">
        <div class="d-flex gap-3 align-items-center flex-wrap">
            <div class="icon-chip-sm"><i class="bi bi-mortarboard"></i></div>
            <div>
                <h5 class="mb-1">Trabalho de Conclusão de Curso</h5>
                <p class="mb-0 text-muted small">
                    Desenvolvido no curso de Tecnologia em Sistemas para Internet, do
                    Instituto Federal de Educação, Ciência e Tecnologia de São Paulo —
                    Câmpus São João da Boa Vista.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
