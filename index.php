<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/residuos.php';

$titulo_pagina = 'Início';

// Números para a seção de estatísticas
$total_residuos = (int) $pdo->query('SELECT COUNT(*) FROM residuo')->fetchColumn();
$total_pontos   = (int) $pdo->query('SELECT COUNT(*) FROM ponto_coleta')->fetchColumn();
$total_quizzes  = (int) $pdo->query('SELECT COUNT(*) FROM quiz')->fetchColumn();

// Faixa animada com resíduos do catálogo (um pouco de cada tipo)
$faixa = $pdo->query(
    "SELECT r.id_residuo, r.nome, r.tipo_material
     FROM residuo r
     WHERE r.nome <> 'Resíduos orgânicos' AND r.tipo_material IS NOT NULL
     ORDER BY r.tipo_material, r.nome"
)->fetchAll();
$por_tipo = [];
foreach ($faixa as $item) $por_tipo[$item['tipo_material']][] = $item;
$faixa = [];
for ($i = 0; $i < 3; $i++) {
    foreach (TIPOS_RESIDUO as $chave => $_) {
        if (isset($por_tipo[$chave][$i])) $faixa[] = $por_tipo[$chave][$i];
    }
}

require __DIR__ . '/includes/header.php';
?>

<!-- ============================================================
     HERO: busca + ilustração animada (lixeiras da coleta seletiva)
     ============================================================ -->
