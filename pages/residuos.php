<?php
/**
 * Resíduos — listagem com busca e filtros (RF03) e página de detalhes (RF04–RF08).
 *
 * Filtros combináveis, todos na URL (funcionam sem JavaScript):
 *   busca  — texto, tolerante a erros de digitação (config/busca.php): nome, palavras-chave e descrição;
 *   tipo   — tipo de material (config/residuos.php: TIPOS_RESIDUO);
 *   classe — 1, 2a ou 2b (ABNT NBR 10004);
 *   lr     — 1 = somente resíduos com logística reversa.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/residuos.php';
require_once __DIR__ . '/../config/busca.php';

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
                    <img src="<?php echo imagem_residuo($residuo['nome'], $tipo, $residuo['classificacao_nome']); ?>" alt="Foto: <?php echo htmlspecialchars($residuo['nome']); ?>">
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
                            quem fabrica, importa ou vende é responsável por recolhê-lo. Entregue em
                            lojas, assistências técnicas ou ecopontos para reaproveitamento ou
                            destinação ambientalmente adequada.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>


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

// Busca e logística reversa valem para tudo (lista e contagens)
$condicoes = ['r.nome <> :generico'];
$params = [':generico' => RESIDUO_GENERICO];
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
$linhas = $stmt->fetchAll();

// Busca tolerante a erros de digitação (config/busca.php): ordena pelos mais parecidos
$busca_corrigida = null;
if ($busca !== '') {
    [$linhas, $busca_corrigida] = buscar_residuos($linhas, $busca);
}

// Tipo e classe são filtrados aqui, para que cada contagem respeite o outro filtro:
// - número de cada TIPO   = resíduos daquele tipo dentro da classe escolhida;
// - número de cada CLASSE = resíduos daquela classe dentro do tipo escolhido.
$residuos = [];
$contagem_tipo = [];
$contagem_classe = ['1' => 0, '2a' => 0, '2b' => 0];
foreach ($linhas as $linha) {
    $chave_tipo = (string) ($linha['tipo_material'] ?? '');
    $chave_classe = classe_residuo($linha['classificacao_nome'])['chave'];
    $passa_tipo = $tipo_filtro === '' || $chave_tipo === $tipo_filtro;
    $passa_classe = $classe_filtro === '' || $chave_classe === $classe_filtro;
    if ($passa_classe) $contagem_tipo[$chave_tipo] = ($contagem_tipo[$chave_tipo] ?? 0) + 1;
    if ($passa_tipo && isset($contagem_classe[$chave_classe])) $contagem_classe[$chave_classe]++;
    if ($passa_tipo && $passa_classe) $residuos[] = $linha;
}

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

/** Card de um resíduo na listagem: só foto, nome e classe. O resto aparece ao passar o mouse e no detalhe. */
function card_residuo(array $res, $extra = false)
{
    $classe = classe_residuo($res['classificacao_nome']);
    $texto_descarte = trim((string) $res['forma_descarte']);
    ?>
    <div class="col-6 col-md-4 col-xl-3<?php echo $extra ? ' card-extra' : ''; ?>">
        <a href="residuos.php?id=<?php echo (int) $res['id_residuo']; ?>" class="text-decoration-none text-dark d-block h-100">
            <article class="card card-residuo card-residuo-limpo">
                <div class="photo">
                    <img src="<?php echo imagem_residuo($res['nome'], $res['tipo_material'] ?? null, $res['classificacao_nome']); ?>" alt="" loading="lazy">
                    <?php if ($res['possui_logistica_reversa']): ?>
                        <span class="photo-tag" title="Sujeito à logística reversa (Lei 12.305/2010)"><i class="bi bi-arrow-repeat"></i><span class="d-none d-sm-inline"> Logística reversa</span></span>
                    <?php endif; ?>
                    <span class="descarte-hover" aria-hidden="true"><span class="descarte-hover-rotulo"><i class="bi bi-trash3"></i> Como descartar</span><span class="descarte-hover-texto"><?php echo htmlspecialchars($texto_descarte); ?></span></span>
                </div>
                <div class="card-body">
                    <h3 class="h6 card-title mb-2"><?php echo htmlspecialchars($res['nome']); ?></h3>
                    <span class="badge-classe badge-classe-sm <?php echo $classe['css']; ?>" title="<?php echo htmlspecialchars($res['classificacao_nome']); ?>"><i class="bi <?php echo $classe['icone']; ?>"></i> <?php echo htmlspecialchars($classe['curto']); ?></span>
                </div>
            </article>
        </a>
    </div>
    <?php
}

const CARDS_POR_GRUPO = 4; // quantos resíduos cada grupo mostra antes de "Ver todos"
$filtros_abertos = $classe_filtro !== '' || $lr_filtro;
$qtd_filtros = ($classe_filtro !== '' ? 1 : 0) + ($lr_filtro ? 1 : 0);

$titulo_pagina = 'Resíduos';
require __DIR__ . '/../includes/header.php';
?>

