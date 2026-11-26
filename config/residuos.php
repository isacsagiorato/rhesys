<?php
/**
 * Regras e utilitários do módulo de Resíduos.
 *
 * - TIPOS_RESIDUO: tipos de material (coluna residuo.tipo_material);
 * - classe_residuo(): rótulo curto, cor e ícone de cada classe ABNT NBR 10004;
 * - imagem_residuo(): foto real do resíduo (img/fotos/residuos/) ou ilustração.
 *
 * Incluir DEPOIS de config/config.php (usa BASE_URL).
 */

// Tipos de material, na ordem em que aparecem na página de resíduos.
const TIPOS_RESIDUO = [
    'metal' => [
        'rotulo' => 'Metais',
        'descricao' => 'Latas, panelas, tampinhas, papel alumínio e sucatas.',
        'foto' => 'img/fotos/metais.jpg',
        'icone' => 'bi-nut',
    ],
    'plastico' => [
        'rotulo' => 'Plásticos',
        'descricao' => 'Garrafas PET, potes, sacolas, frascos e descartáveis.',
        'foto' => 'img/fotos/plasticos.jpg',
        'icone' => 'bi-cup-straw',
    ],
    'papel' => [
        'rotulo' => 'Papel e papelão',
        'descricao' => 'Caixas, jornais, cadernos, sulfite e embalagens longa vida.',
        'foto' => 'img/fotos/papel.jpg',
        'icone' => 'bi-newspaper',
    ],
    'vidro' => [
        'rotulo' => 'Vidros',
        'descricao' => 'Garrafas, potes, copos, frascos, espelhos e vidros planos.',
        'foto' => 'img/fotos/vidro.jpg',
        'icone' => 'bi-cup',
    ],
    'organico' => [
        'rotulo' => 'Orgânicos',
        'descricao' => 'Restos de comida, cascas, borra de café, folhas e podas.',
        'foto' => 'img/fotos/lixo-organico.jpg',
        'icone' => 'bi-flower1',
    ],
    'eletronico' => [
        'rotulo' => 'Eletroeletrônicos',
        'descricao' => 'Celulares, computadores, cabos, pilhas e aparelhos elétricos.',
        'foto' => 'img/fotos/eletronicos.jpg',
        'icone' => 'bi-cpu',
    ],
    'especial' => [
        'rotulo' => 'Descarte especial',
        'descricao' => 'Óleos, tintas, remédios, lâmpadas, pneus e químicos: nunca no lixo comum.',
        'foto' => 'img/fotos/residuos/restos-de-tinta-e-solvente.jpg',
        'icone' => 'bi-exclamation-triangle',
    ],
    'construcao' => [
        'rotulo' => 'Construção e inertes',
        'descricao' => 'Entulho, gesso, cerâmica, louça, pedras e areia.',
        'foto' => 'img/fotos/residuos/entulho-de-construcao.jpg',
        'icone' => 'bi-bricks',
    ],
    'textil' => [
        'rotulo' => 'Roupas e tecidos',
        'descricao' => 'Roupas, calçados, lençóis, toalhas, bolsas e mochilas.',
        'foto' => 'img/fotos/residuos/roupa-e-tecido-velho.jpg',
        'icone' => 'bi-bag',
    ],
    'rejeito' => [
        'rotulo' => 'Rejeitos',
        'descricao' => 'Itens sem reciclagem viável, que vão para o lixo comum.',
        'foto' => 'img/fotos/residuos/bituca-de-cigarro.jpg',
        'icone' => 'bi-trash3',
    ],
];

/** Rótulo do tipo de material (ou "Outros" quando não definido). */
function rotulo_tipo_residuo($tipo)
{
    return TIPOS_RESIDUO[$tipo]['rotulo'] ?? 'Outros';
}

/**
 * Rótulo curto, explicação, classe CSS e ícone da classe ABNT NBR 10004.
 * Mesmas cores usadas na página de Conteúdos (Classe I vermelho,
 * II A âmbar, II B cinza).
 */
