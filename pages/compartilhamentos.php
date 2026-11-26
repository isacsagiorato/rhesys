<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
require_once __DIR__ . '/../config/notificacoes.php';
exige_login();

$titulo_pagina = 'Compartilhamentos';
$status_validos = ['todos' => 'Todos', 'disponivel' => 'Disponível', 'reservado' => 'Reservado', 'concluido' => 'Retirado', 'cancelado' => 'Indisponível'];
$erros = [];
$mensagens = [
    'oferta' => 'Material publicado com sucesso.',
    'interesse' => 'Seu interesse foi registrado. O ofertante poderá aceitá-lo no painel.',
    'interesse_cancelado' => 'Seu interesse foi cancelado.',
];
$codigo_sucesso = $_GET['sucesso'] ?? '';
$sucesso = is_string($codigo_sucesso) ? ($mensagens[$codigo_sucesso] ?? '') : '';

$busca = trim((string) ($_GET['busca'] ?? ''));
$categoria = (string) ($_GET['categoria'] ?? 'todas');
$estado = (string) ($_GET['estado'] ?? 'todos');
$tipo = (string) ($_GET['tipo'] ?? 'todos');
if ($tipo !== 'todos' && !isset(TIPOS_OFERTA[$tipo])) $tipo = 'todos';
$localizacao = trim((string) ($_GET['localizacao'] ?? ''));
$status = (string) ($_GET['status'] ?? 'todos');
if (!isset($status_validos[$status])) $status = 'todos';
if ($categoria !== 'todas' && !isset(CATEGORIAS_MATERIAIS[$categoria])) $categoria = 'todas';
if ($estado !== 'todos' && !isset(ESTADOS_CONSERVACAO[$estado])) $estado = 'todos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $erros[] = 'Sessão expirada ou formulário inválido. Recarregue a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $id = (int) ($_POST['id_compartilhamento'] ?? 0);
        try {
            $pdo->beginTransaction();
            if ($acao === 'demonstrar_interesse') {
                $stmt = $pdo->prepare('SELECT id_usuario_ofertante, status FROM compartilhamento_residuo WHERE id_compartilhamento = :id FOR UPDATE');
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $oferta = $stmt->fetch();
                if (!$oferta || $oferta['status'] !== 'disponivel') {
                    throw new RuntimeException('Esta oferta não está mais disponível.');
                }
                if ((int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario']) {
                    throw new RuntimeException('Você não pode demonstrar interesse na própria oferta.');
                }
                $stmt = $pdo->prepare('SELECT id_interesse, status FROM interesse_compartilhamento WHERE id_compartilhamento = :oferta AND id_usuario = :usuario FOR UPDATE');
                $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                $stmt->execute();
                $interesse = $stmt->fetch();
                if ($interesse && in_array($interesse['status'], ['pendente', 'aceito'], true)) {
                    throw new RuntimeException('Você já registrou interesse nesta oferta.');
                }
                if ($interesse) {
                    $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'pendente', data_interesse = CURRENT_TIMESTAMP WHERE id_interesse = :id");
                    $stmt->bindValue(':id', $interesse['id_interesse'], PDO::PARAM_INT);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO interesse_compartilhamento (id_compartilhamento, id_usuario) VALUES (:oferta, :usuario)');
                    $stmt->bindValue(':oferta', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                }
                $stmt->execute();
                notif_interesse_novo($pdo, $id, $_SESSION['id_usuario']);
                $pdo->commit();
                header('Location: compartilhamentos.php?status=todos&sucesso=interesse#oferta-' . $id);
                exit;
            }
            if ($acao === 'cancelar_interesse') {
                $id_interesse = (int) ($_POST['id_interesse'] ?? 0);
                $stmt = $pdo->prepare('SELECT i.id_interesse, i.status, i.id_compartilhamento, c.status AS status_oferta FROM interesse_compartilhamento i INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento WHERE i.id_interesse = :id AND i.id_usuario = :usuario FOR UPDATE');
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->bindValue(':usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
                $stmt->execute();
                $interesse = $stmt->fetch();
                if (!$interesse || !in_array($interesse['status'], ['pendente', 'aceito'], true)) {
                    throw new RuntimeException('O interesse não foi encontrado ou já está encerrado.');
                }
                $stmt = $pdo->prepare("UPDATE interesse_compartilhamento SET status = 'cancelado' WHERE id_interesse = :id");
                $stmt->bindValue(':id', $id_interesse, PDO::PARAM_INT);
                $stmt->execute();
                if ($interesse['status'] === 'aceito' && $interesse['status_oferta'] === 'reservado') {
                    $stmt = $pdo->prepare("UPDATE compartilhamento_residuo SET status = 'disponivel' WHERE id_compartilhamento = :id");
                    $stmt->bindValue(':id', $interesse['id_compartilhamento'], PDO::PARAM_INT);
                    $stmt->execute();
                }
                notif_interesse_cancelado($pdo, $interesse['id_compartilhamento'], $_SESSION['id_usuario'], $interesse['status'] === 'aceito');
                $pdo->commit();
                header('Location: compartilhamentos.php?status=todos&sucesso=interesse_cancelado#oferta-' . $interesse['id_compartilhamento']);
                exit;
            }
            throw new RuntimeException('Ação inválida.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erros[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erro no compartilhamento: ' . $e->getMessage());
            $erros[] = 'Não foi possível concluir a operação. Tente novamente.';
        }
    }
}
$condicoes = [];
$params = [':usuario' => $_SESSION['id_usuario']];
if ($status !== 'todos') { $condicoes[] = 'c.status = :status'; $params[':status'] = $status; }
if ($busca !== '') { $condicoes[] = '(c.nome LIKE :busca OR r.nome LIKE :busca2)'; $params[':busca'] = $params[':busca2'] = '%' . $busca . '%'; }
if ($categoria !== 'todas') { $condicoes[] = 'c.categoria = :categoria'; $params[':categoria'] = $categoria; }
if ($estado !== 'todos') { $condicoes[] = 'c.estado_conservacao = :estado'; $params[':estado'] = $estado; }
if ($tipo !== 'todos') { $condicoes[] = 'c.tipo_oferta = :tipo'; $params[':tipo'] = $tipo; }
if ($localizacao !== '') { $condicoes[] = 'c.localizacao LIKE :localizacao'; $params[':localizacao'] = '%' . $localizacao . '%'; }
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$sql = "SELECT c.*,
               COALESCE(NULLIF(c.nome, ''), r.nome) AS material_nome,
               COALESCE(NULLIF(c.categoria, ''), cl.nome) AS material_categoria,
               u.nome AS ofertante_nome,
               mi.id_interesse AS meu_interesse_id, mi.status AS meu_interesse_status,
               (SELECT COUNT(*) FROM interesse_compartilhamento i
                 WHERE i.id_compartilhamento = c.id_compartilhamento
                   AND i.status IN ('pendente', 'aceito')) AS total_interesses
        FROM compartilhamento_residuo c
        LEFT JOIN residuo r ON r.id_residuo = c.id_residuo
        LEFT JOIN classificacao cl ON cl.id_classificacao = r.id_classificacao
        INNER JOIN usuario u ON u.id_usuario = c.id_usuario_ofertante
        LEFT JOIN interesse_compartilhamento mi
          ON mi.id_compartilhamento = c.id_compartilhamento AND mi.id_usuario = :usuario
        $where
        ORDER BY c.data_publicacao DESC, c.id_compartilhamento DESC";
$stmt = $pdo->prepare($sql);
foreach ($params as $chave => $valor) {
    $stmt->bindValue($chave, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$ofertas = $stmt->fetchAll();

// Números gerais (sem filtros) para o topo, as abas de status e os chips de categoria
$contagem_status = array_fill_keys(array_keys(STATUS_COMPARTILHAMENTO), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM compartilhamento_residuo GROUP BY status') as $linha) {
    $contagem_status[$linha['status']] = (int) $linha['total'];
}
$contagem_status['todos'] = array_sum($contagem_status);
$contagem_categoria = [];
foreach ($pdo->query("SELECT categoria, COUNT(*) AS total FROM compartilhamento_residuo WHERE categoria IS NOT NULL AND categoria <> '' GROUP BY categoria") as $linha) {
    $contagem_categoria[$linha['categoria']] = (int) $linha['total'];
}
$total_interesses = (int) $pdo->query("SELECT COUNT(*) FROM interesse_compartilhamento WHERE status IN ('pendente', 'aceito')")->fetchColumn();

$icones_categoria = [
    'moveis' => 'bi-lamp', 'eletronicos' => 'bi-laptop', 'eletrodomesticos' => 'bi-plug',
    'roupas' => 'bi-bag', 'livros' => 'bi-book', 'brinquedos' => 'bi-puzzle',
    'ferramentas' => 'bi-tools', 'construcao' => 'bi-bricks', 'plasticos' => 'bi-cup-straw',
    'metais' => 'bi-nut', 'vidros' => 'bi-cup', 'organicos' => 'bi-flower1', 'outros' => 'bi-box-seam',
];
$icones_status = ['todos' => 'bi-grid', 'disponivel' => 'bi-check2-circle', 'reservado' => 'bi-hourglass-split', 'concluido' => 'bi-bag-check', 'cancelado' => 'bi-slash-circle'];

/** Monta a URL da listagem mantendo os filtros atuais e trocando só o que for passado. */
function url_compartilhamentos(array $troca)
{
    global $busca, $categoria, $estado, $localizacao, $status, $tipo;
    $q = array_merge(['busca' => $busca, 'tipo' => $tipo, 'categoria' => $categoria, 'estado' => $estado, 'localizacao' => $localizacao, 'status' => $status], $troca);
    $q = array_filter($q, fn($v, $k) => $v !== '' && !($k === 'categoria' && $v === 'todas') && !(in_array($k, ['estado', 'tipo'], true) && $v === 'todos'), ARRAY_FILTER_USE_BOTH);
    return 'compartilhamentos.php' . ($q ? '?' . http_build_query($q) : '') . '#materiais';
}

/** "hoje", "ontem", "há 3 dias"... */
function tempo_publicacao($data)
{
    $dias = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime((string) $data)))) / 86400);
    if ($dias <= 0) return 'hoje';
    if ($dias === 1) return 'ontem';
    if ($dias < 30) return "há $dias dias";
    $meses = intdiv($dias, 30);
    return $meses === 1 ? 'há 1 mês' : "há $meses meses";
}