<section class="band band-compacta reveal">
    <img class="band-bg" src="<?php echo BASE_URL; ?>img/fotos/coleta.jpg" alt="">
    <span class="crumb">Consulte antes de descartar</span>
    <h1 class="mt-1">Resíduos</h1>
    <p class="mt-1">Descubra como descartar cada material do jeito certo.</p>
</section>

<!-- Busca -->
<div class="filtro-bar residuos-filtros mt-4" id="resultados">
    <form method="GET" action="residuos.php#resultados" role="search">
        <?php if ($tipo_filtro !== ''): ?><input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_filtro); ?>"><?php endif; ?>
        <?php if ($classe_filtro !== ''): ?><input type="hidden" name="classe" value="<?php echo htmlspecialchars($classe_filtro); ?>"><?php endif; ?>
        <?php if ($lr_filtro): ?><input type="hidden" name="lr" value="1"><?php endif; ?>
        <label for="busca" class="visually-hidden">O que você quer descartar?</label>
        <div class="search-pill">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="busca" name="busca" placeholder="O que você quer descartar?" value="<?php echo htmlspecialchars($busca); ?>" data-autocompletar="<?php echo BASE_URL; ?>pages/sugestoes.php">
            <button class="btn btn-success" type="submit">Buscar</button>
        </div>
    </form>

    <!-- Tipos de material: fileira compacta (rola para o lado no celular) -->
    <nav class="tipos-pilulas mt-3" aria-label="Tipos de material">
        <a class="tipo-pilula<?php echo $tipo_filtro === '' ? ' active' : ''; ?>" href="<?php echo htmlspecialchars(url_residuos(['tipo' => '', 'busca' => ''])); ?>"<?php echo $tipo_filtro === '' ? ' aria-current="true"' : ''; ?>>
            <i class="bi bi-grid"></i> Todos
        </a>
        <?php foreach (TIPOS_RESIDUO as $chave => $tipo):
            $ativo = $tipo_filtro === $chave;
            $qtd_tipo = (int) ($contagem_tipo[$chave] ?? 0); ?>
            <a class="tipo-pilula<?php echo $ativo ? ' active' : ''; ?><?php echo $qtd_tipo === 0 && !$ativo ? ' vazio' : ''; ?>"
               href="<?php echo htmlspecialchars(url_residuos(['tipo' => $ativo ? '' : $chave, 'busca' => ''])); ?>"
               title="<?php echo htmlspecialchars($tipo['descricao']); ?>"<?php echo $ativo ? ' aria-current="true"' : ''; ?>>
                <i class="bi <?php echo $tipo['icone']; ?>"></i> <?php echo htmlspecialchars($tipo['rotulo']); ?>
                <span class="tipo-pilula-qtd"><?php echo $qtd_tipo; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Filtros avançados: recolhidos até a pessoa abrir -->
    <details class="filtros-avancados mt-2"<?php echo $filtros_abertos ? ' open' : ''; ?>>
        <summary>
            <i class="bi bi-sliders"></i> Filtrar por classe ou logística reversa
            <?php if ($qtd_filtros): ?><span class="filtros-qtd"><?php echo $qtd_filtros; ?></span><?php endif; ?>
            <i class="bi bi-chevron-down seta" aria-hidden="true"></i>
        </summary>
        <div class="filtros-linha mt-3">
            <a class="filtro-chip<?php echo $classe_filtro === '' ? ' active' : ''; ?>" href="<?php echo htmlspecialchars(url_residuos(['classe' => ''])); ?>">Todas as classes</a>
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
        <ul class="classes-explicacao small mb-0 mt-3">
            <li><strong>Classe I · Perigoso:</strong> inflamável, corrosivo, tóxico, reativo ou patogênico. Nunca vai para o lixo comum.</li>
            <li><strong>Classe II A · Não inerte:</strong> não é perigoso, mas se altera com o tempo (biodegradável, combustível ou solúvel em água).</li>
            <li><strong>Classe II B · Inerte:</strong> praticamente não se transforma em contato com a água. Ex.: entulho, vidro, cerâmica.</li>
            <li><strong>Logística reversa:</strong> por lei (Lei 12.305/2010), deve ser devolvido a quem fabrica ou vende.</li>
        </ul>
    </details>
</div>

<?php if ($tipo_filtro === 'organico'): ?>
    <div class="organic-filters mt-3" aria-label="Filtros rápidos de orgânicos">
        <?php foreach ($filtros_organicos as $termo_chip => $rotulo_chip): ?>
            <a href="<?php echo htmlspecialchars(url_residuos(['busca' => $busca === $termo_chip ? '' : $termo_chip])); ?>" class="organic-filter<?php echo $busca === $termo_chip ? ' active' : ''; ?>"><?php echo htmlspecialchars($rotulo_chip); ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Resumo: só aparece com detalhes quando há filtro ativo -->
