<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo_pagina) ? $titulo_pagina . ' - ' . NOME_SITE : NOME_SITE; ?></title>

    <!-- Bootstrap via CDN (RNF03 / RNF04) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS próprio -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>index.php">
            <img src="<?php echo BASE_URL; ?>img/logo-icon.svg" alt="Rhesys" width="32" height="32">
            <span class="fw-bold">Rhe<span class="text-info">sys</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">Início</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/sobre.php">Sobre</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/residuos.php">Pesquisar Resíduos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/compartilhamentos.php">Compartilhamentos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/quiz.php">Quizzes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>pages/pontos_coleta.php">Pontos de Coleta</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-5">
