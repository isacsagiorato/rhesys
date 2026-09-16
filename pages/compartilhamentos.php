<?php
require_once __DIR__ . '/../config/config.php';

$titulo_pagina = 'Compartilhamentos';
require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens usados em bom estado que podem ser reaproveitados">
    <span class="crumb">Economia circular</span>
    <h1 class="mt-2">Compartilhamentos</h1>
    <p class="mt-2">Oferte itens que podem ser reaproveitados e encontre o que você precisa com outras pessoas.</p>
</section>

<div class="row g-5 mt-4 align-items-center reveal">
    <div class="col-lg-7">
        <span class="badge-lr d-inline-flex align-items-center gap-2 mb-3">
            <i class="bi bi-hourglass-split"></i> Em breve
        </span>
        <h2 class="mb-3">Um jeito simples de reaproveitar</h2>
        <p class="text-muted">
            Estamos finalizando esta área do sistema. Em vez de descartar itens que ainda
            servem — móveis, roupas, eletrônicos, livros —, você poderá ofertá-los aqui
            para outras pessoas da comunidade, reduzindo o desperdício e incentivando o
            reaproveitamento.
        </p>

        <div class="d-flex flex-column gap-3 mt-4">
            <div class="step-item">
                <div class="step-dot">1</div>
                <div>
                    <strong>Oferte um item</strong>
                    <p class="small text-muted mb-0">Descreva o material que você quer compartilhar com outras pessoas.</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-dot">2</div>
                <div>
                    <strong>Receba interesses</strong>
                    <p class="small text-muted mb-0">Outros usuários demonstram interesse e você escolhe para quem entregar.</p>
                </div>
            </div>
            <div class="step-item">
                <div class="step-dot">3</div>
                <div>
                    <strong>Conclua o compartilhamento</strong>
                    <p class="small text-muted mb-0">Combinem a entrega e deem um novo destino útil ao item.</p>
                </div>
            </div>
        </div>

        <a href="pontos_coleta.php" class="btn btn-outline-success mt-4">
            <i class="bi bi-geo-alt me-2"></i>Enquanto isso, veja os pontos de coleta
        </a>
    </div>
    <div class="col-lg-5">
        <div class="hero-photo ratio ratio-1x1 mx-auto" style="max-width: 420px;">
            <img src="<?php echo BASE_URL; ?>img/fotos/reaproveitar.jpg" alt="Itens que podem ser reaproveitados">
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