function classe_residuo($nome_classificacao)
{
    $nome = (string) $nome_classificacao;
    if (stripos($nome, 'II B') !== false) {
        return ['chave' => '2b', 'curto' => 'Classe II B', 'resumo' => 'Inerte', 'css' => 'classe-2b', 'icone' => 'bi-bricks'];
    }
    if (stripos($nome, 'II A') !== false) {
        return ['chave' => '2a', 'curto' => 'Classe II A', 'resumo' => 'Não inerte', 'css' => 'classe-2a', 'icone' => 'bi-tree-fill'];
    }
    if (preg_match('/classe\s+i\b/i', $nome)) {
        return ['chave' => '1', 'curto' => 'Classe I', 'resumo' => 'Perigoso', 'css' => 'classe-1', 'icone' => 'bi-exclamation-triangle-fill'];
    }
    return ['chave' => '', 'curto' => $nome, 'resumo' => '', 'css' => 'classe-outra', 'icone' => 'bi-tag'];
}

/** Texto em minúsculas e sem acentos (para buscas e nomes de arquivo). */
function normalizar_texto($texto)
{
    $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
}

/** Nome de arquivo da foto do resíduo: "Pão e bolo velhos" -> "pao-e-bolo-velhos". */
function slug_residuo($nome)
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', normalizar_texto($nome)), '-');
}

/**
 * Caminho relativo (img/fotos/...) da foto do resíduo, sem BASE_URL.
 * 1) foto específica em img/fotos/residuos/ (fotos reais do Wikimedia Commons);
 * 2) foto do tipo de material; 3) ilustração pela classificação.
 */
function caminho_foto_residuo($nome_residuo, $tipo = null, $nome_classificacao = '')
{
    // Resíduos antigos cujo arquivo não segue o nome completo
    $fotos_especiais = [
        'eletrodomestico (geladeira, maquina de lavar)' => 'eletrodomestico',
        'aerossol (spray)' => 'aerossol-spray',
        'toner e cartucho de impressora' => 'toner-e-cartucho',
        'embalagem cartonada (tetra pak)' => 'embalagem-cartonada',
        'louca quebrada (pratos e canecas)' => 'louca-quebrada',
        'pedra e marmore (residuo de reforma)' => 'pedra-e-marmore',
        'mato e podas de jardim' => 'mato-e-podas',
    ];
    $nome_norm = normalizar_texto($nome_residuo);
    $slug = $fotos_especiais[$nome_norm] ?? slug_residuo($nome_residuo);
    $raiz = dirname(__DIR__) . '/';
    if ($slug !== '' && is_file($raiz . 'img/fotos/residuos/' . $slug . '.jpg')) {
        return 'img/fotos/residuos/' . $slug . '.jpg';
    }
    if (isset(TIPOS_RESIDUO[$tipo])) {
        return TIPOS_RESIDUO[$tipo]['foto'];
    }
    $classe = classe_residuo($nome_classificacao)['chave'];
    if ($classe === '1') return 'img/fotos/eletronicos.jpg';
    if ($classe === '2a') return 'img/fotos/reciclaveis.jpg';
    return 'img/fotos/coleta.jpg';
}

/**
 * URL pública da foto do resíduo. O ?v= força o navegador a baixar a
 * versão nova depois de uma troca de fotos.
 */
function imagem_residuo($nome_residuo, $tipo = null, $nome_classificacao = '')
{
    return BASE_URL . caminho_foto_residuo($nome_residuo, $tipo, $nome_classificacao) . '?v=7';
}

/** Crédito da foto (autor e licença), quando ela vem do Wikimedia Commons. */
function credito_foto_residuo($caminho_foto)
{
    static $creditos = null;
    if ($creditos === null) {
        $creditos = require __DIR__ . '/fontes_fotos_residuos.php';
    }
    if (strpos($caminho_foto, 'img/fotos/residuos/') !== 0) {
        return null;
    }
    return $creditos[basename($caminho_foto, '.jpg')] ?? null;
}
