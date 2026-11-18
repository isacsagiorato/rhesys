<?php
/**
 * Resíduos — listagem com busca e filtros (RF03) e página de detalhes (RF04–RF08).
 *
 * Filtros combináveis, todos na URL (funcionam sem JavaScript):
 *   busca  — texto (LIKE em nome, palavras-chave e descrição);
 *   tipo   — tipo de material (config/residuos.php: TIPOS_RESIDUO);
 *   classe — 1, 2a ou 2b (ABNT NBR 10004);
 *   lr     — 1 = somente resíduos com logística reversa.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/residuos.php';

// Item "guarda-chuva" usado só como referência dos orgânicos (não aparece na lista)
const RESIDUO_GENERICO = 'Resíduos orgânicos';

$id_residuo = (int) ($_GET['id'] ?? 0);

// ============================================================
// DETALHE DE UM RESÍDUO
// ============================================================
if ($id_residuo > 0) {
    $stmt = $pdo->prepare(
        'SELECT r.*, c.nome AS classificacao_nome, c.descricao AS classificacao_descricao
         FROM residuo r
         INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
         WHERE r.id_residuo = :id'
    );
    $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
    $stmt->execute();
    $residuo = $stmt->fetch();

    $titulo_pagina = $residuo ? $residuo['nome'] : 'Resíduo não encontrado';
    require __DIR__ . '/../includes/header.php';

    if (!$residuo) {
        echo '<div class="alert alert-warning">Resíduo não encontrado.</div>';
        echo '<a href="residuos.php" class="btn btn-success">Voltar à lista</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    $classe = classe_residuo($residuo['classificacao_nome']);
    $tipo = $residuo['tipo_material'] ?? null;
    $tipo_info = TIPOS_RESIDUO[$tipo] ?? null;
    $caminho_foto = caminho_foto_residuo($residuo['nome'], $tipo, $residuo['classificacao_nome']);
    $credito = credito_foto_residuo($caminho_foto);

    // Pontos de coleta que aceitam este resíduo
    $stmt = $pdo->prepare(
        'SELECT p.* FROM ponto_coleta p
         INNER JOIN residuo_ponto_coleta rpc ON p.id_ponto = rpc.id_ponto
         WHERE rpc.id_residuo = :id ORDER BY p.nome'
    );
    $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
    $stmt->execute();
    $pontos = $stmt->fetchAll();

    // Outros resíduos do mesmo tipo (navegação)
    $relacionados = [];
    if ($tipo_info) {
        $stmt = $pdo->prepare(
            'SELECT r.id_residuo, r.nome, r.tipo_material, c.nome AS classificacao_nome
             FROM residuo r INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
             WHERE r.tipo_material = :tipo AND r.id_residuo <> :id AND r.nome <> :generico
             ORDER BY r.nome LIMIT 4'
        );
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':id', $id_residuo, PDO::PARAM_INT);
        $stmt->bindValue(':generico', RESIDUO_GENERICO);
        $stmt->execute();
        $relacionados = $stmt->fetchAll();
    }
    ?>

    <nav aria-label="Caminho" class="mb-3">
        <ol class="breadcrumb residuo-breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="residuos.php">Resíduos</a></li>
            <?php if ($tipo_info): ?>
                <li class="breadcrumb-item"><a href="residuos.php?tipo=<?php echo rawurlencode($tipo); ?>#resultados"><?php echo htmlspecialchars($tipo_info['rotulo']); ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($residuo['nome']); ?></li>
        </ol>
    </nav>

    <section class="info-card residuo-topo reveal">
        <div class="row g-0">
            <div class="col-lg-6">
                <figure class="residuo-topo-foto mb-0">
                    <img src="<?php echo BASE_URL . $caminho_foto; ?>?v=5" alt="Foto: <?php echo htmlspecialchars($residuo['nome']); ?>">
                </figure>
            </div>
            <div class="col-lg-6">
                <div class="p-4 p-lg-5">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge-classe <?php echo $classe['css']; ?>"><i class="bi <?php echo $classe['icone']; ?>"></i> <?php echo htmlspecialchars($classe['curto'] . ($classe['resumo'] ? ' · ' . $classe['resumo'] : '')); ?></span>
                        <?php if ($tipo_info): ?>
                            <span class="badge-soft"><i class="bi <?php echo $tipo_info['icone']; ?> me-1"></i><?php echo htmlspecialchars($tipo_info['rotulo']); ?></span>
                        <?php endif; ?>
                        <?php if ($residuo['possui_logistica_reversa']): ?>
                            <span class="badge-lr"><i class="bi bi-arrow-repeat me-1"></i>Logística reversa</span>
                        <?php endif; ?>
                    </div>
                    <h1 class="fs-2 mb-3"><?php echo htmlspecialchars($residuo['nome']); ?></h1>
                    <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars((string) $residuo['descricao'])); ?></p>

                    <div class="descarte-destaque">
                        <h2 class="h6 mb-2"><i class="bi bi-trash3 me-1"></i>Como descartar</h2>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) $residuo['forma_descarte'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php if ($credito): ?>
        <p class="foto-credito text-start mt-2 mb-0">
            Foto: <a href="<?php echo htmlspecialchars($credito['pagina']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($credito['autor']); ?></a>
            — <?php if ($credito['licenca_url'] !== ''): ?><a href="<?php echo htmlspecialchars($credito['licenca_url']); ?>" target="_blank" rel="noopener noreferrer license"><?php echo htmlspecialchars($credito['licenca']); ?></a><?php else: ?><?php echo htmlspecialchars($credito['licenca']); ?><?php endif; ?>,
            via Wikimedia Commons
        </p>
    <?php endif; ?>

    <div class="row g-4 mt-1">
        <div class="col-md-6">
            <div class="info-card">
                <div class="card-body p-4">
                    <h2 class="card-title h5"><span class="icon-chip-sm"><i class="bi bi-recycle"></i></span> Reciclagem e reaproveitamento</h2>
                    <p class="card-text mb-0"><?php echo nl2br(htmlspecialchars((string) $residuo['reciclagem'])); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-card">
                <div class="card-body p-4">
                    <h2 class="card-title h5"><span class="icon-chip-sm <?php echo $classe['css']; ?>"><i class="bi <?php echo $classe['icone']; ?>"></i></span> Por que é <?php echo htmlspecialchars($classe['curto']); ?>?</h2>
                    <p class="card-text mb-0"><?php echo htmlspecialchars((string) $residuo['classificacao_descricao']); ?></p>
                    <p class="small text-muted mt-2 mb-0">Classificação conforme a ABNT NBR 10004.</p>
                </div>
            </div>
        </div>
        <?php if ($residuo['possui_logistica_reversa']): ?>
            <div class="col-12">
                <div class="info-card">
                    <div class="card-body p-4">
                        <h2 class="card-title h5"><span class="icon-chip-sm" style="background: #fef3c7; color: #92400e;"><i class="bi bi-arrow-repeat"></i></span> Logística reversa</h2>
                        <p class="card-text mb-0">
                            Este resíduo está sujeito à logística reversa conforme a Lei nº 12.305/2010:
                            quem fabrica, importa ou vende é responsável por recolhê-lo. Entregue em pontos
                            de coleta específicos (lojas, assistências e ecopontos) para reaproveitamento ou
                            destinação ambientalmente adequada.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <section class="mt-5" aria-labelledby="titulo-pontos">
        <h2 class="h4 mb-3" id="titulo-pontos"><i class="bi bi-geo-alt me-1" style="color: var(--rhe-600);"></i>Onde entregar</h2>
        <?php if ($pontos): ?>
            <ul class="list-group">
                <?php foreach ($pontos as $ponto): ?>
                    <li class="list-group-item">
                        <strong><?php echo htmlspecialchars($ponto['nome']); ?></strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars((string) $ponto['endereco']); ?></span>
                        <?php if ($ponto['telefone']): ?>
                            <br><span class="text-muted">Tel: <?php echo htmlspecialchars($ponto['telefone']); ?></span>
                        <?php endif; ?>
                        <?php if ($ponto['horario_funcionamento']): ?>
                            <br><span class="text-muted">Horário: <?php echo htmlspecialchars($ponto['horario_funcionamento']); ?></span>
                        <?php endif; ?>
                        <?php if ($ponto['latitude'] && $ponto['longitude']): ?>
                            <br><a href="https://www.openstreetmap.org/?mlat=<?php echo (float) $ponto['latitude']; ?>&amp;mlon=<?php echo (float) $ponto['longitude']; ?>&amp;zoom=18" target="_blank" rel="noopener noreferrer" class="small">Ver no mapa</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="pontos_coleta.php?id_residuo=<?php echo $id_residuo; ?>" class="link-arrow small mt-3">Ver estes pontos no mapa <i class="bi bi-arrow-right"></i></a>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                Nenhum ponto de coleta cadastrado aceita este resíduo até o momento.
                Siga as orientações de descarte acima ou <a href="pontos_coleta.php">veja todos os pontos de coleta</a>.
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($residuo['palavras_chave'])): ?>
        <div class="mt-4">
            <span class="small text-muted me-1">Buscar por:</span>
            <?php foreach (array_filter(array_map('trim', explode(',', $residuo['palavras_chave']))) as $palavra): ?>
                <a class="organic-filter me-1 mt-1" href="residuos.php?busca=<?php echo rawurlencode($palavra); ?>#resultados"><?php echo htmlspecialchars($palavra); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($relacionados): ?>
        <section class="mt-5" aria-labelledby="titulo-relacionados">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <h2 class="h4 mb-0" id="titulo-relacionados">Outros resíduos: <?php echo htmlspecialchars($tipo_info['rotulo']); ?></h2>
                <a href="residuos.php?tipo=<?php echo rawurlencode($tipo); ?>#resultados" class="link-arrow small">Ver todos <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="row g-4">
                <?php foreach ($relacionados as $rel):
                    $classe_rel = classe_residuo($rel['classificacao_nome']); ?>
                    <div class="col-6 col-lg-3">
                        <a href="residuos.php?id=<?php echo (int) $rel['id_residuo']; ?>" class="text-decoration-none text-dark">
                            <div class="card card-residuo">
                                <div class="photo"><img src="<?php echo imagem_residuo($rel['nome'], $rel['tipo_material'], $rel['classificacao_nome']); ?>" alt="" loading="lazy"></div>
                                <div class="card-body p-3">
                                    <h3 class="h6 card-title mb-2"><?php echo htmlspecialchars($rel['nome']); ?></h3>
                                    <span class="badge-classe badge-classe-sm <?php echo $classe_rel['css']; ?>"><?php echo htmlspecialchars($classe_rel['curto']); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <a href="residuos.php" class="btn btn-outline-success mt-5"><i class="bi bi-arrow-left me-1"></i>Voltar à lista de resíduos</a>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ============================================================
// LISTAGEM COM BUSCA E FILTROS
// ============================================================
$busca = trim((string) ($_GET['busca'] ?? ''));
$tipo_filtro = (string) ($_GET['tipo'] ?? '');
if (!isset(TIPOS_RESIDUO[$tipo_filtro])) $tipo_filtro = '';
$classe_filtro = (string) ($_GET['classe'] ?? '');
if (!in_array($classe_filtro, ['1', '2a', '2b'], true)) $classe_filtro = '';
$lr_filtro = ($_GET['lr'] ?? '') === '1';

/** Link para a listagem trocando apenas alguns filtros (os demais são mantidos). */
function url_residuos(array $mudancas)
{
    global $busca, $tipo_filtro, $classe_filtro, $lr_filtro;
    $params = array_merge(
        ['busca' => $busca, 'tipo' => $tipo_filtro, 'classe' => $classe_filtro, 'lr' => $lr_filtro ? '1' : ''],
        $mudancas
    );
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'residuos.php' . ($params ? '?' . http_build_query($params) : '') . '#resultados';
}

