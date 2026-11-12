<?php
require_once __DIR__ . '/../config/config.php';

$titulo_pagina = 'Jogo da Reciclagem';
$scripts_extras = [BASE_URL . 'js/jogo_reciclagem.js?v=5'];
require __DIR__ . '/../includes/header.php';
?>

<section class="reveal text-center mb-4">
    <span class="section-tag">Jogo educativo</span>
    <h1 class="mt-1 mb-2">Jogue no lixeiro certo</h1>
    <p class="text-muted mx-auto mb-0" style="max-width: 680px;">
        Um resíduo aparece por vez. Toque na lixeira da cor certa (padrão CONAMA 275/2001)
        e acumule pontos — cada erro derruba a sua sequência.
    </p>
</section>

<div id="jogo-reciclagem">
    <div class="jogo-hud d-none" id="jogo-hud">
        <div class="jogo-hud-item">
            <span class="jogo-hud-label">Pontos</span>
            <span class="jogo-hud-valor" id="jogo-pontos">0</span>
        </div>
        <div class="jogo-hud-item">
            <span class="jogo-hud-label">Rodada</span>
            <span class="jogo-hud-valor" id="jogo-rodada">0/10</span>
        </div>
        <div class="jogo-hud-item">
            <span class="jogo-hud-label">Sequência</span>
            <span class="jogo-hud-valor" id="jogo-streak">0 🔥</span>
        </div>
    </div>

    <div id="jogo-tela-inicial" class="jogo-painel text-center">
        <div class="jogo-emoji-gigante">♻️</div>
        <h2 class="mt-3">Vamos reciclar?</h2>
        <p class="text-muted mx-auto" style="max-width: 520px;">
            10 resíduos e 6 lixeiras coloridas. Descubra onde cada um vai
            e marque o máximo de pontos.
        </p>
        <button type="button" class="btn btn-success btn-lg px-4 mt-3" id="jogo-comecar">
            <i class="bi bi-play-fill me-1"></i> Começar
        </button>
    </div>

    <div id="jogo-tela-jogo" class="d-none">
        <div class="jogo-item">
            <span class="jogo-item-nome" id="jogo-item-nome">Casca de banana</span>
            <span class="jogo-item-emoji" id="jogo-item-emoji">🍌</span>
        </div>

        <div id="jogo-lixeiras" class="jogo-lixeiras"></div>

        <div id="jogo-feedback" class="jogo-feedback mt-4" aria-live="polite"></div>

        <div class="text-center mt-4">
            <button type="button" class="btn btn-outline-success d-none" id="jogo-proximo">
                <span id="jogo-proximo-txt">Próximo</span>
                <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    <div id="jogo-tela-resultado" class="jogo-painel text-center d-none">
        <div class="jogo-emoji-gigante" id="jogo-resultado-emoji">🏆</div>
        <h2 class="mt-3" id="jogo-resultado-titulo">Mandou bem!</h2>
        <p class="text-muted" id="jogo-resultado-texto"></p>
        <div class="d-flex flex-wrap justify-content-center gap-4 mt-3" id="jogo-resultado-stats"></div>
        <button type="button" class="btn btn-success btn-lg px-4 mt-4" id="jogo-recomecar">
            <i class="bi bi-arrow-repeat me-1"></i> Jogar de novo
        </button>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>