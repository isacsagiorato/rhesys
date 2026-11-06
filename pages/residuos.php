<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';

// Foto ilustrativa conforme a classificação (ABNT NBR 10004)
function imagem_residuo($nome_classificacao, $nome_residuo = '', $palavras_chave = '') {
    $termos = mb_strtolower($nome_residuo . ' ' . $palavras_chave, 'UTF-8');
    if (mb_strpos($termos, 'orgânico') !== false || mb_strpos($termos, 'organico') !== false) {
        return BASE_URL . 'img/fotos/lixo-organico.jpg';
    }
    $n = mb_strtolower((string) $nome_classificacao, 'UTF-8');
    if (mb_strpos($n, 'perigoso') !== false) {
        return BASE_URL . 'img/fotos/eletronicos.jpg';   // Classe I - Perigosos
    }
    if (mb_strpos($n, 'não inerte') !== false || mb_strpos($n, 'nao inerte') !== false) {
        return BASE_URL . 'img/fotos/reciclaveis.jpg';   // Classe II A - Não Inertes
    }
    if (mb_strpos($n, 'inerte') !== false) {
        return BASE_URL . 'img/fotos/coleta.jpg';        // Classe II B - Inertes
    }
    return BASE_URL . 'img/fotos/coleta.jpg';
}

$titulo_pagina = 'Resíduos';
require __DIR__ . '/../includes/header.php';