<section class="row align-items-center g-5 hero-inicio">
    <div class="col-lg-6 reveal">
        <span class="hero-badge"><i class="bi bi-recycle"></i> Educação ambiental + tecnologia</span>
        <h1 class="hero-title mt-4">
            Descarte certo,<br>
            <span class="grad-text">planeta agradecido.</span>
        </h1>
        <p class="hero-lead mt-3">
            Descubra como descartar cada material, compartilhe o que pode ser reaproveitado,
            encontre pontos de coleta e aprenda brincando com quizzes e jogos.
        </p>

        <form class="hero-busca mt-4" method="get" action="pages/residuos.php#resultados" role="search">
            <label for="busca-inicio" class="visually-hidden">O que você quer descartar?</label>
            <div class="search-pill">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="busca-inicio" name="busca" placeholder="Ex.: pilha, óleo, vidro..." data-autocompletar="<?php echo BASE_URL; ?>pages/sugestoes.php">
                <button class="btn btn-success" type="submit">Buscar</button>
            </div>
            <div class="hero-sugestoes">
                <span>Mais buscados:</span>
                <?php foreach (['pilha', 'óleo', 'remédio', 'pizza', 'vidro'] as $sugestao): ?>
                    <a href="pages/residuos.php?busca=<?php echo rawurlencode($sugestao); ?>#resultados"><?php echo htmlspecialchars($sugestao); ?></a>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="stats-grid mt-4">
            <a class="stat-card" href="pages/residuos.php">
                <div class="stat-num" data-contar="<?php echo $total_residuos; ?>"><?php echo $total_residuos; ?></div>
                <div class="stat-label">resíduos cadastrados</div>
            </a>
            <a class="stat-card" href="pages/pontos_coleta.php">
                <div class="stat-num" data-contar="<?php echo $total_pontos; ?>"><?php echo $total_pontos; ?></div>
                <div class="stat-label"><?php echo $total_pontos === 1 ? 'ponto de coleta' : 'pontos de coleta'; ?></div>
            </a>
            <a class="stat-card" href="pages/quiz.php">
                <div class="stat-num" data-contar="<?php echo $total_quizzes; ?>"><?php echo $total_quizzes; ?></div>
                <div class="stat-label"><?php echo $total_quizzes === 1 ? 'quiz educativo' : 'quizzes educativos'; ?></div>
            </a>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="cena-wrap" aria-hidden="true">
            <svg class="cena" viewBox="0 0 420 470" xmlns="http://www.w3.org/2000/svg" role="presentation" focusable="false">
                <defs>
                    <linearGradient id="g-fundo" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#d1fae5"/>
                        <stop offset="1" stop-color="#a7f3d0"/>
                    </linearGradient>
                    <g id="simbolo-recicla" transform="scale(.5)">
                        <path d="M-14-6 A16 16 0 0 1 10-12 M14 4 A16 16 0 0 1 -2 15 M-12 10 A16 16 0 0 1 -15 -4" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/>
                        <path d="M10-12 l-1 7 l7-3 z M-2 15 l7 2 l-4-6 z M-15-4 l-3 7 l7-1 z" fill="currentColor"/>
                    </g>
                    <clipPath id="recorte-cena"><rect x="10" y="10" width="400" height="450" rx="34"/></clipPath>
                </defs>

                <g clip-path="url(#recorte-cena)">
                <!-- Fundo -->
                <rect x="10" y="10" width="400" height="450" rx="34" fill="url(#g-fundo)"/>
                <circle class="cena-sol" cx="372" cy="52" r="24" fill="#fef3c7"/>
                <g class="cena-recicla-giro" transform="translate(372 52) scale(.8)">
                    <path d="M-14-6 A16 16 0 0 1 10-12" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round"/>
                    <path d="M14 4 A16 16 0 0 1 -2 15" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round"/>
                    <path d="M-12 10 A16 16 0 0 1 -15 -4" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round"/>
                    <path d="M10-12 l-1 7 l7-3 z M-2 15 l7 2 l-4-6 z M-15-4 l-3 7 l7-1 z" fill="#059669"/>
                </g>
                <!-- Nuvens -->
                <g class="cena-nuvem nuvem-1" fill="#fff" opacity=".85"><ellipse cx="120" cy="72" rx="34" ry="14"/><ellipse cx="140" cy="62" rx="22" ry="14"/><ellipse cx="104" cy="66" rx="16" ry="11"/></g>
                <g class="cena-nuvem nuvem-2" fill="#fff" opacity=".7"><ellipse cx="245" cy="46" rx="26" ry="10"/><ellipse cx="260" cy="39" rx="16" ry="10"/></g>
                <!-- Morros -->
                <path d="M10 320 Q110 260 210 310 T410 290 L410 430 Q410 460 380 460 L40 460 Q10 460 10 430 Z" fill="#6ee7b7" opacity=".55"/>
                <path d="M10 380 Q140 340 260 375 T410 365 L410 430 Q410 460 380 460 L40 460 Q10 460 10 430 Z" fill="#34d399" opacity=".45"/>

                <?php
                // As 10 cores da Resolução CONAMA 275/2001, em duas fileiras de cinco.
                // Cada lixeira recebe um item do seu material (desenhado em volta de 0,0 = base do item).
                $lixeiras = [
                    // Fileira de trás
                    ['cor' => '#2563eb', 'tampa' => '#1d4ed8', 'rot' => ['Papel'],
                     'item' => '<rect x="-12" y="-30" width="24" height="30" rx="3" fill="#fff" stroke="#93c5fd" stroke-width="2"/><path d="M-7 -21 h14 M-7 -15 h14 M-7 -9 h9" stroke="#93c5fd" stroke-width="2" stroke-linecap="round"/>'],
                    ['cor' => '#dc2626', 'tampa' => '#b91c1c', 'rot' => ['Plástico'],
                     'item' => '<path d="M-4 -34 h8 v6 q7 5 7 13 v12 q0 3 -3 3 h-16 q-3 0 -3 -3 v-12 q0 -8 7 -13 z" fill="#fecaca" stroke="#dc2626" stroke-width="2"/><rect x="-5" y="-38" width="10" height="6" rx="2" fill="#dc2626"/>'],
                    ['cor' => '#16a34a', 'tampa' => '#15803d', 'rot' => ['Vidro'],
                     'item' => '<rect x="-12" y="-28" width="24" height="28" rx="6" fill="#bbf7d0" stroke="#16a34a" stroke-width="2"/><rect x="-9" y="-34" width="18" height="7" rx="2" fill="#16a34a"/>'],
                    ['cor' => '#eab308', 'tampa' => '#ca8a04', 'rot' => ['Metal'],
                     'item' => '<rect x="-10" y="-30" width="20" height="30" rx="4" fill="#e5e7eb" stroke="#6b7280" stroke-width="2"/><path d="M-10 -23 h20 M-10 -7 h20" stroke="#9ca3af" stroke-width="2"/>'],
                    ['cor' => '#92400e', 'tampa' => '#78350f', 'rot' => ['Orgânico'],
                     'item' => '<path d="M-10 -28 q10 4 20 0 q-6 6 -6 14 q0 8 6 14 q-10 -4 -20 0 q6 -6 6 -14 q0 -8 -6 -14 z" fill="#fde68a" stroke="#b45309" stroke-width="2"/><path d="M0 -28 v-6" stroke="#78350f" stroke-width="2.5" stroke-linecap="round"/><path d="M1 -32 q6 -5 10 -2 q-4 5 -10 2 z" fill="#16a34a"/>'],
                    // Fileira da frente
                    ['cor' => '#1f2937', 'tampa' => '#111827', 'rot' => ['Madeira'],
                     'item' => '<g transform="rotate(-12)"><rect x="-17" y="-14" width="34" height="13" rx="2" fill="#ca8a04" stroke="#713f12" stroke-width="2"/><path d="M-12 -9 q9 -3 20 -2 M-10 -5 q8 -2 16 0" stroke="#713f12" stroke-width="1.5" fill="none"/></g>'],
                    ['cor' => '#f97316', 'tampa' => '#ea580c', 'rot' => ['Perigoso'],
                     'item' => '<rect x="-8" y="-30" width="16" height="30" rx="3" fill="#fed7aa" stroke="#c2410c" stroke-width="2"/><rect x="-3" y="-34" width="6" height="4" rx="1" fill="#c2410c"/><path d="M-4 -18 h8 M0 -22 v8" stroke="#c2410c" stroke-width="2" stroke-linecap="round"/>'],
                    ['cor' => '#f8fafc', 'tampa' => '#e2e8f0', 'rot' => ['Saúde'], 'contorno' => '#94a3b8', 'simbolo' => '#94a3b8',
                     'item' => '<g transform="rotate(-20)"><rect x="-4" y="-30" width="8" height="22" rx="2" fill="#e0f2fe" stroke="#64748b" stroke-width="1.8"/><path d="M0 -8 v9" stroke="#64748b" stroke-width="1.6"/><path d="M-6 -30 h12 M0 -30 v-6 M-4 -36 h8" stroke="#64748b" stroke-width="2" stroke-linecap="round"/></g>'],
                    ['cor' => '#7c3aed', 'tampa' => '#6d28d9', 'rot' => ['Radioativo'],
                     'item' => '<rect x="-12" y="-28" width="24" height="28" rx="4" fill="#ddd6fe" stroke="#6d28d9" stroke-width="2"/><circle cx="0" cy="-14" r="8" fill="#facc15"/><path d="M0 -14 L-3.5 -20 A7 7 0 0 1 3.5 -20 Z M0 -14 L7 -14 A7 7 0 0 1 3.5 -8 Z M0 -14 L-3.5 -8 A7 7 0 0 1 -7 -14 Z" fill="#1f2937"/><circle cx="0" cy="-14" r="1.6" fill="#1f2937"/>'],
                    ['cor' => '#6b7280', 'tampa' => '#4b5563', 'rot' => ['Não', 'reciclável'],
                     'item' => '<path d="M-12 -28 l6 -3 l6 3 l6 -3 l6 3 v24 l-6 4 l-6 -3 l-6 3 l-6 -3 z" fill="#d1d5db" stroke="#6b7280" stroke-width="2"/><path d="M-6 -18 h12 M-6 -12 h8" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"/>'],
                ];
                $colunas = [38, 110, 182, 254, 326];   // x da borda esquerda de cada lixeira
                $tampas  = [140, 330];                  // y da tampa (fileira de trás e da frente)

                // Desenha uma fileira: primeiro os itens (para "entrarem" atrás do corpo), depois as lixeiras
                foreach ([0, 1] as $fileira):
                    $y = $tampas[$fileira];
                    ?>
                <rect x="28" y="<?php echo $y + 78; ?>" width="364" height="9" rx="4.5" fill="#047857" opacity=".16"/>
                    <?php
                    foreach (array_slice($lixeiras, $fileira * 5, 5) as $j => $l):
                        $n = $fileira * 5 + $j + 1; $cx = $colunas[$j] + 28; ?>
                <g transform="translate(<?php echo $cx; ?> <?php echo $y - ($fileira ? 40 : 50); ?>)"><g class="cena-item item-<?php echo $n; ?><?php echo $fileira ? ' item-frente' : ''; ?>"><?php echo $l['item']; ?></g></g>
                    <?php endforeach;
                    foreach (array_slice($lixeiras, $fileira * 5, 5) as $j => $l):
                        $n = $fileira * 5 + $j + 1; $x = $colunas[$j];
                        $contorno = isset($l['contorno']) ? ' stroke="' . $l['contorno'] . '" stroke-width="1.5"' : ''; ?>
                <g class="cena-lixeira lixeira-<?php echo $n; ?>" transform="translate(<?php echo $x; ?> <?php echo $y + 10; ?>)">
                    <path d="M0 0 h56 l-5 66 q-1 6 -7 6 h-32 q-6 0 -7 -6 z" fill="<?php echo $l['cor']; ?>"<?php echo $contorno; ?>/>
                    <path d="M14 16 v44 M28 16 v44 M42 16 v44" stroke="<?php echo isset($l['contorno']) ? 'rgba(148,163,184,.35)' : 'rgba(255,255,255,.22)'; ?>" stroke-width="3" stroke-linecap="round"/>
                    <g class="cena-tampa">
                        <rect x="-4" y="-11" width="64" height="12" rx="5" fill="<?php echo $l['tampa']; ?>"<?php echo $contorno; ?>/>
                        <rect x="20" y="-17" width="16" height="7" rx="3" fill="<?php echo $l['tampa']; ?>"<?php echo $contorno; ?>/>
                    </g>
                    <circle cx="28" cy="32" r="11" fill="<?php echo isset($l['contorno']) ? 'rgba(148,163,184,.15)' : 'rgba(255,255,255,.2)'; ?>"/>
                    <use href="#simbolo-recicla" transform="translate(28 32)" style="color: <?php echo $l['simbolo'] ?? '#fff'; ?>"/>
                    <text x="28" y="<?php echo count($l['rot']) > 1 ? 84 : 88; ?>" text-anchor="middle" class="cena-rotulo"><?php foreach ($l['rot'] as $k => $linha): ?><tspan x="28" dy="<?php echo $k ? 11 : 0; ?>"><?php echo $linha; ?></tspan><?php endforeach; ?></text>
                </g>
                    <?php endforeach;
                endforeach; ?>

                <!-- Folhas flutuando -->
                <path class="cena-folha folha-1" d="M0 0 q10 -12 22 -4 q-6 14 -22 4 z" fill="#10b981"/>
                <path class="cena-folha folha-2" d="M0 0 q10 -12 22 -4 q-6 14 -22 4 z" fill="#a3e635"/>
                <path class="cena-folha folha-3" d="M0 0 q10 -12 22 -4 q-6 14 -22 4 z" fill="#059669"/>
                </g>
            </svg>
            <span class="hero-chip chip-flutua" style="top: -.9rem; left: 1.4rem;">
                <i class="bi bi-patch-check-fill" style="color: var(--rhe-600);"></i> Cada material no seu lugar
            </span>
            <span class="hero-chip chip-flutua chip-flutua-2" style="bottom: -.9rem; right: 1.4rem;">
                <i class="bi bi-arrow-repeat" style="color: var(--rhe-600);"></i> Economia circular
            </span>
        </div>
    </div>