$filtros_avancados = $estado !== 'todos' || $localizacao !== '';
$tem_filtro = $busca !== '' || $categoria !== 'todas' || $filtros_avancados || $status !== 'todos' || $tipo !== 'todos';

// Quantos materiais de cada tipo (para o filtro Doação / Venda)
$contagem_tipo = ['todos' => 0, 'doacao' => 0, 'venda' => 0];
foreach ($pdo->query("SELECT tipo_oferta, COUNT(*) AS total FROM compartilhamento_residuo WHERE status <> 'cancelado' GROUP BY tipo_oferta") as $linha) {
    $contagem_tipo[$linha['tipo_oferta']] = (int) $linha['total'];
    $contagem_tipo['todos'] += (int) $linha['total'];
}

require __DIR__ . '/../includes/header.php';
?>
<section class="comp-hero reveal">
    <div class="comp-hero-texto">
        <span class="comp-eyebrow"><i class="bi bi-arrow-repeat"></i> Economia circular</span>
        <h1>Dê uma nova vida ao que você <span class="comp-destaque">não usa mais</span></h1>
        <p>Doe ou venda materiais que iriam para o lixo e encontre o que outras pessoas estão oferecendo perto de você.</p>
        <div class="comp-hero-acoes">
            <a href="cadastro_compartilhamento.php" class="btn btn-light btn-lg comp-btn-oferecer"><i class="bi bi-plus-circle me-2"></i>Oferecer material</a>
            <a href="meus_compartilhamentos.php" class="btn btn-outline-light btn-lg"><i class="bi bi-person-lines-fill me-2"></i>Meus compartilhamentos</a>
        </div>
        <dl class="comp-stats">
            <div><dt data-contar="<?php echo $contagem_status['disponivel']; ?>"><?php echo $contagem_status['disponivel']; ?></dt><dd>disponíveis agora</dd></div>
            <div><dt data-contar="<?php echo $contagem_status['concluido']; ?>"><?php echo $contagem_status['concluido']; ?></dt><dd>já reaproveitados</dd></div>
            <div><dt data-contar="<?php echo $total_interesses; ?>"><?php echo $total_interesses; ?></dt><dd>interesses registrados</dd></div>
        </dl>
    </div>

    <div class="comp-ciclo" aria-hidden="true">
        <div class="comp-anel comp-anel-1"></div>
        <div class="comp-anel comp-anel-2"></div>
        <div class="comp-centro">
            <i class="bi bi-arrow-repeat"></i>
            <span>reaproveitar</span>
        </div>
        <div class="comp-orbita">
            <?php foreach (['bi-lamp', 'bi-book', 'bi-bicycle', 'bi-box-seam', 'bi-laptop', 'bi-bag-heart'] as $n => $icone): ?>
                <span class="comp-item" style="--n: <?php echo $n; ?>"><i class="bi <?php echo $icone; ?>"></i></span>
            <?php endforeach; ?>
        </div>
        <span class="comp-faisca" style="--x: 12%; --y: 22%; --d: 0s"></span>
        <span class="comp-faisca" style="--x: 86%; --y: 30%; --d: 1.2s"></span>
        <span class="comp-faisca" style="--x: 78%; --y: 84%; --d: 2.1s"></span>
        <span class="comp-faisca" style="--x: 18%; --y: 78%; --d: 3s"></span>
    </div>
