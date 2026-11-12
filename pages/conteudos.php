<?php
require_once __DIR__ . '/../config/config.php';

$titulo_pagina = 'Conteúdos';
require __DIR__ . '/../includes/header.php';
?>

<section class="pt-2 pb-1 reveal">
    <div class="section-tag">Guia educativo</div>
    <h1 class="mt-1 mb-2">Aprenda a descartar certo</h1>
    <p class="text-muted mb-0">O essencial sobre resíduos, coleta seletiva e economia circular — direto ao ponto.</p>
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
    <div class="row g-4">
        <?php
        // Padrão cromático da Resolução CONAMA nº 275/2001.
        // Todos os cartões usam a logo universal da reciclagem (as 3 setas, bi-recycle);
        // ao clicar, abrem um modal com uma breve explicação do material.
        // 'escuro' => cores claras, que recebem texto/ícone escuros (amarelo, laranja, branco).
        $cores_conama = [
            ['hex' => '#2563eb', 'nome' => 'Azul',     'material' => 'Papel e papelão'],
            ['hex' => '#dc2626', 'nome' => 'Vermelho', 'material' => 'Plásticos'],
            ['hex' => '#16a34a', 'nome' => 'Verde',    'material' => 'Vidro'],
            ['hex' => '#f5b301', 'nome' => 'Amarelo',  'material' => 'Metais',            'escuro' => true],
            ['hex' => '#1f2937', 'nome' => 'Preto',    'material' => 'Madeira'],
            ['hex' => '#f97316', 'nome' => 'Laranja',  'material' => 'Resíduos perigosos', 'escuro' => true],
            ['hex' => '#ffffff', 'nome' => 'Branco',   'material' => 'Resíduos de saúde', 'escuro' => true],
            ['hex' => '#7c3aed', 'nome' => 'Roxo',     'material' => 'Radioativos'],
            ['hex' => '#7b4b2a', 'nome' => 'Marrom',   'material' => 'Orgânicos'],
            ['hex' => '#6b7280', 'nome' => 'Cinza',    'material' => 'Não recicláveis'],
        ];

        // Breve explicação mostrada ao tocar em cada cartão (modal).
        // Chaveada pelo nome da cor (CONAMA nº 275/2001).
        $explicacoes_cores = [
            'Azul'     => 'Jornais, revistas, caixas e embalagens de papel. Descarte sempre seco e limpo para facilitar a reciclagem.',
            'Vermelho' => 'Garrafas PET, sacolas e embalagens plásticas. Lave e seque antes de descartar para não contaminar o lote.',
            'Verde'    => 'Garrafas, potes e frascos de vidro. O vidro é reciclável infinitas vezes; nunca misture cacos com o lixo comum.',
            'Amarelo'  => 'Latas de alumínio, aço e outros metais. São 100% recicláveis e podem voltar à indústria quantas vezes for preciso.',
            'Preto'    => 'Móveis, paletes e restos de madeira. Devem ir para reaproveitamento ou reciclagem específica.',
            'Laranja'  => 'Pilhas, baterias, lâmpadas e produtos químicos. Exigem descarte em pontos de coleta específicos (logística reversa).',
            'Branco'   => 'Materiais de hospitais e serviços de saúde. Têm coleta especializada — jamais vão para o lixo comum.',
            'Roxo'     => 'Rejeitos radioativos. Coleta e descarte exclusivos por órgãos especializados e licenciados.',
            'Marrom'   => 'Restos de alimentos e material orgânico. Podem virar adubo pela compostagem em vez de irem ao aterro.',
            'Cinza'    => 'Resíduos que não podem ser reciclados ou reutilizados (ex.: papel higiênico, fraldas). Seguem para aterro sanitário.',
        ];

        foreach ($cores_conama as $cor): ?>
        <div class="col-6 col-md-6 col-lg-4">
            <div class="conama-card h-100<?php echo !empty($cor['escuro']) ? ' conama-card-escura' : ''; ?>"
                 role="button"
                 tabindex="0"
                 data-bs-toggle="modal"
                 data-bs-target="#modalConama"
                 data-material="<?php echo htmlspecialchars($cor['material'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-nome="<?php echo htmlspecialchars($cor['nome'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-hex="<?php echo htmlspecialchars($cor['hex'], ENT_QUOTES, 'UTF-8'); ?>"
                 data-escura="<?php echo !empty($cor['escuro']) ? '1' : '0'; ?>"
                 data-explicacao="<?php echo htmlspecialchars($explicacoes_cores[$cor['nome']] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                 style="background: <?php echo htmlspecialchars($cor['hex'], ENT_QUOTES, 'UTF-8'); ?>;">
                <span class="icone-conama"><i class="bi bi-recycle" aria-hidden="true"></i></span>
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
<!-- Modal: breve explicação do cartão de cor (CONAMA nº 275/2001) -->
<div class="modal fade" id="modalConama" tabindex="-1" aria-labelledby="modalConamaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="modal-swatch" id="modalConamaCor" aria-hidden="true"></span>
                    <div>
                        <h5 class="modal-title mb-0" id="modalConamaTitulo"></h5>
                        <span class="small text-muted" id="modalConamaCorNome"></span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="modalConamaTexto"></p>
            </div>
        </div>
    </div>
</div>

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

<?php require __DIR__ . '/../includes/footer.php'; ?>