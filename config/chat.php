<?php
/**
 * Chat dos compartilhamentos: conversa entre quem ofereceu o material
 * e cada pessoa interessada. Cada conversa pertence a um interesse.
 *
 * Regras:
 * - Só os dois participantes enxergam a conversa.
 * - Dá para conversar enquanto o interesse está pendente ou aceito
 *   (inclusive depois da retirada, para quem foi aceito).
 * - Interesse recusado/cancelado ou material indisponível: a conversa
 *   fica só para leitura.
 */

require_once __DIR__ . '/notificacoes.php';

const CHAT_TAMANHO_MAXIMO = 1000;

const CHAT_FOTO_MAXIMO = 8 * 1024 * 1024; // 8 MB (o navegador já reduz antes de enviar)

/** Pasta das fotos do chat (fora de img/: só abre por pages/chat_imagem.php). */
function chat_pasta_fotos()
{
    return dirname(__DIR__) . '/storage/chat';
}

/**
 * Valida e guarda a foto enviada. Devolve [caminho_relativo, null] ou [null, 'erro'].
 * Sem foto enviada devolve [null, null].
 */
function chat_salvar_foto($arquivo)
{
    if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_INI_SIZE || ($arquivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
        || ($arquivo['size'] ?? 0) > CHAT_FOTO_MAXIMO) {
        return [null, 'A foto é grande demais. O limite é de 8 MB.'];
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
        return [null, 'Não foi possível enviar a foto. Tente de novo.'];
    }
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    $info = @getimagesize($arquivo['tmp_name']);
    if (!isset($tipos[$mime]) || !$info || $info[0] < 1 || $info[0] > 10000 || $info[1] > 10000) {
        return [null, 'Envie uma foto em JPG, PNG, WEBP ou GIF.'];
    }

    $sub = date('Y-m');
    $pasta = chat_pasta_fotos() . '/' . $sub;
    if (!is_dir($pasta) && !@mkdir($pasta, 0775, true) && !is_dir($pasta)) {
        return [null, 'Não foi possível guardar a foto agora.'];
    }
    chat_proteger_pasta();
    $nome = bin2hex(random_bytes(16)) . '.' . $tipos[$mime];
    $destino = $pasta . '/' . $nome;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return [null, 'Não foi possível guardar a foto agora.'];
    }
    if ($mime === 'image/jpeg') chat_limpar_metadados_jpeg($destino);
    return ['storage/chat/' . $sub . '/' . $nome, null];
}

/** Bloqueia o acesso direto à pasta no Apache (as fotos só saem por chat_imagem.php). */
function chat_proteger_pasta()
{
    $raiz = dirname(__DIR__) . '/storage';
    if (!is_file($raiz . '/.htaccess')) {
        @file_put_contents($raiz . '/.htaccess', "Require all denied\nDeny from all\n");
    }
}

/**
 * Remove os metadados EXIF/XMP de um JPEG (localização GPS, modelo do
 * celular etc.) sem precisar da extensão GD: apaga os blocos APP1 a APP15.
 */
function chat_limpar_metadados_jpeg($arquivo)
{
    $d = @file_get_contents($arquivo);
    if ($d === false || substr($d, 0, 2) !== "\xFF\xD8") return;
    $saida = "\xFF\xD8";
    $p = 2;
    $tam = strlen($d);
    while ($p + 4 <= $tam && $d[$p] === "\xFF") {
        $marcador = ord($d[$p + 1]);
        if ($marcador === 0xDA) break; // começo da imagem em si: copia o resto
        $len = (ord($d[$p + 2]) << 8) | ord($d[$p + 3]);
        if ($len < 2 || $p + 2 + $len > $tam) return; // arquivo estranho: deixa como está
        if (!($marcador >= 0xE1 && $marcador <= 0xEF)) {
            $saida .= substr($d, $p, 2 + $len);
        }
        $p += 2 + $len;
    }
    if ($p < $tam) {
        @file_put_contents($arquivo, $saida . substr($d, $p));
    }
}