<div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-1 resultado-resumo">
    <?php if ($busca !== '' || $tipo_filtro !== '' || !empty($classe_filtro)): ?>
    <span class="fw-semibold">
        <?php echo count($residuos); ?> <?php echo count($residuos) === 1 ? 'resultado' : 'resultados'; ?>
    </span>
    <?php endif; ?>
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
        <div class="dropdown ms-auto">
            <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-plus-circle me-1"></i>Cadastrar
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="cadastro_residuo.php">Novo resíduo</a></li>
                <li><a class="dropdown-item" href="cadastro_classificacao.php">Nova classificação</a></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
// A busca é um tipo de material ("eletrônicos", "metais"...)? Oferece ver o tipo inteiro
$tipo_da_busca = ($busca !== '' && $tipo_filtro === '') ? busca_tipo_correspondente($busca) : null;
if ($tipo_da_busca !== null): ?>
    <p class="busca-correcao mt-2 mb-0">
        <i class="bi <?php echo TIPOS_RESIDUO[$tipo_da_busca]['icone']; ?>"></i>
        Procurando um tipo de material?
        <a href="<?php echo htmlspecialchars(url_residuos(['tipo' => $tipo_da_busca, 'busca' => ''])); ?>"><strong>Ver todos: <?php echo htmlspecialchars(TIPOS_RESIDUO[$tipo_da_busca]['rotulo']); ?></strong></a>
    </p>
<?php endif; ?>

<?php if ($busca_corrigida !== null && $residuos): ?>
    <p class="busca-correcao mt-2 mb-0">
        <i class="bi bi-magic"></i> Mostrando resultados para <strong>“<?php echo htmlspecialchars($busca_corrigida); ?>”</strong>.
        Você digitou “<?php echo htmlspecialchars($busca); ?>”.
    </p>
<?php endif; ?>

<?php if (!$residuos): ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-search me-1"></i> Nenhum resíduo encontrado<?php if ($busca !== ''): ?> para "<strong><?php echo htmlspecialchars($busca); ?></strong>"<?php endif; ?> com os filtros escolhidos.
        Tente outra palavra (ex.: o material, como "plástico" ou "metal") ou <a href="residuos.php#resultados">veja todos os resíduos</a>.
    </div>
<?php endif; ?>

<?php foreach ($grupos as $chave_grupo => $itens): if (!$itens) continue;
    $agrupado = $chave_grupo !== '_todos';
    $sobra = $agrupado ? max(0, count($itens) - CARDS_POR_GRUPO) : 0; ?>
    <section class="grupo-residuos mt-4" <?php if ($agrupado): ?>id="tipo-<?php echo htmlspecialchars($chave_grupo); ?>" aria-labelledby="titulo-grupo-<?php echo htmlspecialchars($chave_grupo); ?>"<?php endif; ?>>
        <?php if ($agrupado):
            $info_grupo = TIPOS_RESIDUO[$chave_grupo] ?? ['rotulo' => 'Outros', 'descricao' => '', 'icone' => 'bi-three-dots']; ?>
            <div class="grupo-cabecalho">
                <span class="icon-chip-sm"><i class="bi <?php echo $info_grupo['icone']; ?>"></i></span>
                <h2 class="h5 mb-0 flex-grow-1" id="titulo-grupo-<?php echo htmlspecialchars($chave_grupo); ?>" title="<?php echo htmlspecialchars($info_grupo['descricao']); ?>">
                    <?php echo htmlspecialchars($info_grupo['rotulo']); ?> <span class="text-muted fw-normal small"><?php echo count($itens); ?></span>
                </h2>
            </div>
        <?php endif; ?>
        <div class="row g-3 g-md-4">
            <?php foreach ($itens as $i => $res) card_residuo($res, $agrupado && $i >= CARDS_POR_GRUPO); ?>
        </div>
        <?php if ($sobra > 0): ?>
            <div class="text-center mt-3">
                <a class="btn btn-outline-success btn-sm ver-mais" href="<?php echo htmlspecialchars(url_residuos(['tipo' => $chave_grupo])); ?>"
                   data-ver-mais="<?php echo $sobra; ?>" aria-expanded="false">
                    Ver mais <?php echo $sobra; ?> <i class="bi bi-chevron-down"></i>
                </a>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<script>
// "Ver mais": abre o restante do grupo na própria página (sem JavaScript, o botão leva ao filtro do tipo)
document.querySelectorAll('[data-ver-mais]').forEach(function (botao) {
    botao.addEventListener('click', function (evento) {
        evento.preventDefault();
        var grupo = botao.closest('.grupo-residuos');
        var aberto = grupo.classList.toggle('grupo-aberto');
        botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        botao.innerHTML = aberto
            ? 'Mostrar menos <i class="bi bi-chevron-up"></i>'
            : 'Ver mais ' + botao.getAttribute('data-ver-mais') + ' <i class="bi bi-chevron-down"></i>';
        if (!aberto) grupo.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