</section>

<ol class="comp-passos reveal">
    <li><span class="comp-passo-icone"><i class="bi bi-camera"></i></span><div><strong>Ofereça</strong><span>Publique o material com foto, para doar ou vender.</span></div></li>
    <li><span class="comp-passo-icone"><i class="bi bi-chat-heart"></i></span><div><strong>Combine</strong><span>Quem precisa registra interesse e vocês conversam.</span></div></li>
    <li><span class="comp-passo-icone"><i class="bi bi-recycle"></i></span><div><strong>Reaproveite</strong><span>O material é retirado e ganha uma nova vida.</span></div></li>
</ol>

<section id="materiais" class="comp-explorar reveal">
    <div class="comp-explorar-topo">
        <div>
            <h2 class="h3 mb-1">Materiais compartilhados</h2>
            <p class="text-muted mb-0"><?php echo count($ofertas); ?> <?php echo count($ofertas) === 1 ? 'material encontrado' : 'materiais encontrados'; ?><?php echo $tem_filtro ? ' com os filtros atuais' : ''; ?></p>
        </div>
        <nav class="comp-abas" aria-label="Filtrar por status">
            <?php foreach ($status_validos as $v => $rot): if ($v === 'cancelado' && $contagem_status['cancelado'] === 0) continue; ?>
                <a href="<?php echo htmlspecialchars(url_compartilhamentos(['status' => $v])); ?>" class="<?php echo $status === $v ? 'ativo' : ''; ?>" <?php echo $status === $v ? 'aria-current="page"' : ''; ?>>
                    <i class="bi <?php echo $icones_status[$v]; ?>"></i><?php echo htmlspecialchars($rot); ?>
                    <span class="comp-aba-num"><?php echo $contagem_status[$v] ?? 0; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <form method="get" action="compartilhamentos.php#materiais" class="comp-busca">
        <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoria); ?>">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
        <div class="comp-busca-linha">
            <label for="busca" class="visually-hidden">Pesquisar por nome</label>
            <div class="comp-busca-campo">
                <i class="bi bi-search"></i>
                <input type="search" id="busca" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Pesquisar material: cadeira, livro, pallet...">
            </div>
            <button class="btn comp-btn-filtros <?php echo $filtros_avancados ? 'ativo' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#filtros-avancados" aria-expanded="<?php echo $filtros_avancados ? 'true' : 'false'; ?>" aria-controls="filtros-avancados">
                <i class="bi bi-sliders"></i><span class="d-none d-sm-inline ms-1">Mais filtros</span>
            </button>
            <button type="submit" class="btn btn-success comp-btn-buscar"><i class="bi bi-search d-sm-none"></i><span class="d-none d-sm-inline">Buscar</span></button>
        </div>
        <div class="collapse <?php echo $filtros_avancados ? 'show' : ''; ?>" id="filtros-avancados">
            <div class="row g-3 pt-3">
                <div class="col-sm-6">
                    <label for="estado" class="form-label small mb-1">Estado de conservação</label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="todos">Todos</option>
                        <?php foreach (ESTADOS_CONSERVACAO as $v => $rot): ?>
                            <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $estado === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($rot); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label for="localizacao" class="form-label small mb-1">Localização</label>
                    <input type="text" class="form-control" id="localizacao" name="localizacao" value="<?php echo htmlspecialchars($localizacao); ?>" placeholder="Bairro ou cidade">
                </div>
            </div>
        </div>
    </form>

    <nav class="comp-tipos" aria-label="Doação ou venda">
        <?php foreach (['todos' => ['bi-collection', 'Tudo'], 'doacao' => ['bi-gift', 'Doações'], 'venda' => ['bi-tag', 'À venda']] as $v => [$icone, $rot]): ?>
            <a href="<?php echo htmlspecialchars(url_compartilhamentos(['tipo' => $v])); ?>" class="comp-tipo tipo-<?php echo $v; ?> <?php echo $tipo === $v ? 'ativo' : ''; ?>" <?php echo $tipo === $v ? 'aria-current="page"' : ''; ?>>
                <i class="bi <?php echo $icone; ?>"></i><?php echo $rot; ?><span><?php echo $contagem_tipo[$v]; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="comp-chips" role="list" aria-label="Filtrar por categoria">
        <a role="listitem" href="<?php echo htmlspecialchars(url_compartilhamentos(['categoria' => 'todas'])); ?>" class="comp-chip <?php echo $categoria === 'todas' ? 'ativo' : ''; ?>"><i class="bi bi-grid-3x3-gap"></i>Todas</a>
        <?php foreach (CATEGORIAS_MATERIAIS as $v => $rot): $qtd = $contagem_categoria[$v] ?? 0; if ($qtd === 0 && $categoria !== $v) continue; ?>
            <a role="listitem" href="<?php echo htmlspecialchars(url_compartilhamentos(['categoria' => $v])); ?>" class="comp-chip <?php echo $categoria === $v ? 'ativo' : ''; ?>">
                <i class="bi <?php echo $icones_categoria[$v] ?? 'bi-box-seam'; ?>"></i><?php echo htmlspecialchars($rot); ?><span><?php echo $qtd; ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($tem_filtro): ?>
            <a href="compartilhamentos.php#materiais" class="comp-limpar"><i class="bi bi-x-circle"></i>Limpar filtros</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($sucesso): ?><div class="alert alert-success comp-alerta mt-4"><i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($sucesso); ?></div><?php endif; ?>
