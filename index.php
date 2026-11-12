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

<section class="mt-5 pt-3 reveal" id="guia-residuos">
    <div class="text-center mb-4">
        <div class="section-tag">Aprenda o básico</div>
        <h2 class="mt-1 mb-3">O que são resíduos sólidos?</h2>
        <p class="text-muted mx-auto" style="max-width: 760px;">
            São os materiais que sobram das atividades do dia a dia — de casas, comércios e cidades.
            Com o crescimento urbano, a quantidade gerada só aumenta, e o descarte errado pode poluir
            o solo, contaminar a água e colocar a saúde pública em risco. Mas, com a destinação correta,
            boa parte deles deixa de ser lixo e volta a ser recurso.
        </p>
    </div>
    <p class="text-center text-muted mb-4">
        <i class="bi bi-book me-1"></i> A norma técnica <strong>ABNT NBR 10004</strong> classifica os resíduos
        pelo risco que oferecem à saúde e ao meio ambiente:
    </p>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="info-card p-4 h-100">
                <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem; background: #fee2e2; color: #b91c1c;"><i class="bi bi-exclamation-triangle-fill"></i></span>
                <h5 class="card-title">Classe I — Perigosos</h5>
                <p class="small text-muted mb-2">
                    Oferecem risco à saúde ou ao meio ambiente: podem ser inflamáveis,
                    corrosivos, tóxicos, reativos ou patogênicos.
                </p>
                <p class="small mb-0">
                    <strong>Exemplos:</strong> pilhas, baterias e lâmpadas fluorescentes —
                    sempre em pontos de coleta específicos.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card p-4 h-100">
                <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem; background: #fef3c7; color: #b45309;"><i class="bi bi-tree-fill"></i></span>
                <h5 class="card-title">Classe II A — Não inertes</h5>
                <p class="small text-muted mb-2">
                    Não são perigosos, mas se alteram com o tempo — podem ser biodegradáveis,
                    combustíveis ou se dissolver na água.
                </p>
                <p class="small mb-0">
                    <strong>Exemplos:</strong> restos de alimentos, papel, papelão e madeira.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card p-4 h-100">
                <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem; background: #e5e7eb; color: #4b5563;"><i class="bi bi-bricks"></i></span>
                <h5 class="card-title">Classe II B — Inertes</h5>
                <p class="small text-muted mb-2">
                    Praticamente não se transformam em contato com a água e não liberam
                    substâncias que causem impacto ambiental relevante.
                </p>
                <p class="small mb-0">
                    <strong>Exemplos:</strong> entulho de obra, concreto, tijolos e cerâmicas.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="mt-5 pt-3 reveal" id="cores-conama">
    <div class="text-center mb-4">
        <div class="section-tag">Descarte na cor certa</div>
        <h2 class="mt-1 mb-3">As cores da coleta seletiva (CONAMA nº 275/2001)</h2>
        <p class="text-muted mx-auto" style="max-width: 760px;">
            Essa resolução padronizou as cores dos coletores em todo o Brasil.
            Viu a cor, lembrou do material, descartou certo — sem precisar de conhecimento técnico.
        </p>
    </div>
    <div class="row g-3">
        <?php
        // Padrão cromático da Resolução CONAMA nº 275/2001
        $cores_conama = [
            // Recicláveis usam a logo universal da reciclagem (as 3 setas, bi-recycle);
            // frações especiais usam ícone próprio (perigosos, saúde, radioativos, não recicláveis).
            // 'escuro' => cores claras, que recebem texto/ícone escuros (amarelo, laranja, branco).
            ['hex' => '#2563eb', 'nome' => 'Azul',     'material' => 'Papel e papelão',     'icone' => 'bi-recycle'],
            ['hex' => '#dc2626', 'nome' => 'Vermelho', 'material' => 'Plásticos',         'icone' => 'bi-recycle'],
            ['hex' => '#16a34a', 'nome' => 'Verde',    'material' => 'Vidro',             'icone' => 'bi-recycle'],
            ['hex' => '#f5b301', 'nome' => 'Amarelo',  'material' => 'Metais',            'icone' => 'bi-recycle', 'escuro' => true],
            ['hex' => '#1f2937', 'nome' => 'Preto',    'material' => 'Madeira',           'icone' => 'bi-recycle'],
            ['hex' => '#f97316', 'nome' => 'Laranja',  'material' => 'Resíduos perigosos', 'icone' => 'bi-exclamation-triangle-fill', 'escuro' => true],
            ['hex' => '#ffffff', 'nome' => 'Branco',   'material' => 'Resíduos de saúde',  'icone' => 'bi-capsule',  'escuro' => true],
            ['hex' => '#7c3aed', 'nome' => 'Roxo',     'material' => 'Radioativos',        'icone' => 'bi-radioactive'],
            ['hex' => '#7b4b2a', 'nome' => 'Marrom',   'material' => 'Orgânicos',          'icone' => 'bi-recycle'],
            ['hex' => '#6b7280', 'nome' => 'Cinza',    'material' => 'Não recicláveis',    'icone' => 'bi-trash-fill'],
        ];
        foreach ($cores_conama as $cor): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="conama-card h-100<?php echo !empty($cor['escuro']) ? ' conama-card-escura' : ''; ?>"
                 style="background: <?php echo htmlspecialchars($cor['hex'], ENT_QUOTES, 'UTF-8'); ?>;">
                <span class="icone-conama"><i class="bi <?php echo htmlspecialchars($cor['icone'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
                <div class="material-nome"><?php echo htmlspecialchars($cor['material'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="cor-nome"><?php echo htmlspecialchars($cor['nome'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small text-center mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i> A cor cinza também serve para resíduos misturados ou contaminados,
        que não podem ser separados para reciclagem.
    </p>
</section>

<section class="mt-5 pt-3 reveal" id="pnrs">
    <div class="row align-items-center g-5">
        <div class="col-lg-7">
            <div class="section-tag">A lei que organiza a gestão</div>
            <h2 class="mt-1 mb-3">O que é a PNRS?</h2>
            <p class="text-muted">
                A <strong>Política Nacional de Resíduos Sólidos (PNRS)</strong>, instituída pela
                <strong>Lei nº 12.305/2010</strong>, define as diretrizes para reduzir a geração de
                resíduos, incentivar a reciclagem e garantir a destinação ambientalmente adequada
                dos rejeitos. Mas a lei sozinha não resolve: a informação precisa chegar às pessoas
                de forma simples — e é nisso que o Rhesys quer ajudar.
            </p>
            <div class="d-flex flex-column gap-3 mt-4">
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <strong>Responsabilidade compartilhada</strong>
                        <p class="small text-muted mb-0">
                            Governo, empresas e cidadãos dividem a responsabilidade pelo ciclo de
                            vida dos produtos — da fabricação ao descarte.
                        </p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-arrow-repeat"></i></div>
                    <div>
                        <strong>Logística reversa</strong>
                        <p class="small text-muted mb-0">
                            Pilhas, baterias, lâmpadas fluorescentes e eletrônicos voltam ao setor
                            produtivo para reaproveitamento, em vez de virar lixo.
                            <a href="<?php echo BASE_URL; ?>pages/pontos_coleta.php">Veja os pontos de coleta</a>.
                        </p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-dot"><i class="bi bi-mortarboard"></i></div>
                    <div>
                        <strong>Educação e acesso à informação</strong>
                        <p class="small text-muted mb-0">
                            A participação da população depende de orientação clara sobre como separar
                            e descartar cada material — conteúdo que você encontra aqui.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="hero-photo ratio ratio-4x3">
                <img src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Lixeiras coloridas de coleta seletiva">
            </div>
            <div class="info-card p-3 mt-4">
                <div class="small fw-semibold mb-2">
                    <i class="bi bi-truck me-1"></i> Tipos de coleta (ABNT NBR 13463)
                </div>
                <ul class="small text-muted mb-0">
                    <li><strong>Regular:</strong> rotina da prefeitura (lixo domiciliar e limpeza urbana).</li>
                    <li><strong>Especial:</strong> materiais que exigem manejo diferenciado.</li>
                    <li><strong>Seletiva:</strong> recicláveis separados para voltar à indústria.</li>
                    <li><strong>Particular:</strong> contratada por empresas, comércios e condomínios.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="mt-5 pt-3 reveal" id="economia-circular">
    <div class="text-center mb-4">
        <div class="section-tag">Do descarte ao reuso</div>
        <h2 class="mt-1 mb-3">O que é economia circular?</h2>
        <p class="text-muted mx-auto" style="max-width: 760px;">
            É a alternativa ao modelo tradicional de "produzir, usar e jogar fora": manter os
            materiais em uso pelo maior tempo possível, para que voltem ao ciclo produtivo.
        </p>
    </div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="info-card p-4 h-100">
                <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem; background: #fee2e2; color: #b91c1c;"><i class="bi bi-arrow-right"></i></span>
                <h5 class="card-title">Modelo linear</h5>
                <p class="small text-muted mb-0">
                    <strong>Extrai — produz — descarta.</strong> Os recursos são usados uma única vez,
                    viram lixo e seguem para aterros e lixões.
                </p>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="info-card p-4 h-100">
                <span class="icon-chip-sm mb-3" style="width: 46px; height: 46px; font-size: 1.25rem;"><i class="bi bi-arrow-repeat"></i></span>
                <h5 class="card-title">Economia circular</h5>
                <p class="small text-muted mb-3">
                    <strong>Reutilizar, reciclar e reaproveitar.</strong> O resíduo deixa de ser lixo
                    e volta ao processo produtivo como matéria-prima, reduzindo desperdício e impactos.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge-soft">Menos resíduos em aterros</span>
                    <span class="badge-soft">Menos extração de recursos naturais</span>
                    <span class="badge-soft">Emprego e renda na reciclagem</span>
                </div>
            </div>
        </div>
    </div>
    <p class="text-center mt-4 mb-0">
        <i class="bi bi-lightbulb me-1"></i> Pratique economia circular no Rhesys:
        <a href="<?php echo BASE_URL; ?>pages/compartilhamentos.php">veja itens que outras pessoas estão oferecendo</a>
        ou compartilhe o que você não usa mais.
    </p>
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
