<?php
/**
 * Sistema de notificações do Rhesys.
 *
 * - Cada aviso é gravado na tabela notificacao e aparece no sino do topo.
 * - Os avisos importantes (interesse, aceite, retirada...) também vão
 *   para o e-mail cadastrado da pessoa.
 * - O e-mail só é enviado DEPOIS que a página termina (shutdown), então
 *   se a operação der erro e for desfeita (rollback), nada é enviado.
 */

require_once __DIR__ . '/email.php';

// Tipos de notificação: ícone, cor do ícone e se vai para o e-mail
const TIPOS_NOTIFICACAO = [
    'mensagem_nova'       => ['icone' => 'bi-chat-dots',       'cor' => 'verde',    'email' => true],
    'interesse_novo'      => ['icone' => 'bi-hand-thumbs-up',  'cor' => 'verde',    'email' => true],
    'interesse_cancelado' => ['icone' => 'bi-x-circle',        'cor' => 'cinza',    'email' => true],
    'interesse_aceito'    => ['icone' => 'bi-check2-circle',   'cor' => 'verde',    'email' => true],
    'interesse_recusado'  => ['icone' => 'bi-info-circle',     'cor' => 'ambar',    'email' => true],
    'oferta_retirada'     => ['icone' => 'bi-bag-check',       'cor' => 'verde',    'email' => true],
    'oferta_indisponivel' => ['icone' => 'bi-slash-circle',    'cor' => 'vermelho', 'email' => true],
    'oferta_excluida'     => ['icone' => 'bi-trash3',          'cor' => 'vermelho', 'email' => true],
    'oferta_editada'      => ['icone' => 'bi-pencil-square',   'cor' => 'cinza',    'email' => false],
    'oferta_publicada'    => ['icone' => 'bi-megaphone',       'cor' => 'lima',     'email' => false],
    'boas_vindas'         => ['icone' => 'bi-stars',           'cor' => 'lima',     'email' => true],
    'conteudo_novo'       => ['icone' => 'bi-book',            'cor' => 'verde',    'email' => false],
    'quiz_novo'           => ['icone' => 'bi-patch-question',  'cor' => 'verde',    'email' => false],
];

/** IDs de notificações criadas nesta requisição que ainda precisam de e-mail. */
$GLOBALS['notificacoes_fila_email'] = [];

/**
 * Cria uma notificação para um usuário.
 *
 * @param string      $link  caminho interno, ex.: 'pages/compartilhamento.php?id=3'
 * @param bool|null   $email null = segue o padrão do tipo
 */
function notificar(PDO $pdo, $id_usuario, $tipo, $titulo, $mensagem, $link = null, $email = null)
{
    $id_usuario = (int) $id_usuario;
    if ($id_usuario <= 0) return 0;
    $email = $email ?? (TIPOS_NOTIFICACAO[$tipo]['email'] ?? false);

    $stmt = $pdo->prepare('INSERT INTO notificacao (id_usuario, tipo, titulo, mensagem, link, enviar_email) VALUES (:usuario, :tipo, :titulo, :mensagem, :link, :email)');
    $stmt->bindValue(':usuario', $id_usuario, PDO::PARAM_INT);
    $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':titulo', mb_substr($titulo, 0, 150));
    $stmt->bindValue(':mensagem', $mensagem);
    $stmt->bindValue(':link', $link);
    $stmt->bindValue(':email', $email ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
    $id = (int) $pdo->lastInsertId();

    if ($email) {
        if (!$GLOBALS['notificacoes_fila_email']) {
            register_shutdown_function('notificacoes_enviar_fila', $pdo);
        }
        $GLOBALS['notificacoes_fila_email'][] = $id;
    }
    return $id;
}

/**
 * Avisa todos os usuários (novidades do site). Só no site, sem e-mail,
 * para não lotar a caixa de entrada de ninguém.
 */