// Classificações cadastradas, agrupadas pela chave (1, 2a, 2b)
$ids_por_classe = ['1' => [], '2a' => [], '2b' => []];
foreach ($pdo->query('SELECT id_classificacao, nome FROM classificacao') as $c) {
    $chave = classe_residuo($c['nome'])['chave'];
    if (isset($ids_por_classe[$chave])) $ids_por_classe[$chave][] = (int) $c['id_classificacao'];
}

// Contagens (sobre todos os resíduos) para os atalhos de tipo e de classe
$contagem_tipo = [];
$contagem_classe = ['1' => 0, '2a' => 0, '2b' => 0];
$stmt = $pdo->prepare(
    'SELECT r.tipo_material, c.nome AS classificacao_nome
     FROM residuo r INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
     WHERE r.nome <> :generico'
);
$stmt->bindValue(':generico', RESIDUO_GENERICO);
$stmt->execute();
$total_geral = 0;
foreach ($stmt->fetchAll() as $linha) {
    $total_geral++;
    $chave_tipo = $linha['tipo_material'] ?? '';
    $contagem_tipo[$chave_tipo] = ($contagem_tipo[$chave_tipo] ?? 0) + 1;
    $chave_classe = classe_residuo($linha['classificacao_nome'])['chave'];
    if (isset($contagem_classe[$chave_classe])) $contagem_classe[$chave_classe]++;
}

