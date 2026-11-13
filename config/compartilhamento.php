<?php
/**
 * Regras e utilitários do módulo de Compartilhamentos (economia circular).
 *
 * Incluir DEPOIS de config/config.php (define BASE_URL) e, quando a página
 * acessar o banco, DEPOIS de config/conexao.php ($pdo).
 */

// Categorias de materiais que podem ser oferecidos para reaproveitamento.
const CATEGORIAS_MATERIAIS = [
    'moveis'           => 'Móveis',
    'eletronicos'      => 'Eletrônicos',
    'eletrodomesticos' => 'Eletrodomésticos',
    'roupas'           => 'Roupas e calçados',
    'livros'           => 'Livros e papel',
    'brinquedos'       => 'Brinquedos',
    'ferramentas'      => 'Ferramentas',
    'construcao'       => 'Materiais de construção',
    'plasticos'        => 'Plásticos e embalagens',
    'metais'           => 'Metais',
    'vidros'           => 'Vidros',
    'organicos'        => 'Orgânicos / compostagem',
    'outros'           => 'Outros',
];

// Estados de conservação aceitos no cadastro e no filtro.
const ESTADOS_CONSERVACAO = [
    'novo'           => 'Novo',
    'seminovo'       => 'Seminovo',
    'usado'          => 'Usado',
    'precisa_reparo' => 'Precisa de reparo',
];

// Status da oferta (valores da coluna status do banco).
const STATUS_COMPARTILHAMENTO = [
    'disponivel' => 'Disponível',
    'reservado'  => 'Reservado',
    'concluido'  => 'Retirado',
    'cancelado'  => 'Indisponível',
];

/** Rótulo amigável do status de uma oferta. */
function rotulo_status_compartilhamento($status)
{
    return STATUS_COMPARTILHAMENTO[$status] ?? (string) $status;
}

/** Classe Bootstrap (text-bg-*) para o badge do status. */
function classe_status_compartilhamento($status)
{
    return [
        'disponivel' => 'success',
        'reservado'  => 'warning',
        'concluido'  => 'secondary',
        'cancelado'  => 'danger',
    ][$status] ?? 'secondary';
}

/** Rótulo do estado de conservação. */
function rotulo_estado_conservacao($estado)
{
    return ESTADOS_CONSERVACAO[$estado] ?? (string) $estado;
}

/** Classe visual (badge colorido) do estado de conservação. */
function classe_estado_conservacao($estado)
{
    return [
        'novo'           => 'estado-novo',
        'seminovo'       => 'estado-seminovo',
        'usado'          => 'estado-usado',
        'precisa_reparo' => 'estado-reparo',
    ][$estado] ?? 'estado-usado';
}

/** Rótulo da categoria (ou a classificação legada de resíduo). */
function rotulo_categoria_material($categoria)
{
    if (isset(CATEGORIAS_MATERIAIS[$categoria])) {
        return CATEGORIAS_MATERIAIS[$categoria];
    }
    return $categoria !== null && $categoria !== '' ? $categoria : 'Resíduo';
}

/** URL da imagem da oferta (ou ilustração padrão quando não há foto). */
function imagem_material($imagem)
{
    return ($imagem !== null && $imagem !== '')
        ? BASE_URL . $imagem
        : BASE_URL . 'img/fotos/reaproveitar.jpg';
}

/**
 * Valida o upload de imagem da oferta.
 * Devolve [$mime, $ext] quando há arquivo válido ou null quando não há arquivo.
 * Em caso de erro, adiciona a mensagem ao array $erros.
 */
function compartilhamento_validar_imagem($arquivo, array &$erros)
{
    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $erros[] = 'Não foi possível enviar a imagem. Tente novamente.';
        return null;
    }
    if (($arquivo['size'] ?? 0) > 5 * 1024 * 1024) {
        $erros[] = 'A imagem deve ter no máximo 5 MB.';
        return null;
    }
    if (!is_uploaded_file($arquivo['tmp_name'])) {
        $erros[] = 'O arquivo enviado não é um upload válido.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    if (!isset($extensoes[$mime]) || getimagesize($arquivo['tmp_name']) === false) {
        $erros[] = 'Use uma imagem JPG, PNG, WEBP ou GIF válida.';
        return null;
    }

    return [$mime, $extensoes[$mime]];
}

/** Salva a imagem enviada e devolve o caminho relativo (img/uploads/...). */
function compartilhamento_salvar_imagem($arquivo, $ext)
{
    $diretorio = dirname(__DIR__) . '/img/uploads';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
    }
    $nome_arquivo = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($arquivo['tmp_name'], $diretorio . '/' . $nome_arquivo)) {
        throw new RuntimeException('Não foi possível salvar a imagem enviada.');
    }

    return 'img/uploads/' . $nome_arquivo;
}

/** Remove o arquivo de imagem (apenas dentro de img/uploads). */
function compartilhamento_remover_imagem($caminho)
{
    if ($caminho === null || $caminho === '') {
        return;
    }
    $raizUploads = realpath(dirname(__DIR__) . '/img/uploads');
    $alvo = realpath(dirname(__DIR__) . '/' . $caminho);
    if ($alvo !== false && $raizUploads !== false
        && strpos($alvo, $raizUploads . DIRECTORY_SEPARATOR) === 0
        && $alvo !== $raizUploads
        && is_file($alvo)) {
        unlink($alvo);
    }
}