</section>

<!-- Faixa animada com resíduos do catálogo -->
<?php if ($faixa): ?>
<section class="faixa-residuos mt-5" aria-label="Alguns resíduos do catálogo">
    <div class="faixa-trilho">
        <?php for ($copia = 0; $copia < 2; $copia++): foreach ($faixa as $item):
            $tipo = TIPOS_RESIDUO[$item['tipo_material']] ?? null; ?>
            <a class="faixa-item" href="pages/residuos.php?id=<?php echo (int) $item['id_residuo']; ?>" <?php echo $copia ? 'tabindex="-1" aria-hidden="true"' : ''; ?>>
                <img src="<?php echo imagem_residuo($item['nome'], $item['tipo_material']); ?>" alt="" loading="lazy">
                <span><?php echo htmlspecialchars($item['nome']); ?></span>
                <?php if ($tipo): ?><i class="bi <?php echo $tipo['icone']; ?>" title="<?php echo htmlspecialchars($tipo['rotulo']); ?>"></i><?php endif; ?>
            </a>
        <?php endforeach; endfor; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================
     ATALHOS
     ============================================================ -->
<section class="mt-5 pt-3 reveal">
    <div class="text-center mb-4">
        <div class="section-tag">Comece por aqui</div>
        <h2 class="mt-1">O que você quer fazer hoje?</h2>
    </div>
    <div class="row g-4">
        <?php
        $atalhos = [
            ['foto' => 'home-pesquise.jpg', 'alt' => 'Lixeiras coloridas de coleta seletiva para papel, plástico e latas em um parque', 'icone' => 'bi-search',
             'titulo' => 'Pesquise resíduos', 'texto' => 'Veja a classe, o tipo e a forma correta de descartar cada material.',
             'link' => 'pages/residuos.php', 'acao' => 'Pesquisar'],
            ['foto' => 'home-compartilhe.jpg', 'alt' => 'Pessoas trocando roupas em uma feira de troca', 'icone' => 'bi-arrow-left-right',
             'titulo' => 'Compartilhe itens', 'texto' => 'Ofereça o que pode ser reaproveitado e encontre materiais de outras pessoas.',
             'link' => 'pages/compartilhamentos.php', 'acao' => 'Ver compartilhamentos'],
            ['foto' => 'home-aprenda.jpg', 'alt' => 'Criança plantando em uma horta com composto orgânico na escola', 'icone' => 'bi-lightbulb',
             'titulo' => 'Aprenda brincando', 'texto' => 'Conteúdos, quizzes e um jogo para aprender sobre resíduos e sustentabilidade.',
             'link' => 'pages/quiz.php', 'acao' => 'Fazer quiz'],
        ];
        foreach ($atalhos as $atalho): ?>
            <div class="col-md-4">
                <a href="<?php echo $atalho['link']; ?>" class="text-decoration-none text-dark">
                    <article class="card card-feature">
                        <div class="photo">
                            <img src="<?php echo BASE_URL; ?>img/fotos/home/<?php echo $atalho['foto']; ?>?v=2" alt="<?php echo htmlspecialchars($atalho['alt']); ?>" loading="lazy">
                        </div>
                        <span class="icon-chip"><i class="bi <?php echo $atalho['icone']; ?>"></i></span>
                        <div class="card-body">
                            <h3 class="h5 card-title"><?php echo $atalho['titulo']; ?></h3>
                            <p class="card-text text-muted small"><?php echo $atalho['texto']; ?></p>
                            <span class="link-arrow"><?php echo $atalho['acao']; ?> <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </article>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================================================
     COMO FUNCIONA: passos + celular animado
     ============================================================ -->