// Consulta com os filtros ativos
$condicoes = ['r.nome <> :generico'];
$params = [':generico' => RESIDUO_GENERICO];
if ($busca !== '') {
    // Escapa os curingas do LIKE (% e _) digitados pelo usuário
    $termo = '%' . addcslashes($busca, '%_\\') . '%';
    $condicoes[] = '(r.nome LIKE :busca1 OR r.palavras_chave LIKE :busca2 OR r.descricao LIKE :busca3)';
    $params[':busca1'] = $params[':busca2'] = $params[':busca3'] = $termo;
}
if ($tipo_filtro !== '') {
    $condicoes[] = 'r.tipo_material = :tipo';
    $params[':tipo'] = $tipo_filtro;
}
if ($classe_filtro !== '') {
    $ids = $ids_por_classe[$classe_filtro] ?: [0];
    $condicoes[] = 'r.id_classificacao IN (' . implode(',', array_map('intval', $ids)) . ')';
}
if ($lr_filtro) {
    $condicoes[] = 'r.possui_logistica_reversa = 1';
}
$stmt = $pdo->prepare(
    'SELECT r.*, c.nome AS classificacao_nome
     FROM residuo r
     INNER JOIN classificacao c ON r.id_classificacao = c.id_classificacao
     WHERE ' . implode(' AND ', $condicoes) . '
     ORDER BY r.nome'
);
foreach ($params as $chave => $valor) $stmt->bindValue($chave, $valor);
$stmt->execute();
$residuos = $stmt->fetchAll();