<?php if ($erros): ?><div class="alert alert-danger comp-alerta mt-4"><ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?php echo htmlspecialchars($erro); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if (!$ofertas): ?>
    <div class="comp-vazio reveal">
        <div class="comp-vazio-arte" aria-hidden="true"><i class="bi bi-box2-heart"></i></div>
        <h2 class="h4">Nada por aqui ainda</h2>
        <p class="text-muted"><?php echo $tem_filtro ? 'Nenhum material combina com os filtros atuais. Tente outra busca ou limpe os filtros.' : 'Ainda não há materiais compartilhados. Que tal começar?'; ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php if ($tem_filtro): ?><a href="compartilhamentos.php#materiais" class="btn btn-outline-success">Limpar filtros</a><?php endif; ?>
            <a href="cadastro_compartilhamento.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Oferecer material</a>
        </div>
    </div>
<?php else: ?>
    <div class="comp-grade">
        <?php foreach ($ofertas as $i => $oferta):
            $id_oferta = (int) $oferta['id_compartilhamento'];
            $propria = (int) $oferta['id_usuario_ofertante'] === (int) $_SESSION['id_usuario'];
            $categoria_rotulo = ($oferta['categoria'] !== null && $oferta['categoria'] !== '')
                ? rotulo_categoria_material($oferta['categoria'])
                : ($oferta['material_categoria'] !== null && $oferta['material_categoria'] !== '' ? $oferta['material_categoria'] : 'Resíduo');
            $inicial = mb_strtoupper(mb_substr(trim((string) $oferta['ofertante_nome']), 0, 1));
            $interesses = (int) $oferta['total_interesses'];
        ?>
        <article class="comp-card status-<?php echo htmlspecialchars($oferta['status']); ?>" id="oferta-<?php echo $id_oferta; ?>" style="--i: <?php echo min($i, 11); ?>">
            <a class="comp-card-foto" href="compartilhamento.php?id=<?php echo $id_oferta; ?>">
                <img src="<?php echo imagem_material($oferta['imagem']); ?>" alt="<?php echo htmlspecialchars($oferta['material_nome']); ?>" loading="lazy">
                <span class="comp-status"><span class="ponto"></span><?php echo rotulo_status_oferta($oferta['status'], $oferta['tipo_oferta']); ?></span>
                <span class="comp-preco <?php echo $oferta['tipo_oferta'] === 'venda' ? 'venda' : 'doacao'; ?>"><?php if ($oferta['tipo_oferta'] === 'venda'): ?><?php echo htmlspecialchars(rotulo_preco_oferta($oferta)); ?><?php if ($oferta['preco_negociavel']): ?><small>negociável</small><?php endif; ?><?php else: ?><i class="bi bi-gift"></i>Grátis<?php endif; ?></span>
                <span class="comp-quando"><i class="bi bi-clock"></i><?php echo tempo_publicacao($oferta['data_publicacao']); ?></span>
                <span class="comp-cat"><i class="bi <?php echo $icones_categoria[$oferta['categoria']] ?? 'bi-box-seam'; ?>"></i><?php echo htmlspecialchars($categoria_rotulo); ?></span>
            </a>
            <div class="comp-card-corpo">
                <h3 class="comp-card-titulo"><a href="compartilhamento.php?id=<?php echo $id_oferta; ?>"><?php echo htmlspecialchars($oferta['material_nome']); ?></a></h3>
                <p class="comp-card-desc"><?php echo htmlspecialchars((string) $oferta['descricao']); ?></p>
                <div class="comp-card-meta">
                    <span class="<?php echo classe_estado_conservacao($oferta['estado_conservacao']); ?>"><?php echo htmlspecialchars(rotulo_estado_conservacao($oferta['estado_conservacao'])); ?></span>
                    <?php if ($oferta['quantidade'] !== null && $oferta['quantidade'] !== ''): ?><span><i class="bi bi-box-seam"></i><?php echo htmlspecialchars($oferta['quantidade']); ?></span><?php endif; ?>
                    <?php if ($oferta['localizacao'] !== null && $oferta['localizacao'] !== ''): ?><span><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($oferta['localizacao']); ?></span><?php endif; ?>
                </div>
                <div class="comp-card-pe">
                    <div class="comp-autor">
                        <span class="comp-avatar"><?php echo htmlspecialchars($inicial); ?></span>
                        <div><small>Oferecido por</small><strong><?php echo $propria ? 'Você' : htmlspecialchars($oferta['ofertante_nome']); ?></strong></div>
                    </div>
                    <span class="comp-interesses <?php echo $interesses > 0 ? 'tem' : ''; ?>" title="<?php echo $interesses; ?> interesse(s)"><i class="bi bi-heart<?php echo $interesses > 0 ? '-fill' : ''; ?>"></i><?php echo $interesses; ?></span>
                </div>
                <div class="comp-card-acoes">
                    <a href="compartilhamento.php?id=<?php echo $id_oferta; ?>" class="btn btn-sm btn-outline-success">Ver detalhes</a>
                    <?php if ($propria): ?>
                        <a href="meus_compartilhamentos.php#oferta-<?php echo $id_oferta; ?>" class="btn btn-sm btn-success"><i class="bi bi-gear me-1"></i>Gerenciar</a>
                    <?php elseif (in_array($oferta['meu_interesse_status'], ['pendente', 'aceito'], true)): ?>
                        <form method="post" action="compartilhamentos.php?status=<?php echo htmlspecialchars($status); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="acao" value="cancelar_interesse">
                            <input type="hidden" name="id_interesse" value="<?php echo (int) $oferta['meu_interesse_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Seu interesse está <?php echo $oferta['meu_interesse_status'] === 'aceito' ? 'aceito' : 'pendente'; ?>">Cancelar interesse</button>
                        </form>
                        <a href="conversas.php?id=<?php echo (int) $oferta['meu_interesse_id']; ?>" class="btn btn-sm btn-success"><i class="bi bi-chat-dots me-1"></i>Conversar</a>
                        <span class="comp-meu-interesse"><i class="bi bi-<?php echo $oferta['meu_interesse_status'] === 'aceito' ? 'check2-circle' : 'hourglass-split'; ?>"></i>Interesse <?php echo $oferta['meu_interesse_status'] === 'aceito' ? 'aceito' : 'pendente'; ?></span>
                    <?php elseif ($oferta['status'] === 'disponivel'): ?>
                        <form method="post" action="compartilhamentos.php?status=<?php echo htmlspecialchars($status); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="acao" value="demonstrar_interesse">
                            <input type="hidden" name="id_compartilhamento" value="<?php echo $id_oferta; ?>">
                            <button type="submit" class="btn btn-sm btn-success comp-btn-interesse"><i class="bi bi-hand-thumbs-up me-1"></i>Tenho interesse</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="comp-cta reveal">
    <div class="comp-cta-icone" aria-hidden="true"><i class="bi bi-gift"></i></div>
    <div>
        <h2 class="h4 mb-1">Tem algo parado em casa?</h2>
        <p class="mb-0">Móveis, livros, roupas, materiais de obra: o que não serve mais para você pode ser exatamente o que alguém procura.</p>
    </div>
    <a href="cadastro_compartilhamento.php" class="btn btn-light btn-lg"><i class="bi bi-plus-circle me-2"></i>Oferecer agora</a>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