function notificar_todos(PDO $pdo, $tipo, $titulo, $mensagem, $link = null, $exceto = null)
{
    $stmt = $pdo->prepare('INSERT INTO notificacao (id_usuario, tipo, titulo, mensagem, link) SELECT id_usuario, :tipo, :titulo, :mensagem, :link FROM usuario WHERE id_usuario <> :exceto');
    $stmt->bindValue(':tipo', $tipo);
    $stmt->bindValue(':titulo', mb_substr($titulo, 0, 150));
    $stmt->bindValue(':mensagem', $mensagem);
    $stmt->bindValue(':link', $link);
    $stmt->bindValue(':exceto', (int) $exceto, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Envia os e-mails da fila (roda ao fim da página, depois do commit).
 */
function notificacoes_enviar_fila(PDO $pdo)
{
    $ids = array_values(array_unique(array_map('intval', $GLOBALS['notificacoes_fila_email'])));
    $GLOBALS['notificacoes_fila_email'] = [];
    if (!$ids) return;

    // Libera o navegador antes de conversar com o servidor de e-mail
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    try {
        if ($pdo->inTransaction()) $pdo->rollBack(); // transação esquecida = nada foi confirmado
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT n.*, u.nome, u.email FROM notificacao n INNER JOIN usuario u ON u.id_usuario = n.id_usuario WHERE n.id_notificacao IN ($marcadores) AND n.enviar_email = 1 AND n.email_enviado = 0");
        $stmt->execute($ids);
        $marcar = $pdo->prepare('UPDATE notificacao SET email_enviado = 1 WHERE id_notificacao = :id');
        foreach ($stmt->fetchAll() as $n) {
            [$html, $texto] = email_notificacao_conteudo($n);
            if (enviar_email($n['email'], $n['nome'], $n['titulo'] . ' | Rhesys', $html, $texto)) {
                $marcar->execute([':id' => $n['id_notificacao']]);
            }
        }
    } catch (Throwable $e) {
        error_log('Rhesys: falha ao processar e-mails de notificação: ' . $e->getMessage());
    }
}

/** Monta o e-mail (HTML + texto puro) de uma notificação. */
function email_notificacao_conteudo(array $n)
{
    $site = email_url_site();
    $primeiro_nome = strtok(trim((string) $n['nome']), ' ') ?: 'Olá';
    $link = $site . (notificacao_link_seguro($n['link']) ?? 'pages/notificacoes.php');
    $h = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $html = '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:#f4faf6;font-family:Inter,Segoe UI,Arial,sans-serif;color:#20302a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4faf6;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px -12px rgba(4,66,41,.18);">'
        . '<tr><td style="background:#064e3b;background:linear-gradient(135deg,#064e3b,#047857);padding:26px 32px;">'
        . '<span style="font-family:Outfit,Segoe UI,Arial,sans-serif;font-size:24px;font-weight:800;color:#ffffff;letter-spacing:-.5px;">Rhe<span style="color:#a3e635;">sys</span></span>'
        . '<div style="color:#a7f3d0;font-size:13px;margin-top:4px;">Gestão inteligente de resíduos</div></td></tr>'
        . '<tr><td style="padding:32px;">'
        . '<p style="margin:0 0 6px;font-size:15px;color:#5b6b64;">Olá, ' . $h($primeiro_nome) . '!</p>'
        . '<h1 style="margin:0 0 14px;font-family:Outfit,Segoe UI,Arial,sans-serif;font-size:22px;line-height:1.3;color:#0d2b1e;">' . $h($n['titulo']) . '</h1>'
        . '<p style="margin:0 0 26px;font-size:15px;line-height:1.65;color:#20302a;">' . nl2br($h($n['mensagem'])) . '</p>'
        . '<a href="' . $h($link) . '" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 26px;border-radius:999px;">Ver no Rhesys</a>'
        . '</td></tr>'
        . '<tr><td style="padding:18px 32px 26px;border-top:1px solid #d1fae5;font-size:12px;line-height:1.6;color:#7b8a83;">'
        . 'Você recebeu este e-mail porque tem uma conta no Rhesys. Todas as suas notificações ficam no sino do site: '
        . '<a href="' . $h($site . 'pages/notificacoes.php') . '" style="color:#059669;">ver notificações</a>.'
        . '</td></tr></table></td></tr></table></body></html>';

    $texto = "Olá, $primeiro_nome!\n\n" . $n['titulo'] . "\n\n" . $n['mensagem'] . "\n\nVer no Rhesys: $link\n\n"
        . "Você recebeu este e-mail porque tem uma conta no Rhesys.\nTodas as suas notificações: {$site}pages/notificacoes.php\n";

    return [$html, $texto];
}

/**
 * Só aceita links internos do próprio site (evita redirecionar para fora).
 * Devolve o caminho limpo ou null.
 */
function notificacao_link_seguro($link)
{
    $link = trim((string) $link);
    if ($link === '') return null;
    return preg_match('~^(index\.php|pages/[a-z0-9_]+\.php)(\?[A-Za-z0-9_=&%.\-]*)?(#[A-Za-z0-9_\-]*)?$~', $link) ? $link : null;
}

/** Texto do botão que leva até o assunto da notificação. */
function notificacao_rotulo_link($link)
{
    $link = (string) $link;
    if (strpos($link, 'conversas.php') !== false) return 'Abrir a conversa';
    if (strpos($link, 'meus_compartilhamentos') !== false) return 'Ver meus compartilhamentos';
    if (strpos($link, 'compartilhamento.php') !== false) return 'Ver o material';
    if (strpos($link, 'compartilhamentos.php') !== false) return 'Ver outros materiais';
    if (strpos($link, 'conteudos.php') !== false) return 'Ler o conteúdo';
    if (strpos($link, 'quiz.php') !== false) return 'Jogar o quiz';
    if (strpos($link, 'residuos.php') !== false) return 'Explorar os resíduos';
    return 'Abrir';
}

/** Quantidade de notificações não lidas do usuário. */
function notificacoes_nao_lidas(PDO $pdo, $id_usuario)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notificacao WHERE id_usuario = :u AND lida = 0');
    $stmt->bindValue(':u', (int) $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/** Últimas notificações do usuário (minutos calculados pelo relógio do MySQL). */
function notificacoes_listar(PDO $pdo, $id_usuario, $limite = 8, $filtro = 'todas', $pular = 0)
{
    // $filtro: 'todas', 'nao_lidas' ou 'lidas' (true = só não lidas, por compatibilidade)
    if ($filtro === true) $filtro = 'nao_lidas';
    $condicao = $filtro === 'nao_lidas' ? ' AND lida = 0' : ($filtro === 'lidas' ? ' AND lida = 1' : '');
    $sql = 'SELECT id_notificacao, tipo, titulo, mensagem, link, lida, data_criacao,
                   TIMESTAMPDIFF(MINUTE, data_criacao, NOW()) AS minutos,
                   DATEDIFF(CURDATE(), DATE(data_criacao)) AS dias
            FROM notificacao WHERE id_usuario = :u' . $condicao . '
            ORDER BY data_criacao DESC, id_notificacao DESC LIMIT :limite OFFSET :pular';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':u', (int) $id_usuario, PDO::PARAM_INT);
    $stmt->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
    $stmt->bindValue(':pular', (int) $pular, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** "agora", "há 5 min", "há 2 h", "ontem", "há 3 dias", "12/03/2026". */
function notificacao_tempo($minutos, $data = null, $dias = null)
{
    $minutos = max(0, (int) $minutos);
    if ($minutos < 1) return 'agora';
    if ($minutos < 60) return "há $minutos min";
    $dias = $dias !== null ? (int) $dias : intdiv($minutos, 1440);
    if ($dias === 0) return 'há ' . intdiv($minutos, 60) . ' h';
    if ($dias === 1) return 'ontem';
    if ($dias < 7) return "há $dias dias";
    return $data ? date('d/m/Y', strtotime($data)) : "há $dias dias";
}

/** Dados de um item no formato usado pelo sino (HTML e JSON). */
function notificacao_para_array(array $n)
{
    $tipo = TIPOS_NOTIFICACAO[$n['tipo']] ?? ['icone' => 'bi-bell', 'cor' => 'verde'];
    return [
        'id' => (int) $n['id_notificacao'],
        'titulo' => $n['titulo'],
        'mensagem' => $n['mensagem'],
        'icone' => $tipo['icone'],
        'cor' => $tipo['cor'],
        'lida' => (bool) $n['lida'],
        'quando' => notificacao_tempo($n['minutos'], $n['data_criacao'], $n['dias'] ?? null),
        'data' => date('d/m/Y', strtotime($n['data_criacao'])) . ' às ' . date('H:i', strtotime($n['data_criacao'])),
        'url' => BASE_URL . 'pages/notificacoes.php?abrir=' . (int) $n['id_notificacao'],
        'tem_link' => notificacao_link_seguro($n['link'] ?? '') !== null,
        'rotulo_link' => notificacao_rotulo_link($n['link'] ?? ''),
    ];
}

/* ------------------------------------------------------------
 * Eventos dos compartilhamentos
 * ------------------------------------------------------------ */

/** Nome do material e dados do ofertante de uma oferta. */
function notificacao_oferta(PDO $pdo, $id_compartilhamento)
{
    $stmt = $pdo->prepare("SELECT c.id_compartilhamento, c.id_usuario_ofertante, c.tipo_oferta, c.preco, COALESCE(NULLIF(c.nome, ''), r.nome, 'material') AS material, u.nome AS ofertante
                           FROM compartilhamento_residuo c
                           LEFT JOIN residuo r ON r.id_residuo = c.id_residuo
                           INNER JOIN usuario u ON u.id_usuario = c.id_usuario_ofertante
                           WHERE c.id_compartilhamento = :id");
    $stmt->bindValue(':id', (int) $id_compartilhamento, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

/** Nome de um usuário. */
function notificacao_nome_usuario(PDO $pdo, $id_usuario)
{
    $stmt = $pdo->prepare('SELECT nome FROM usuario WHERE id_usuario = :id');
    $stmt->bindValue(':id', (int) $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    return (string) ($stmt->fetchColumn() ?: 'Alguém');
}

/** Interessados de uma oferta com determinados status (antes da mudança). */
function notificacao_interessados(PDO $pdo, $id_compartilhamento, array $status)
{
    $marcadores = implode(',', array_fill(0, count($status), '?'));
    $stmt = $pdo->prepare("SELECT id_interesse, id_usuario, status FROM interesse_compartilhamento WHERE id_compartilhamento = ? AND status IN ($marcadores)");
    $stmt->execute(array_merge([(int) $id_compartilhamento], $status));
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------
 * TEXTOS DAS NOTIFICAÇÕES (um evento por função)
 * Para mudar um texto, basta editar aqui.
 * ------------------------------------------------------------ */

/** Alguém clicou em "Tenho interesse": avisa quem ofereceu. */
function notif_interesse_novo(PDO $pdo, $id_oferta, $id_interessado)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    $nome = notificacao_nome_usuario($pdo, $id_interessado);
    $venda = $o['tipo_oferta'] === 'venda';
    notificar($pdo, $o['id_usuario_ofertante'], 'interesse_novo',
        $venda ? 'Alguém quer comprar o seu material' : 'Alguém se interessou pelo seu material',
        $venda
            ? "$nome quer comprar “{$o['material']}” (anunciado por R$ " . number_format((float) $o['preco'], 2, ',', '.') . "). Converse pelo chat para combinar o pagamento e a retirada, e aceite o interesse quando fecharem negócio."
            : "$nome demonstrou interesse em “{$o['material']}”. Abra seus compartilhamentos para ver o pedido e aceitar quem vai retirar o material.",
        'pages/meus_compartilhamentos.php#oferta-' . (int) $id_oferta);
}

/** O interessado desistiu: avisa quem ofereceu. */
function notif_interesse_cancelado(PDO $pdo, $id_oferta, $id_interessado, $era_aceito)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    $nome = notificacao_nome_usuario($pdo, $id_interessado);
    $mensagem = "$nome cancelou o interesse em “{$o['material']}”.";
    if ($era_aceito) {
        $mensagem .= ' Como era a pessoa que você tinha aceitado, o material voltou a ficar disponível para outros interessados.';
    }
    notificar($pdo, $o['id_usuario_ofertante'], 'interesse_cancelado', 'Um interesse foi cancelado', $mensagem,
        'pages/meus_compartilhamentos.php#oferta-' . (int) $id_oferta);
}

/**
 * Quem ofereceu aceitou um interessado: avisa o aceito e os demais
 * (que serão recusados). Chamar ANTES de atualizar os status.
 */
function notif_interesse_aceito(PDO $pdo, $id_oferta, $id_interesse_aceito)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    foreach (notificacao_interessados($pdo, $id_oferta, ['pendente']) as $i) {
        if ((int) $i['id_interesse'] === (int) $id_interesse_aceito) {
            notificar($pdo, $i['id_usuario'], 'interesse_aceito', 'Seu interesse foi aceito!',
                "{$o['ofertante']} aceitou seu interesse em “{$o['material']}”. O material está reservado para você e o contato para combinar a retirada já está liberado na página do material.",
                'pages/compartilhamento.php?id=' . (int) $id_oferta);
        } else {
            notificar($pdo, $i['id_usuario'], 'interesse_recusado', 'O material foi reservado para outra pessoa',
                "“{$o['material']}” foi reservado para outro interessado e o seu pedido foi encerrado. Mas não desanime: há outros materiais esperando por você.",
                'pages/compartilhamentos.php');
        }
    }
}

/**
 * Material retirado: agradece quem foi aceito e avisa os pendentes.
 * Chamar ANTES de atualizar os status.
 */
function notif_oferta_retirada(PDO $pdo, $id_oferta)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    foreach (notificacao_interessados($pdo, $id_oferta, ['pendente', 'aceito']) as $i) {
        if ($i['status'] === 'aceito') {
            notificar($pdo, $i['id_usuario'], 'oferta_retirada', $o['tipo_oferta'] === 'venda' ? 'Venda concluída' : 'Retirada confirmada',
                $o['tipo_oferta'] === 'venda'
                    ? "{$o['ofertante']} marcou a venda de “{$o['material']}” como concluída. Obrigado por dar uma nova vida a esse material!"
                    : "{$o['ofertante']} confirmou a retirada de “{$o['material']}”. Obrigado por dar uma nova vida a esse material!",
                'pages/compartilhamento.php?id=' . (int) $id_oferta);
        } else {
            notificar($pdo, $i['id_usuario'], 'oferta_indisponivel', 'Um material que você queria já foi retirado',
                "“{$o['material']}” já foi retirado e seu interesse foi encerrado. Veja outros materiais disponíveis nos compartilhamentos.",
                'pages/compartilhamentos.php');
        }
    }
}

/** Quem ofereceu deixou o material indisponível. Chamar ANTES de atualizar. */
function notif_oferta_indisponivel(PDO $pdo, $id_oferta)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    foreach (notificacao_interessados($pdo, $id_oferta, ['pendente', 'aceito']) as $i) {
        notificar($pdo, $i['id_usuario'], 'oferta_indisponivel', 'Um material ficou indisponível',
            "{$o['ofertante']} deixou “{$o['material']}” indisponível e seu interesse foi encerrado. Veja outros materiais compartilhados.",
            'pages/compartilhamentos.php');
    }
}

/** Quem ofereceu excluiu o material. Chamar ANTES do DELETE. */
function notif_oferta_excluida(PDO $pdo, $id_oferta)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    foreach (notificacao_interessados($pdo, $id_oferta, ['pendente', 'aceito']) as $i) {
        notificar($pdo, $i['id_usuario'], 'oferta_excluida', 'Um material que você queria foi removido',
            "{$o['ofertante']} removeu “{$o['material']}” dos compartilhamentos e seu interesse foi encerrado. Veja outros materiais que podem te interessar.",
            'pages/compartilhamentos.php');
    }
}

/** Material editado: avisa quem está interessado (só no site). */
function notif_oferta_editada(PDO $pdo, $id_oferta)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    foreach (notificacao_interessados($pdo, $id_oferta, ['pendente', 'aceito']) as $i) {
        notificar($pdo, $i['id_usuario'], 'oferta_editada', 'Um material que você quer foi atualizado',
            "{$o['ofertante']} atualizou as informações de “{$o['material']}”. Confira se ainda atende ao que você precisa.",
            'pages/compartilhamento.php?id=' . (int) $id_oferta);
    }
}

/** Material publicado: confirmação para quem ofereceu (só no site). */
function notif_oferta_publicada(PDO $pdo, $id_oferta)
{
    $o = notificacao_oferta($pdo, $id_oferta);
    if (!$o) return;
    notificar($pdo, $o['id_usuario_ofertante'], 'oferta_publicada', 'Seu material foi publicado',
        "“{$o['material']}” já aparece nos compartilhamentos. Quando alguém se interessar, você recebe um aviso aqui e no seu e-mail.",
        'pages/compartilhamento.php?id=' . (int) $id_oferta);
}

/** Conta criada (cadastro normal ou com Google). */
function notif_boas_vindas(PDO $pdo, $id_usuario)
{
    notificar($pdo, $id_usuario, 'boas_vindas', 'Bem-vindo(a) ao Rhesys!',
        'Sua conta foi criada com sucesso. Aqui você descobre como descartar cada resíduo do jeito certo, e pode doar ou vender materiais que não usa mais. Sempre que algo acontecer com os seus compartilhamentos, avisaremos por aqui e pelo seu e-mail.',
        'pages/residuos.php');
}

/** Novo conteúdo educativo publicado (todos, só no site). */
function notif_conteudo_novo(PDO $pdo, $id_conteudo, $titulo, $autor = null)
{
    notificar_todos($pdo, 'conteudo_novo', 'Novo conteúdo: ' . $titulo,
        'Acabamos de publicar um novo conteúdo sobre sustentabilidade e descarte correto. Aproveite para aprender algo novo!',
        'pages/conteudos.php?id=' . (int) $id_conteudo, $autor);
}

/** Quiz recebeu a primeira pergunta e já pode ser jogado (todos, só no site). */
function notif_quiz_novo(PDO $pdo, $id_quiz, $titulo, $autor = null)
{
    notificar_todos($pdo, 'quiz_novo', 'Novo quiz: ' . $titulo,
        'Tem quiz novo no Rhesys! Teste seus conhecimentos sobre resíduos e reciclagem.',
        'pages/quiz.php?id=' . (int) $id_quiz, $autor);
}