$filtrando = $busca !== '' || $tipo_filtro !== '' || $classe_filtro !== '' || $lr_filtro;

// Sem busca nem tipo: a lista é agrupada por tipo de material
$grupos = [];
if ($busca === '' && $tipo_filtro === '') {
    foreach ($residuos as $res) {
        $chave = isset(TIPOS_RESIDUO[$res['tipo_material'] ?? '']) ? $res['tipo_material'] : 'outros';
        $grupos[$chave][] = $res;
    }
    $ordem = array_flip(array_merge(array_keys(TIPOS_RESIDUO), ['outros']));
    uksort($grupos, fn($a, $b) => $ordem[$a] <=> $ordem[$b]);
} else {
    $grupos['_todos'] = $residuos;
}

// Subfiltros rápidos dentro dos orgânicos
$filtros_organicos = [
    'banana' => 'Banana', 'casca' => 'Cascas', 'cenoura' => 'Cenoura', 'queijo' => 'Queijo',
    'resto de comida' => 'Restos de comida', 'mato' => 'Mato e podas', 'borra' => 'Borra de café',
    'ovo' => 'Cascas de ovo', 'folhas' => 'Folhas secas', 'pao' => 'Pão',
];

$classes_info = [
    '1'  => classe_residuo('Classe I'),
    '2a' => classe_residuo('Classe II A'),
    '2b' => classe_residuo('Classe II B'),
];

