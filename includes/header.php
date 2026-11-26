<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#04281c">
    <title><?php echo htmlspecialchars(isset($titulo_pagina) ? $titulo_pagina . ' - ' . NOME_SITE : NOME_SITE); ?></title>

    <!-- Favicon (nova logo) -->
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>img/logo-icon.svg">

    <!-- Bootstrap via CDN (RNF03 / RNF04) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Fontes modernas: Outfit (títulos) + Inter (textos) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS próprio (?v=14 força o navegador a baixar a versão nova e não usar cache) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=51">
    <?php foreach (($estilos_extras ?? []) as $stylesheet): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <noscript><style>.reveal { opacity: 1; transform: none; }</style></noscript>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<nav class="navbar navbar-expand-xl navbar-rhe">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>index.php">
            <img src="<?php echo BASE_URL; ?>img/logo-icon.svg" alt="Logotipo Rhesys" width="38" height="38">
            <span>Rhe<span class="brand-sys">sys</span></span>
        </a>

        <?php if (usuario_logado()):
            // Sino de notificações (fica fora do menu recolhível para aparecer também no celular)
            if (!isset($pdo)) require_once __DIR__ . '/../config/conexao.php';
            require_once __DIR__ . '/../config/seguranca.php';
            require_once __DIR__ . '/../config/notificacoes.php';
            require_once __DIR__ . '/../config/chat.php';
            $chat_nao_lidas = chat_nao_lidas_total($pdo, $_SESSION['id_usuario']);
            $sino_nao_lidas = notificacoes_nao_lidas($pdo, $_SESSION['id_usuario']);
            $sino_dados = [
                'nao_lidas' => $sino_nao_lidas,
                'filtro' => 'todas',
                'itens' => array_map('notificacao_para_array', notificacoes_listar($pdo, $_SESSION['id_usuario'], 8, 'todas')),
            ];
        ?>
        <div class="topo-icones ms-auto me-2 me-xl-0 ms-xl-2 order-xl-last">
        <a class="chat-topo" href="<?php echo BASE_URL; ?>pages/conversas.php" title="Conversas" aria-label="Conversas<?php echo $chat_nao_lidas ? " ($chat_nao_lidas mensagens não lidas)" : ''; ?>">
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
            <span class="chat-topo-num" <?php echo $chat_nao_lidas ? '' : 'hidden'; ?>><?php echo $chat_nao_lidas > 9 ? '9+' : $chat_nao_lidas; ?></span>
        </a>
        <div class="dropdown notif-sino"
             data-api="<?php echo BASE_URL; ?>pages/notificacoes_api.php"
             data-acao="<?php echo BASE_URL; ?>pages/notificacoes.php"
             data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="notif-sino-btn<?php echo $sino_nao_lidas ? ' tem' : ''; ?>" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-display="static" aria-expanded="false"
                    aria-label="Notificações<?php echo $sino_nao_lidas ? " ($sino_nao_lidas não lidas)" : ''; ?>">
                <i class="bi bi-bell<?php echo $sino_nao_lidas ? '-fill' : ''; ?>" aria-hidden="true"></i>
                <span class="notif-sino-num" <?php echo $sino_nao_lidas ? '' : 'hidden'; ?>><?php echo $sino_nao_lidas > 9 ? '9+' : $sino_nao_lidas; ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-painel">
                <div class="notif-painel-topo">
                    <strong>Notificações</strong>
                    <div class="notif-painel-botoes">
                        <button type="button" class="notif-marcar-todas" title="Marcar todas como lidas" <?php echo $sino_nao_lidas ? '' : 'hidden'; ?>><i class="bi bi-check2-all"></i><span>Marcar todas</span></button>
                        <a class="notif-expandir" href="<?php echo BASE_URL; ?>pages/notificacoes.php" title="Abrir em tela cheia" aria-label="Abrir notificações em tela cheia"><i class="bi bi-arrows-angle-expand"></i></a>
                    </div>
                </div>
                <div class="notif-painel-abas" role="tablist" aria-label="Filtrar notificações">
                    <button type="button" role="tab" class="ativo" data-filtro="todas" aria-selected="true">Todas</button>
                    <button type="button" role="tab" data-filtro="nao_lidas" aria-selected="false">Não lidas <span class="notif-aba-num" data-conta="nao_lidas"><?php echo $sino_nao_lidas; ?></span></button>
                    <button type="button" role="tab" data-filtro="lidas" aria-selected="false">Lidas</button>
                </div>
                <ul class="notif-painel-lista" aria-live="polite"></ul>
                <a class="notif-painel-rodape" href="<?php echo BASE_URL; ?>pages/notificacoes.php"><i class="bi bi-arrows-angle-expand"></i>Ver todas em tela cheia</a>
                <script type="application/json" class="notif-dados"><?php echo json_encode($sino_dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
            </div>
        </div>
        </div>
        <?php endif; ?>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">
                <?php
                // Destaca o link da página atual
                $pagina_atual = basename($_SERVER['PHP_SELF']);
// Subpáginas do módulo de compartilhamentos também destacam "Compartilhamentos"
if (strpos($pagina_atual, 'compartilhamen') !== false) {
    $pagina_atual = 'compartilhamentos.php';
}
                $links = [
                    'index.php'             => ['Início', 'index.php', 'bi-house'],
                    'sobre.php'             => ['Sobre', 'pages/sobre.php', 'bi-info-circle'],
                    'residuos.php'          => ['Resíduos', 'pages/residuos.php', 'bi-recycle'],
                    'compartilhamentos.php' => ['Compartilhamentos', 'pages/compartilhamentos.php', 'bi-arrow-left-right'],
                    'conteudos.php'         => ['Conteúdos', 'pages/conteudos.php', 'bi-book'],
                    'quiz.php'              => ['Quizzes', 'pages/quiz.php', 'bi-patch-question'],
                    'jogo.php'              => ['Jogo', 'pages/jogo.php', 'bi-controller'],
                    'pontos_coleta.php'     => ['Pontos de Coleta', 'pages/pontos_coleta.php', 'bi-geo-alt'],
                ];
                foreach ($links as $arquivo => $item):
                    $classe = ($pagina_atual === $arquivo) ? ' active' : '';
                ?>
                <li class="nav-item">
                    <a class="nav-link<?php echo $classe; ?>" href="<?php echo BASE_URL . $item[1]; ?>"<?php echo $classe ? ' aria-current="page"' : ''; ?>>
                        <i class="bi <?php echo $item[2]; ?> nav-icone" aria-hidden="true"></i><?php echo $item[0]; ?>
                    </a>
                </li>
                <?php endforeach; ?>

                <!-- Acesso do usuário: Entrar/Cadastrar (deslogado) ou nome + Sair (logado) -->
                <?php if (usuario_logado()): ?>
                <li class="nav-item">
                    <?php $primeiro_nome = strtok(trim((string) $_SESSION['nome_usuario']), ' ') ?: ''; ?>
                    <span class="nav-link nav-usuario" title="<?php echo htmlspecialchars($_SESSION['nome_usuario']); ?>">
                        <i class="bi bi-person-circle nav-icone-usuario" aria-hidden="true"></i>
                        <span class="nav-usuario-nome">Olá, <?php echo htmlspecialchars($primeiro_nome); ?></span>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/logout.php">Sair</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link nav-usuario" href="<?php echo BASE_URL; ?>pages/login.php"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Entrar</a>
                </li>
                <li class="nav-item ms-xl-2">
                    <a class="btn btn-success btn-sm px-3 my-1" href="<?php echo BASE_URL; ?>pages/cadastro.php">Cadastrar</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4 my-lg-5" id="conteudo" tabindex="-1">