// Parâmetros
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$id_residuo = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- Detalhe de um resíduo específico ---
if ($id_residuo > 0) {
    $sql = "SELECT r.*, c.nome AS classificacao_nome, c.descricao AS classificacao_descricao
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            WHERE r.id_residuo = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
    $stmt->execute();
    $residuo = $stmt->fetch();

    if (!$residuo) {
        echo '<div class="alert alert-warning">Resíduo não encontrado.</div>';
        echo '<a href="residuos.php" class="btn btn-success">Voltar</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    ?>

    <a href="residuos.php" class="btn btn-outline-success mb-3">&larr; Voltar à lista</a>

    <div class="detail-photo mb-4">
        <img src="<?php echo imagem_residuo($residuo['classificacao_nome'], $residuo['nome'], $residuo['palavras_chave']); ?>" alt="Foto ilustrativa: <?php echo htmlspecialchars($residuo['classificacao_nome']); ?>">
    </div>

    <div class="info-card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                <h1 class="mb-0 me-2 fs-2"><?php echo htmlspecialchars($residuo['nome']); ?></h1>
                <span class="badge-soft"><?php echo htmlspecialchars($residuo['classificacao_nome']); ?></span>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <span class="badge-lr">Logística Reversa</span>
                <?php endif; ?>
            </div>

            <p class="text-muted"><?php echo htmlspecialchars($residuo['classificacao_descricao']); ?></p>

            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-card-text"></i></span> Descrição</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['descricao'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-trash3"></i></span> Forma de Descarte</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['forma_descarte'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <div class="card-body p-4">
                            <h5 class="card-title"><span class="icon-chip-sm"><i class="bi bi-recycle"></i></span> Reciclagem</h5>
                            <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars($residuo['reciclagem'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php if ($residuo['possui_logistica_reversa']): ?>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="card-body p-4">
                                <h5 class="card-title"><span class="icon-chip-sm" style="background: #fef3c7; color: #92400e;"><i class="bi bi-arrow-repeat"></i></span> Logística Reversa</h5>
                                <p class="card-text">
                                    Este resíduo está sujeito à logística reversa conforme a Lei nº 12.305/2010.
                                    Deve ser entregue em pontos de coleta específicos para reaproveitamento
                                    ou destinação ambientalmente adequada.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($residuo['palavras_chave'])): ?>
                <div class="mt-4">
                    <strong>Palavras-chave:</strong>
                    <?php foreach (explode(',', $residuo['palavras_chave']) as $palavra): ?>
                        <span class="badge-soft me-1 mt-1"><?php echo htmlspecialchars(trim($palavra)); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Pontos de coleta que aceitam este resíduo -->
            <?php
            $sql_pontos = "SELECT p.* FROM ponto_coleta p
                           INNER JOIN residuo_ponto_coleta rpc ON p.id_ponto = rpc.id_ponto
                           WHERE rpc.id_residuo = :id";
            $stmt_pontos = $pdo->prepare($sql_pontos);
            $stmt_pontos->bindValue(':id', $id_residuo, PDO::PARAM_INT);
            $stmt_pontos->execute();
            $pontos = $stmt_pontos->fetchAll();
            ?>
            <?php if (count($pontos) > 0): ?>
                <div class="mt-4">
                    <h5>Pontos de Coleta que aceitam este resíduo</h5>
                    <ul class="list-group">
                        <?php foreach ($pontos as $ponto): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($ponto['nome']); ?></strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($ponto['endereco']); ?></span>
                                <?php if ($ponto['telefone']): ?>
                                    <br><span class="text-muted">Tel: <?php echo htmlspecialchars($ponto['telefone']); ?></span>
                                <?php endif; ?>
                                <?php if ($ponto['horario_funcionamento']): ?>
                                    <br><span class="text-muted">Horário: <?php echo htmlspecialchars($ponto['horario_funcionamento']); ?></span>
                                <?php endif; ?>
                                <?php if ($ponto['latitude'] && $ponto['longitude']): ?>
                                    <br><a href="https://www.openstreetmap.org/?mlat=<?php echo $ponto['latitude']; ?>&mlon=<?php echo $ponto['longitude']; ?>&zoom=18" target="_blank" rel="noopener noreferrer" class="small">Ver no mapa</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="pontos_coleta.php?id_residuo=<?php echo $id_residuo; ?>" class="link-arrow small mt-3">Ver estes pontos no mapa <i class="bi bi-arrow-right"></i></a>
                </div>
            <?php else: ?>
                <div class="alert alert-info mt-4 mb-0">Nenhum ponto de coleta vinculado a este resíduo até o momento.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// --- Listagem com busca ---
if ($busca !== '') {
    $sql = "SELECT r.*, c.nome AS classificacao_nome
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            WHERE r.nome LIKE :busca1
               OR r.palavras_chave LIKE :busca2
               OR r.descricao LIKE :busca3
            ORDER BY r.nome";
    // Escapa os curingas do LIKE (% e _) digitados pelo usuário,
    // para que sejam tratados como texto literal na busca
    $termo = '%' . addcslashes($busca, '%_\\') . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':busca1', $termo, PDO::PARAM_STR);
    $stmt->bindValue(':busca2', $termo, PDO::PARAM_STR);
    $stmt->bindValue(':busca3', $termo, PDO::PARAM_STR);
    $stmt->execute();
} else {
    $sql = "SELECT r.*, c.nome AS classificacao_nome
            FROM residuo r
            INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
            ORDER BY r.nome";
    $stmt = $pdo->query($sql);
}
$residuos = $stmt->fetchAll();
$filtros_organicos = [
    'orgânico' => 'Todos os orgânicos',
    'banana' => 'Banana',
    'casca' => 'Cascas',
    'cenoura' => 'Cenoura',
    'queijo' => 'Queijo',
    'resto de comida' => 'Restos de comida',
    'mato' => 'Mato e podas',
    'borra' => 'Borra de café',
    'ovo' => 'Cascas de ovo',
    'folhas' => 'Folhas secas',
];

// Cards de tipos de resíduos: atalhos que filtram a lista pelo material.
// Cada card usa o mesmo mecanismo da busca (LIKE em nome, palavras-chave e descrição).
$tipos_residuos = [
    [
        'rotulo' => 'Metais',
        'termo' => 'metal',
        'foto' => 'img/fotos/metais.jpg',
        'alt' => 'Latinhas de alumínio prensadas para reciclagem',
        'icone' => null,
        'descricao' => 'Latas de alumínio e aço, panelas, fios, ferramentas e sucatas.',
    ],
    [
        'rotulo' => 'Plásticos',
        'termo' => 'plastico',
        'foto' => null,
        'alt' => '',
        'icone' => 'bi-cup-straw',
        'descricao' => 'Garrafas PET, embalagens, potes, sacolas e utensílios.',
    ],
    [
        'rotulo' => 'Papel e Papelão',
        'termo' => 'papel',
        'foto' => null,
        'alt' => '',
        'icone' => 'bi-newspaper',
        'descricao' => 'Jornais, revistas, caixas, folhas de sulfite e cadernos.',
    ],
    [
        'rotulo' => 'Vidros',
        'termo' => 'vidro',
        'foto' => null,
        'alt' => '',
        'icone' => 'bi-cup',
        'descricao' => 'Garrafas, potes, frascos, copos e vidros planos.',
    ],
    [
        'rotulo' => 'Orgânicos',
        'termo' => 'orgânico',
        'foto' => 'img/fotos/lixo-organico.jpg',
        'alt' => 'Restos de alimentos e cascas em uma composteira',
        'icone' => null,
        'descricao' => 'Restos de comida, cascas, borra de café, folhas e podas.',
    ],
    [
        'rotulo' => 'Eletrônicos',
        'termo' => 'eletronico',
        'foto' => 'img/fotos/eletronicos.jpg',
        'alt' => 'Equipamentos eletrônicos descartados',
        'icone' => null,
        'descricao' => 'Celulares, computadores, cabos, pilhas e eletroeletrônicos.',
    ],
];

// Painel dinâmico do tipo selecionado: script específico desta página (incluído pelo footer)
$scripts_extras = [BASE_URL . 'js/tipos_residuos.js?v=1'];
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Lixeiras coloridas de coleta seletiva">
    <span class="crumb">Consulte antes de descartar</span>
    <h1 class="mt-2">Resíduos</h1>
    <p class="mt-2">
        Pesquise pelo nome, palavra-chave ou descrição e descubra a classificação
        e a forma correta de descarte.
    </p>
</section>

<!-- Painel dinâmico do tipo de resíduo: fica oculto ao abrir a página e só
     aparece, com animação, quando um card de tipo é clicado (js/tipos_residuos.js) -->
<section id="tipo-spotlight" class="organic-guide tipo-spotlight mt-4" hidden aria-live="polite" aria-labelledby="spotlight-titulo">
    <button type="button" id="spotlight-fechar" class="spotlight-close" aria-label="Fechar painel e limpar filtro" title="Fechar e limpar filtro">
        <i class="bi bi-x-lg"></i>
    </button>
    <div class="row g-0 align-items-stretch">
        <div class="col-lg-7">
            <div class="organic-content">
                <span class="badge-lr"><i class="bi bi-recycle me-1"></i>Tipo selecionado</span>
                <h2 id="spotlight-titulo" class="mt-3"></h2>
                <p class="text-muted mb-0" id="spotlight-descricao"></p>
                <h3 class="h6 mt-4" id="spotlight-filtros-titulo" hidden>Filtre os resíduos orgânicos</h3>
                <div class="organic-filters mt-3" id="spotlight-filtros" hidden></div>
                <p class="text-muted small mt-4 mb-0">
                    <i class="bi bi-arrow-down-circle me-1"></i>A lista de resíduos abaixo já está filtrada por este tipo.
                </p>
            </div>
        </div>
        <div class="col-lg-5">
            <img id="spotlight-foto" class="organic-photo" alt="" hidden>
            <div id="spotlight-icone" class="spotlight-icon" hidden aria-hidden="true"><i class="bi"></i></div>
        </div>
    </div>
</section>

<div class="search-wrap mt-4 reveal" id="resultados">
    <form method="GET">
        <div class="search-pill">
            <i class="bi bi-search"></i>
            <input type="text" name="busca"
                   placeholder="Ex.: pilha, garrafa PET, papelão..."
                   value="<?php echo htmlspecialchars($busca); ?>">
            <button class="btn btn-success" type="submit">Buscar</button>
            <?php if ($busca !== ''): ?>
                <a href="residuos.php" class="btn btn-outline-secondary border-0 me-1">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
    <p class="text-muted small mt-3 mb-0" id="contagem-resultados">
        <i class="bi bi-funnel"></i>
        <?php echo count($residuos); ?> resultado(s) encontrado(s)<?php if ($busca !== ''): ?> para
        “<strong><?php echo htmlspecialchars($busca); ?></strong>”<?php endif; ?>.
    </p>
    <?php if (usuario_logado()): ?>
        <!-- Área de gestão (somente logado): cadastros do Sprint 2 -->
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="cadastro_residuo.php" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Cadastrar resíduo
            </a>
            <a href="cadastro_classificacao.php" class="btn btn-outline-success btn-sm">
                <i class="bi bi-tags me-1"></i>Cadastrar classificação
            </a>
        </div>
    <?php endif; ?>
</div>

<section class="tipos-residuos mt-5 reveal" aria-labelledby="titulo-tipos">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h2 class="h4 mb-0" id="titulo-tipos">Explore por tipo de resíduo</h2>
        <span class="text-muted small"><i class="bi bi-hand-index-thumb me-1"></i>Toque em um card para filtrar a lista</span>
    </div>
    <div class="row g-4">
        <?php foreach ($tipos_residuos as $tipo): ?>
            <div class="col-sm-6 col-lg-4">
                <a href="residuos.php?busca=<?php echo rawurlencode($tipo['termo']); ?>#resultados"
                   class="text-decoration-none text-dark"
                   data-tipo="<?php echo htmlspecialchars($tipo['termo']); ?>"
                   <?php echo $busca === $tipo['termo'] ? 'aria-current="true"' : ''; ?>>
                    <div class="card card-tipo<?php echo $busca === $tipo['termo'] ? ' active' : ''; ?>">
                        <?php if ($tipo['foto'] !== null): ?>
                            <div class="photo">
                                <img src="<?php echo BASE_URL . $tipo['foto']; ?>"
                                     alt="<?php echo htmlspecialchars($tipo['alt']); ?>"
                                     onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>img/fotos/reciclaveis.jpg';">
                            </div>
                        <?php else: ?>
                            <div class="photo tipo-icon" aria-hidden="true"><i class="bi <?php echo $tipo['icone']; ?>"></i></div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h3 class="h5 card-title"><?php echo htmlspecialchars($tipo['rotulo']); ?></h3>
                            <p class="card-text text-muted small mb-2"><?php echo htmlspecialchars($tipo['descricao']); ?></p>
                            <span class="link-arrow small">Ver resíduos <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Agrupamentos montados pelo JS quando um card de tipo é clicado:
     relacionados ao tipo primeiro e, no final, os demais resíduos -->
<section id="grupo-relacionados" class="mt-5" hidden aria-labelledby="titulo-relacionados">
    <h2 class="h4 mb-3" id="titulo-relacionados">Resíduos Relacionados</h2>
    <div class="row g-4" id="lista-relacionados"></div>
</section>
<section id="grupo-outros" class="mt-5" hidden aria-labelledby="titulo-outros">
    <h2 class="h4 mb-3" id="titulo-outros">Outros Tipos de Resíduos</h2>
    <div class="row g-4" id="lista-outros"></div>
</section>

<?php if (count($residuos) === 0): ?>
    <div class="alert alert-info mt-4">
        <i class="bi bi-search me-1"></i> Nenhum resíduo encontrado para "<strong><?php echo htmlspecialchars($busca); ?></strong>".
    </div>
<?php else: ?>
    <div class="row g-4 mt-2 reveal" id="lista-residuos">
        <?php foreach ($residuos as $res): ?>
            <?php $texto_filtro = mb_strtolower($res['nome'] . ' ' . $res['classificacao_nome'] . ' ' . $res['descricao'] . ' ' . $res['palavras_chave'], 'UTF-8'); ?>
            <div class="col-md-6 col-lg-4" data-filtro="<?php echo htmlspecialchars($texto_filtro); ?>">
                <a href="residuos.php?id=<?php echo $res['id_residuo']; ?>" class="text-decoration-none text-dark">
                    <div class="card card-residuo">
                        <div class="photo">
                            <img src="<?php echo imagem_residuo($res['classificacao_nome'], $res['nome'], $res['palavras_chave']); ?>" alt="Foto ilustrativa: <?php echo htmlspecialchars($res['nome']); ?>">
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($res['nome']); ?></h5>
                                <?php if ($res['possui_logistica_reversa']): ?>
                                    <span class="badge-lr">LR</span>
                                <?php endif; ?>
                            </div>
                            <span class="badge-soft d-inline-block mb-2"><?php echo htmlspecialchars($res['classificacao_nome']); ?></span>
                            <p class="card-text text-muted small clamp-3">
                                <?php echo htmlspecialchars($res['descricao']); ?>
                            </p>
                            <span class="link-arrow small">Ver detalhes <i class="bi bi-arrow-right"></i></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Exibido pelo JS quando o filtro de um card não encontra nenhum resíduo -->
<div class="alert alert-info mt-4" id="sem-resultados-js" hidden>
    <i class="bi bi-search me-1"></i> Nenhum resíduo encontrado para o filtro selecionado.
</div>

<?php
// Dados dos tipos para o painel dinâmico (consumidos por js/tipos_residuos.js)
$tipos_js = [];
foreach ($tipos_residuos as $tipo) {
    $tipos_js[$tipo['termo']] = [
        'rotulo' => $tipo['rotulo'],
        'descricao' => $tipo['descricao'],
        'foto' => $tipo['foto'] !== null ? BASE_URL . $tipo['foto'] : null,
        'alt' => $tipo['alt'],
        'icone' => $tipo['icone'],
    ];
}
?>
<script>
window.RHESYS_TIPOS = <?php echo json_encode($tipos_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.RHESYS_FILTROS_ORGANICOS = <?php echo json_encode($filtros_organicos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>