/** Card de um resíduo na listagem. */
function card_residuo(array $res)
{
    $classe = classe_residuo($res['classificacao_nome']);
    $tipo_info = TIPOS_RESIDUO[$res['tipo_material'] ?? ''] ?? null;
    $texto_descarte = trim((string) $res['forma_descarte']);
    ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <a href="residuos.php?id=<?php echo (int) $res['id_residuo']; ?>" class="text-decoration-none text-dark">
            <article class="card card-residuo">
                <div class="photo">
                    <img src="<?php echo imagem_residuo($res['nome'], $res['tipo_material'] ?? null, $res['classificacao_nome']); ?>" alt="Foto: <?php echo htmlspecialchars($res['nome']); ?>" loading="lazy">
                    <?php if ($res['possui_logistica_reversa']): ?>
                        <span class="photo-tag" title="Sujeito à logística reversa (Lei 12.305/2010)"><i class="bi bi-arrow-repeat"></i> Logística reversa</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 card-title mb-2"><?php echo htmlspecialchars($res['nome']); ?></h3>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge-classe badge-classe-sm <?php echo $classe['css']; ?>" title="<?php echo htmlspecialchars($res['classificacao_nome']); ?>"><i class="bi <?php echo $classe['icone']; ?>"></i> <?php echo htmlspecialchars($classe['curto']); ?></span>
                        <?php if ($tipo_info): ?>
                            <span class="badge-tipo"><i class="bi <?php echo $tipo_info['icone']; ?>"></i> <?php echo htmlspecialchars($tipo_info['rotulo']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="card-text small text-muted clamp-3 mb-3"><strong class="text-body">Descarte:</strong> <?php echo htmlspecialchars($texto_descarte); ?></p>
                    <span class="link-arrow small mt-auto">Ver orientações <i class="bi bi-arrow-right"></i></span>
                </div>
            </article>
        </a>
    </div>
    <?php
}

$titulo_pagina = 'Resíduos';
require __DIR__ . '/../includes/header.php';
?>

<section class="band reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="Lixeiras coloridas de coleta seletiva">
    <span class="crumb">Consulte antes de descartar</span>
    <h1 class="mt-2">Resíduos</h1>
    <p class="mt-2">
        Digite o que você quer descartar ou escolha um tipo de material para ver
        a classificação e a forma correta de descarte.
    </p>
</section>

<!-- Busca + filtros -->
<div class="filtro-bar residuos-filtros mt-4" id="resultados">
    <form method="GET" action="residuos.php#resultados" role="search">
        <?php if ($tipo_filtro !== ''): ?><input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_filtro); ?>"><?php endif; ?>
        <?php if ($classe_filtro !== ''): ?><input type="hidden" name="classe" value="<?php echo htmlspecialchars($classe_filtro); ?>"><?php endif; ?>
        <?php if ($lr_filtro): ?><input type="hidden" name="lr" value="1"><?php endif; ?>
        <label for="busca" class="visually-hidden">O que você quer descartar?</label>
        <div class="search-pill">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="busca" name="busca" placeholder="O que você quer descartar? Ex.: pilha, caixa de pizza, óleo..." value="<?php echo htmlspecialchars($busca); ?>">
            <button class="btn btn-success" type="submit">Buscar</button>
        </div>
    </form>

    <div class="filtros-linha mt-3">
        <span class="filtros-rotulo">Classe (ABNT NBR 10004):</span>
        <a class="filtro-chip<?php echo $classe_filtro === '' ? ' active' : ''; ?>" href="<?php echo htmlspecialchars(url_residuos(['classe' => ''])); ?>">Todas</a>
        <?php foreach ($classes_info as $chave => $info): $chave = (string) $chave; // a chave '1' vira inteiro no PHP ?>
            <a class="filtro-chip filtro-chip-<?php echo $info['css']; ?><?php echo $classe_filtro === $chave ? ' active' : ''; ?>"
               href="<?php echo htmlspecialchars(url_residuos(['classe' => $classe_filtro === $chave ? '' : $chave])); ?>"
               <?php echo $classe_filtro === $chave ? 'aria-current="true"' : ''; ?>>
                <i class="bi <?php echo $info['icone']; ?>"></i> <?php echo $info['curto']; ?> · <?php echo $info['resumo']; ?>
                <span class="filtro-contagem"><?php echo $contagem_classe[$chave]; ?></span>
            </a>
        <?php endforeach; ?>
        <a class="filtro-chip filtro-chip-lr<?php echo $lr_filtro ? ' active' : ''; ?>"
           href="<?php echo htmlspecialchars(url_residuos(['lr' => $lr_filtro ? '' : '1'])); ?>"
           <?php echo $lr_filtro ? 'aria-current="true"' : ''; ?>>
            <i class="bi bi-arrow-repeat"></i> Logística reversa
        </a>
    </div>
    <details class="classes-ajuda mt-2">
        <summary>O que significa cada classe?</summary>
        <ul class="small mb-0 mt-2">
            <li><strong>Classe I · Perigoso:</strong> inflamável, corrosivo, tóxico, reativo ou patogênico. Nunca vai para o lixo comum.</li>
            <li><strong>Classe II A · Não inerte:</strong> não é perigoso, mas se altera com o tempo (biodegradável, combustível ou solúvel em água). Ex.: restos de comida, papel, plásticos.</li>
            <li><strong>Classe II B · Inerte:</strong> praticamente não se transforma em contato com a água. Ex.: entulho, vidro, cerâmica, pedras.</li>
            <li><strong>Logística reversa:</strong> por lei (Lei 12.305/2010), deve ser devolvido a quem fabrica ou vende, em pontos de coleta específicos.</li>
        </ul>
    </details>