/** Apaga do disco as fotos das conversas de uma oferta (antes de excluí-la). */
function chat_fotos_da_oferta(PDO $pdo, $id_oferta)
{
    $stmt = $pdo->prepare('SELECT m.imagem FROM mensagem m INNER JOIN interesse_compartilhamento i ON i.id_interesse = m.id_interesse WHERE i.id_compartilhamento = :o AND m.imagem IS NOT NULL');
    $stmt->execute([':o' => (int) $id_oferta]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
function chat_apagar_fotos(array $caminhos)
{
    $raiz = realpath(chat_pasta_fotos());
    foreach ($caminhos as $c) {
        $alvo = realpath(dirname(__DIR__) . '/' . $c);
        if (!$raiz || !$alvo || strpos($alvo, $raiz . DIRECTORY_SEPARATOR) !== 0 || !is_file($alvo)) continue;
        // No Windows o antivírus pode segurar um arquivo recém-enviado por um instante: tenta de novo
        for ($tentativa = 0; $tentativa < 5 && is_file($alvo); $tentativa++) {
            if (@unlink($alvo)) break;
            clearstatcache(true, $alvo);
            usleep(200000);
        }
        if (is_file($alvo)) {
            $e = error_get_last();
            error_log('Rhesys: não foi possível apagar a foto do chat ' . $alvo . ': ' . ($e['message'] ?? 'motivo desconhecido'));
        }
    }
}

/**
 * Dados da conversa, se o usuário participa dela. Senão, null.
 */
function chat_conversa(PDO $pdo, $id_interesse, $id_usuario)
{
    $stmt = $pdo->prepare("SELECT i.id_interesse, i.status AS status_interesse, i.id_usuario AS id_interessado,
                                  c.id_compartilhamento, c.id_usuario_ofertante, c.status AS status_oferta, c.imagem,
                                  c.tipo_oferta, c.preco, c.preco_negociavel,
                                  COALESCE(NULLIF(c.nome, ''), r.nome, 'material') AS material,
                                  ui.nome AS nome_interessado, uo.nome AS nome_ofertante
                           FROM interesse_compartilhamento i
                           INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento
                           LEFT JOIN residuo r ON r.id_residuo = c.id_residuo
                           INNER JOIN usuario ui ON ui.id_usuario = i.id_usuario
                           INNER JOIN usuario uo ON uo.id_usuario = c.id_usuario_ofertante
                           WHERE i.id_interesse = :id");
    $stmt->execute([':id' => (int) $id_interesse]);
    $c = $stmt->fetch();
    if (!$c) return null;

    $id_usuario = (int) $id_usuario;
    $sou_ofertante = (int) $c['id_usuario_ofertante'] === $id_usuario;
    if (!$sou_ofertante && (int) $c['id_interessado'] !== $id_usuario) return null;

    $c['sou_ofertante'] = $sou_ofertante;
    $c['id_outro'] = $sou_ofertante ? (int) $c['id_interessado'] : (int) $c['id_usuario_ofertante'];
    $c['nome_outro'] = $sou_ofertante ? $c['nome_interessado'] : $c['nome_ofertante'];
    $c['pode_enviar'] = chat_pode_enviar($c);
    $c['aviso'] = chat_aviso($c);
    return $c;
}

/** A conversa ainda aceita mensagens novas? */
function chat_pode_enviar(array $c)
{
    if (!in_array($c['status_interesse'], ['pendente', 'aceito'], true)) return false;
    if ($c['status_oferta'] === 'cancelado') return false;
    if ($c['status_oferta'] === 'concluido' && $c['status_interesse'] !== 'aceito') return false;
    return true;
}

/** Faixa informativa no topo da conversa. */
function chat_aviso(array $c)
{
    if ($c['status_oferta'] === 'cancelado') return 'Este material ficou indisponível. A conversa está encerrada.';
    if ($c['status_interesse'] === 'cancelado') return 'O interesse foi cancelado. A conversa está encerrada.';
    if ($c['status_interesse'] === 'recusado') return 'O material foi reservado para outra pessoa. A conversa está encerrada.';
    $venda = ($c['tipo_oferta'] ?? 'doacao') === 'venda';
    if ($c['status_oferta'] === 'concluido') return $venda ? 'Venda concluída. Vocês ainda podem trocar mensagens.' : 'Material retirado. Vocês ainda podem trocar mensagens.';
    if ($c['status_interesse'] === 'aceito') return $venda
        ? 'Interesse aceito! Combinem aqui o pagamento e a retirada. Dica: pague só ao receber o material.'
        : 'Interesse aceito! Combinem aqui o dia e o horário da retirada.';
    if ($venda) return $c['sou_ofertante']
        ? 'Negocie o preço e as condições. O Rhesys não intermedeia pagamentos.'
        : 'Tire dúvidas e negocie pelo chat. Por segurança, combine o pagamento só na retirada.';
    return $c['sou_ofertante']
        ? 'Tire as dúvidas da pessoa e, se estiver tudo certo, aceite o interesse.'
        : 'Seu interesse está pendente. Aproveite para tirar dúvidas sobre o material.';
}

/** Mensagens da conversa (todas ou só as depois de um id). */
function chat_mensagens(PDO $pdo, $id_interesse, $depois_de = 0, $limite = 200)
{
    $stmt = $pdo->prepare('SELECT id_mensagem, id_remetente, texto, imagem, apagada, lida, data_envio,
                                  DATEDIFF(CURDATE(), DATE(data_envio)) AS dias
                           FROM mensagem WHERE id_interesse = :i AND id_mensagem > :depois
                           ORDER BY id_mensagem DESC LIMIT :limite');
    $stmt->bindValue(':i', (int) $id_interesse, PDO::PARAM_INT);
    $stmt->bindValue(':depois', (int) $depois_de, PDO::PARAM_INT);
    $stmt->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
    $stmt->execute();
    return array_reverse($stmt->fetchAll());
}

/** Formato usado pela tela (HTML e JSON). */
function chat_mensagem_para_array(array $m, $id_usuario)
{
    $dias = (int) $m['dias'];
    $dia = $dias === 0 ? 'Hoje' : ($dias === 1 ? 'Ontem' : date('d/m/Y', strtotime($m['data_envio'])));
    return [
        'id' => (int) $m['id_mensagem'],
        'minha' => (int) $m['id_remetente'] === (int) $id_usuario,
        'texto' => $m['texto'],
        'imagem' => !empty($m['imagem']) ? BASE_URL . 'pages/chat_imagem.php?id=' . (int) $m['id_mensagem'] : null,
        'apagada' => (bool) ($m['apagada'] ?? false),
        'hora' => date('H:i', strtotime($m['data_envio'])),
        'dia' => $dia,
        'lida' => (bool) $m['lida'],
    ];
}

/** Marca como lidas as mensagens recebidas (e o aviso do sino dessa conversa). */
function chat_marcar_lidas(PDO $pdo, $id_interesse, $id_usuario)
{
    $stmt = $pdo->prepare('UPDATE mensagem SET lida = 1 WHERE id_interesse = :i AND id_remetente <> :u AND lida = 0');
    $stmt->execute([':i' => (int) $id_interesse, ':u' => (int) $id_usuario]);
    $stmt = $pdo->prepare("UPDATE notificacao SET lida = 1 WHERE id_usuario = :u AND tipo = 'mensagem_nova' AND link = :link AND lida = 0");
    $stmt->execute([':u' => (int) $id_usuario, ':link' => 'pages/conversas.php?id=' . (int) $id_interesse]);
}

/**
 * Envia uma mensagem. Devolve [id_mensagem, null] ou [0, 'erro'].
 */
function chat_enviar(PDO $pdo, array $c, $id_usuario, $texto, $foto = null)
{
    $texto = (string) $texto;
    if (!mb_check_encoding($texto, 'UTF-8')) $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8'); // descarta bytes inválidos
    $texto = trim(preg_replace("/\r\n?/", "\n", $texto));
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);
    $tem_foto = $foto && (($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    if ($texto === '' && !$tem_foto) return [0, 'Escreva uma mensagem ou escolha uma foto antes de enviar.'];
    if (mb_strlen($texto) > CHAT_TAMANHO_MAXIMO) return [0, 'A mensagem pode ter no máximo ' . CHAT_TAMANHO_MAXIMO . ' caracteres.'];
    if (!$c['pode_enviar']) return [0, 'Esta conversa está encerrada e não recebe novas mensagens.'];
    [$caminho_foto, $erro_foto] = chat_salvar_foto($tem_foto ? $foto : null);
    if ($erro_foto) return [0, $erro_foto];

    // Evita enviar a mesma mensagem duas vezes seguidas (clique duplo)
    $stmt = $pdo->prepare('SELECT texto, TIMESTAMPDIFF(SECOND, data_envio, NOW()) AS segundos FROM mensagem WHERE id_interesse = :i AND id_remetente = :u ORDER BY id_mensagem DESC LIMIT 1');
    $stmt->execute([':i' => $c['id_interesse'], ':u' => (int) $id_usuario]);
    $ultima = $stmt->fetch();
    if (!$caminho_foto && $ultima && $ultima['texto'] === $texto && (int) $ultima['segundos'] < 10) return [0, 'Essa mensagem acabou de ser enviada.'];

    $stmt = $pdo->prepare('INSERT INTO mensagem (id_interesse, id_remetente, texto, imagem) VALUES (:i, :u, :t, :img)');
    $stmt->execute([':i' => $c['id_interesse'], ':u' => (int) $id_usuario, ':t' => $texto, ':img' => $caminho_foto]);
    $id = (int) $pdo->lastInsertId();

    chat_marcar_lidas($pdo, $c['id_interesse'], $id_usuario); // quem responde já leu o que chegou
    notif_mensagem_nova($pdo, $c, $id_usuario, $caminho_foto ? ('📷 Foto' . ($texto !== '' ? ': ' . $texto : '')) : $texto);
    return [$id, null];
}

/**
 * Lista de conversas do usuário (como ofertante ou interessado),
 * da mais recente para a mais antiga, com a última mensagem e não lidas.
 */
function chat_conversas_usuario(PDO $pdo, $id_usuario)
{
    $stmt = $pdo->prepare("SELECT i.id_interesse, i.status AS status_interesse, i.id_usuario AS id_interessado, i.data_interesse,
                                  c.id_compartilhamento, c.id_usuario_ofertante, c.status AS status_oferta, c.imagem,
                                  COALESCE(NULLIF(c.nome, ''), r.nome, 'material') AS material,
                                  ui.nome AS nome_interessado, uo.nome AS nome_ofertante,
                                  um.texto AS ultima_texto, um.imagem AS ultima_imagem, um.apagada AS ultima_apagada, um.id_remetente AS ultima_remetente, um.data_envio AS ultima_data,
                                  (SELECT COUNT(*) FROM mensagem m2 WHERE m2.id_interesse = i.id_interesse AND m2.id_remetente <> :u1 AND m2.lida = 0 AND m2.apagada = 0) AS nao_lidas,
                                  TIMESTAMPDIFF(MINUTE, COALESCE(um.data_envio, i.data_interesse), NOW()) AS minutos,
                                  DATEDIFF(CURDATE(), DATE(COALESCE(um.data_envio, i.data_interesse))) AS dias
                           FROM interesse_compartilhamento i
                           INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento
                           LEFT JOIN residuo r ON r.id_residuo = c.id_residuo
                           INNER JOIN usuario ui ON ui.id_usuario = i.id_usuario
                           INNER JOIN usuario uo ON uo.id_usuario = c.id_usuario_ofertante
                           LEFT JOIN mensagem um ON um.id_mensagem = (SELECT MAX(m3.id_mensagem) FROM mensagem m3 WHERE m3.id_interesse = i.id_interesse)
                           WHERE i.id_usuario = :u2 OR c.id_usuario_ofertante = :u3
                           ORDER BY COALESCE(um.data_envio, i.data_interesse) DESC, i.id_interesse DESC");
    $stmt->execute([':u1' => (int) $id_usuario, ':u2' => (int) $id_usuario, ':u3' => (int) $id_usuario]);
    $lista = [];
    foreach ($stmt->fetchAll() as $c) {
        $sou_ofertante = (int) $c['id_usuario_ofertante'] === (int) $id_usuario;
        $c['sou_ofertante'] = $sou_ofertante;
        $c['nome_outro'] = $sou_ofertante ? $c['nome_interessado'] : $c['nome_ofertante'];
        $c['pode_enviar'] = chat_pode_enviar($c);
        $lista[] = $c;
    }
    return $lista;
}

/** Formato de um item da lista de conversas (HTML e JSON). */
function chat_conversa_para_array(array $c, $id_usuario)
{
    if ($c['ultima_texto'] !== null) {
        $texto_ultima = $c['ultima_apagada'] ? '🚫 Mensagem apagada'
            : ($c['ultima_imagem'] ? ('📷 Foto' . ($c['ultima_texto'] !== '' ? ': ' . $c['ultima_texto'] : '')) : $c['ultima_texto']);
        $previa = ((int) $c['ultima_remetente'] === (int) $id_usuario ? 'Você: ' : '') . $texto_ultima;
    } else {
        $previa = $c['sou_ofertante'] ? 'Demonstrou interesse. Diga olá!' : 'Você demonstrou interesse. Envie uma mensagem!';
    }
    return [
        'id' => (int) $c['id_interesse'],
        'nome' => $c['nome_outro'],
        'inicial' => mb_strtoupper(mb_substr(trim((string) $c['nome_outro']), 0, 1)),
        'material' => $c['material'],
        'papel' => $c['sou_ofertante'] ? 'Interessado(a) no seu material' : 'Ofertante',
        'previa' => mb_strimwidth(preg_replace('/\s+/', ' ', $previa), 0, 80, '…'),
        'quando' => notificacao_tempo($c['minutos'], $c['ultima_data'] ?? $c['data_interesse'], $c['dias']),
        'nao_lidas' => (int) $c['nao_lidas'],
        'encerrada' => !$c['pode_enviar'],
    ];
}

/**
 * Apaga uma mensagem para os dois lados. Só quem enviou pode apagar.
 * A mensagem continua na conversa como "mensagem apagada" (sem texto nem foto).
 * Devolve null em caso de sucesso ou a mensagem de erro.
 */
function chat_apagar_mensagem(PDO $pdo, array $c, $id_mensagem, $id_usuario)
{
    $stmt = $pdo->prepare('SELECT id_mensagem, id_remetente, imagem, apagada FROM mensagem WHERE id_mensagem = :m AND id_interesse = :i');
    $stmt->execute([':m' => (int) $id_mensagem, ':i' => $c['id_interesse']]);
    $m = $stmt->fetch();
    if (!$m) return 'Mensagem não encontrada.';
    if ((int) $m['id_remetente'] !== (int) $id_usuario) return 'Você só pode apagar as suas próprias mensagens.';
    if ($m['apagada']) return null;

    $pdo->prepare("UPDATE mensagem SET texto = '', imagem = NULL, apagada = 1 WHERE id_mensagem = :m")
        ->execute([':m' => $m['id_mensagem']]);
    if ($m['imagem']) chat_apagar_fotos([$m['imagem']]);

    // Se o aviso no sino ainda não foi lido, tira dele o trecho da mensagem apagada
    $nome = (int) $id_usuario === (int) $c['id_usuario_ofertante'] ? $c['nome_ofertante'] : $c['nome_interessado'];
    $pdo->prepare("UPDATE notificacao SET mensagem = :msg WHERE id_usuario = :u AND tipo = 'mensagem_nova' AND link = :link AND lida = 0")
        ->execute([
            ':msg' => "$nome enviou mensagens sobre “{$c['material']}”. Abra a conversa para ver.",
            ':u' => $c['id_outro'],
            ':link' => 'pages/conversas.php?id=' . (int) $c['id_interesse'],
        ]);
    return null;
}

/** Ids das mensagens apagadas da conversa (para atualizar a tela dos dois lados). */
function chat_ids_apagadas(PDO $pdo, $id_interesse)
{
    $stmt = $pdo->prepare('SELECT id_mensagem FROM mensagem WHERE id_interesse = :i AND apagada = 1');
    $stmt->execute([':i' => (int) $id_interesse]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Total de mensagens não lidas do usuário (ícone de balão no topo). */
function chat_nao_lidas_total(PDO $pdo, $id_usuario)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mensagem m
                           INNER JOIN interesse_compartilhamento i ON i.id_interesse = m.id_interesse
                           INNER JOIN compartilhamento_residuo c ON c.id_compartilhamento = i.id_compartilhamento
                           WHERE m.lida = 0 AND m.apagada = 0 AND m.id_remetente <> :u1 AND (i.id_usuario = :u2 OR c.id_usuario_ofertante = :u3)');
    $stmt->execute([':u1' => (int) $id_usuario, ':u2' => (int) $id_usuario, ':u3' => (int) $id_usuario]);
    return (int) $stmt->fetchColumn();
}

/**
 * Avisa a outra pessoa da conversa. Se ela ainda não leu o aviso anterior
 * desta mesma conversa, o aviso é atualizado (sem novo e-mail), para não
 * gerar uma notificação e um e-mail por mensagem.
 */
function notif_mensagem_nova(PDO $pdo, array $c, $id_remetente, $texto)
{
    $nome = (int) $id_remetente === (int) $c['id_usuario_ofertante'] ? $c['nome_ofertante'] : $c['nome_interessado'];
    $link = 'pages/conversas.php?id=' . (int) $c['id_interesse'];
    $trecho = mb_strimwidth(preg_replace('/\s+/', ' ', $texto), 0, 140, '…');
    $titulo = 'Nova mensagem de ' . $nome;
    $mensagem = "$nome enviou uma mensagem sobre “{$c['material']}”: “{$trecho}”";

    $stmt = $pdo->prepare("SELECT id_notificacao FROM notificacao WHERE id_usuario = :u AND tipo = 'mensagem_nova' AND link = :link AND lida = 0 ORDER BY id_notificacao DESC LIMIT 1");
    $stmt->execute([':u' => $c['id_outro'], ':link' => $link]);
    $existente = $stmt->fetchColumn();
    if ($existente) {
        $pdo->prepare('UPDATE notificacao SET titulo = :t, mensagem = :m, data_criacao = NOW() WHERE id_notificacao = :id')
            ->execute([':t' => $titulo, ':m' => $mensagem . ' (e outras mensagens)', ':id' => $existente]);
        return;
    }
    notificar($pdo, $c['id_outro'], 'mensagem_nova', $titulo, $mensagem, $link);
}