<section class="mt-5 pt-4 reveal">
    <div class="row align-items-center g-5">
        <div class="col-lg-6 order-2 order-lg-2">
            <div class="celular-mock mx-auto" aria-hidden="true">
                <div class="celular-tela">
                    <div class="celular-topo"><span></span></div>
                    <div class="celular-app">
                        <div class="celular-logo"><img src="<?php echo BASE_URL; ?>img/logo-icon.svg" alt="" width="22" height="22"> Rhesys</div>
                        <div class="celular-busca"><i class="bi bi-search"></i><span class="digitando">pilha</span></div>
                        <div class="celular-resultado">
                            <img src="<?php echo BASE_URL; ?>img/fotos/residuos/pilha.jpg" alt="">
                            <div>
                                <strong>Pilha</strong>
                                <span class="badge-classe badge-classe-sm classe-1"><i class="bi bi-exclamation-triangle-fill"></i> Classe I</span>
                            </div>
                        </div>
                        <div class="celular-dica">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>Entregue em um ponto de coleta de pilhas e baterias</span>
                        </div>
                        <div class="celular-dica dica-2">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Logística reversa: a loja é obrigada a receber</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 order-1 order-lg-1">
            <div class="section-tag">Como funciona</div>
            <h2 class="mt-1 mb-3">Descartar certo em três passos</h2>
            <p class="text-muted">
                O Rhesys reúne orientações conforme as normas brasileiras (ABNT NBR 10004 e a
                Lei nº 12.305/2010) para você saber, em segundos, o que fazer com cada material.
            </p>
            <ol class="passos mt-4">
                <li>
                    <span class="passo-num">1</span>
                    <div><strong>Busque o material</strong><p class="small text-muted mb-0">Digite o nome ou escolha o tipo: metal, plástico, eletrônico...</p></div>
                </li>
                <li>
                    <span class="passo-num">2</span>
                    <div><strong>Veja como descartar</strong><p class="small text-muted mb-0">Classe, cuidados, reciclagem e se tem logística reversa.</p></div>
                </li>
                <li>
                    <span class="passo-num">3</span>
                    <div><strong>Entregue no lugar certo</strong><p class="small text-muted mb-0">Encontre no mapa os pontos de coleta que aceitam o resíduo.</p></div>
                </li>
            </ol>
            <a href="pages/residuos.php" class="btn btn-success mt-2"><i class="bi bi-search me-1"></i>Começar agora</a>
        </div>
    </div>