</div>

<!-- Tipos de material -->
<section class="mt-4" aria-labelledby="titulo-tipos">
    <h2 class="h5 mb-3" id="titulo-tipos">Escolha o tipo de material</h2>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-5 g-3">
        <?php foreach (TIPOS_RESIDUO as $chave => $tipo): $ativo = $tipo_filtro === $chave; ?>
            <div class="col">
                <a href="<?php echo htmlspecialchars(url_residuos(['tipo' => $ativo ? '' : $chave, 'busca' => ''])); ?>"
                   class="text-decoration-none text-dark" <?php echo $ativo ? 'aria-current="true"' : ''; ?>
                   title="<?php echo htmlspecialchars($tipo['descricao']); ?>">
                    <div class="card card-tipo card-tipo-sm<?php echo $ativo ? ' active' : ''; ?>">
                        <div class="photo">
                            <img src="<?php echo BASE_URL . $tipo['foto']; ?>?v=4" alt="" loading="lazy">
                        </div>
                        <div class="card-body">
                            <span class="tipo-nome"><i class="bi <?php echo $tipo['icone']; ?>"></i> <?php echo htmlspecialchars($tipo['rotulo']); ?></span>
                            <span class="tipo-contagem"><?php echo (int) ($contagem_tipo[$chave] ?? 0); ?></span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($tipo_filtro === 'organico'): ?>
    <div class="organic-filters mt-3" aria-label="Filtros rápidos de orgânicos">
        <span class="filtros-rotulo">Orgânicos:</span>
        <?php foreach ($filtros_organicos as $termo_chip => $rotulo_chip): ?>
            <a href="<?php echo htmlspecialchars(url_residuos(['busca' => $busca === $termo_chip ? '' : $termo_chip])); ?>" class="organic-filter<?php echo $busca === $termo_chip ? ' active' : ''; ?>"><?php echo htmlspecialchars($rotulo_chip); ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Resumo dos filtros ativos -->
