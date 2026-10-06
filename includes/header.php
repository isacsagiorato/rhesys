<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#04281c">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' - ' . NOME_SITE : NOME_SITE; ?></title>

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

    <!-- CSS próprio (?v=2 força o navegador a baixar a versão nova e não usar cache) -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=2">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-rhe">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>index.php">
            <img src="<?php echo BASE_URL; ?>img/logo-icon.svg" alt="Logotipo Rhesys" width="38" height="38">
            <span>Rhe<span class="brand-sys">sys</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php
                // Destaca o link da página atual
                $pagina_atual = basename($_SERVER['PHP_SELF']);
                $links = [
                    'index.php'             => ['Início', 'index.php'],
                    'sobre.php'             => ['Sobre', 'pages/sobre.php'],
                    'residuos.php'          => ['Resíduos', 'pages/residuos.php'],
                    'compartilhamentos.php' => ['Compartilhamentos', 'pages/compartilhamentos.php'],
                    'quiz.php'              => ['Quizzes', 'pages/quiz.php'],
                    'pontos_coleta.php'     => ['Pontos de Coleta', 'pages/pontos_coleta.php'],
                ];
                foreach ($links as $arquivo => $item):
                    $classe = ($pagina_atual === $arquivo) ? ' active' : '';
                ?>
                <li class="nav-item">
                    <a class="nav-link<?php echo $classe; ?>" href="<?php echo BASE_URL . $item[1]; ?>">
                        <?php echo $item[0]; ?>
                    </a>
                </li>
                <?php endforeach; ?>

                <!-- Acesso do usuário: Entrar/Cadastrar (deslogado) ou nome + Sair (logado) -->
                <?php if (usuario_logado()): ?>
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="bi bi-person-circle"></i>
                        Olá, <?php echo htmlspecialchars($_SESSION['nome_usuario']); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/logout.php">Sair</a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/login.php">Entrar</a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-success btn-sm px-3 my-1" href="<?php echo BASE_URL; ?>pages/cadastro.php">Cadastrar</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4 my-lg-5">