</section>

<!-- ============================================================
     CHAMADA FINAL
     ============================================================ -->
<section class="mt-5 pt-2 reveal">
    <div class="cta-foto text-center">
        <img class="cta-foto-bg" src="<?php echo BASE_URL; ?>img/fotos/home/home-mutirao.jpg" alt="" loading="lazy">
        <span class="cta-folha f1" aria-hidden="true"><i class="bi bi-tree"></i></span>
        <span class="cta-folha f2" aria-hidden="true"><i class="bi bi-recycle"></i></span>
        <span class="cta-folha f3" aria-hidden="true"><i class="bi bi-droplet"></i></span>
        <div class="cta-conteudo">
            <span class="crumb">Faça a sua parte</span>
            <h2 class="text-white mt-2 mb-2">Pequenas atitudes, grande impacto.</h2>
            <p class="mb-4">Separe, reaproveite e leve cada resíduo ao ponto de coleta certo.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="pages/pontos_coleta.php" class="btn btn-light btn-lg rounded-pill px-4 fw-semibold">
                    <i class="bi bi-geo-alt me-1"></i>Ver pontos de coleta
                </a>
                <a href="pages/jogo.php" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-semibold">
                    <i class="bi bi-controller me-1"></i>Jogar
                </a>
            </div>
        </div>
    </div>
    <p class="foto-credito text-center mt-2 mb-0">
        Fotos: epSos.de (CC BY 2.0), Conception Levy (CC BY-SA 2.0), Joel Vargas/Prefeitura de Porto Alegre e
        Ocean Blue Project (CC BY-SA 4.0), via Wikimedia Commons.
    </p>
</section>

<script>
// Contadores animados dos números do topo (respeita "reduzir movimento")
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('[data-contar]').forEach(function (el) {
        var alvo = parseInt(el.getAttribute('data-contar'), 10) || 0;
        var inicio = null;
        el.textContent = '0';
        function passo(t) {
            if (!inicio) inicio = t;
            var p = Math.min((t - inicio) / 1200, 1);
            el.textContent = Math.round(alvo * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(passo);
        }
        requestAnimationFrame(passo);
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