<div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-2 resultado-resumo">
    <span class="fw-semibold">
        <?php echo count($residuos); ?> <?php echo count($residuos) === 1 ? 'resíduo' : 'resíduos'; ?>
        <?php if (!$filtrando): ?><span class="text-muted fw-normal">cadastrados</span><?php endif; ?>
    </span>
    <?php if ($busca !== ''): ?>
        <a class="filtro-ativo" href="<?php echo htmlspecialchars(url_residuos(['busca' => ''])); ?>" title="Remover busca">“<?php echo htmlspecialchars($busca); ?>” <i class="bi bi-x"></i></a>
    <?php endif; ?>
    <?php if ($tipo_filtro !== ''): ?>
        <a class="filtro-ativo" href="<?php echo htmlspecialchars(url_residuos(['tipo' => ''])); ?>" title="Remover tipo"><?php echo htmlspecialchars(TIPOS_RESIDUO[$tipo_filtro]['rotulo']); ?> <i class="bi bi-x"></i></a>
    <?php endif; ?>
    <?php if ($classe_filtro !== ''): ?>
        <a class="filtro-ativo" href="<?php echo htmlspecialchars(url_residuos(['classe' => ''])); ?>" title="Remover classe"><?php echo $classes_info[$classe_filtro]['curto']; ?> <i class="bi bi-x"></i></a>
    <?php endif; ?>
    <?php if ($lr_filtro): ?>
        <a class="filtro-ativo" href="<?php echo htmlspecialchars(url_residuos(['lr' => ''])); ?>" title="Remover filtro">Logística reversa <i class="bi bi-x"></i></a>
    <?php endif; ?>
    <?php if ($filtrando): ?>
        <a href="residuos.php#resultados" class="small ms-1">Limpar filtros</a>
    <?php endif; ?>
    <?php if (usuario_logado()): ?>
        <span class="ms-auto d-flex flex-wrap gap-2">
            <a href="cadastro_residuo.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Cadastrar resíduo</a>
            <a href="cadastro_classificacao.php" class="btn btn-outline-success btn-sm"><i class="bi bi-tags me-1"></i>Cadastrar classificação</a>
        </span>
    <?php endif; ?>
</div>

<?php if (!$residuos): ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-search me-1"></i> Nenhum resíduo encontrado<?php if ($busca !== ''): ?> para "<strong><?php echo htmlspecialchars($busca); ?></strong>"<?php endif; ?> com os filtros escolhidos.
        Tente outra palavra (ex.: o material, como "plástico" ou "metal") ou <a href="residuos.php#resultados">veja todos os resíduos</a>.
    </div>
<?php endif; ?>

<?php foreach ($grupos as $chave_grupo => $itens): if (!$itens) continue; ?>
    <section class="grupo-residuos mt-4" <?php if ($chave_grupo !== '_todos'): ?>id="tipo-<?php echo htmlspecialchars($chave_grupo); ?>" aria-labelledby="titulo-grupo-<?php echo htmlspecialchars($chave_grupo); ?>"<?php endif; ?>>
        <?php if ($chave_grupo !== '_todos'):
            $info_grupo = TIPOS_RESIDUO[$chave_grupo] ?? ['rotulo' => 'Outros', 'descricao' => '', 'icone' => 'bi-three-dots']; ?>
            <div class="grupo-cabecalho">
                <span class="icon-chip-sm"><i class="bi <?php echo $info_grupo['icone']; ?>"></i></span>
                <div class="flex-grow-1">
                    <h2 class="h5 mb-0" id="titulo-grupo-<?php echo htmlspecialchars($chave_grupo); ?>"><?php echo htmlspecialchars($info_grupo['rotulo']); ?> <span class="text-muted fw-normal small">(<?php echo count($itens); ?>)</span></h2>
                    <?php if ($info_grupo['descricao']): ?><p class="small text-muted mb-0"><?php echo htmlspecialchars($info_grupo['descricao']); ?></p><?php endif; ?>
                </div>
                <?php if (isset(TIPOS_RESIDUO[$chave_grupo])): ?>
                    <a href="<?php echo htmlspecialchars(url_residuos(['tipo' => $chave_grupo])); ?>" class="link-arrow small">Só este tipo <i class="bi bi-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="row g-4">
            <?php foreach ($itens as $res) card_residuo($res); ?>
        </div>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
