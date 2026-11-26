<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../config/seguranca.php';
require_once __DIR__ . '/../config/compartilhamento.php';
require_once __DIR__ . '/../config/chat.php';
exige_login();

$id_usuario = (int) $_SESSION['id_usuario'];
$id_ativa = (int) ($_GET['id'] ?? 0);
$erro = '';

// Envio sem JavaScript (o js/chat.js envia pela API, sem recarregar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ativa = (int) ($_POST['id'] ?? 0);
    $c = chat_conversa($pdo, $id_ativa, $id_usuario);
    if (!verificar_csrf()) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } elseif (!$c) {
        $erro = 'Conversa não encontrada.';
    } else {
        [, $erro] = chat_enviar($pdo, $c, $id_usuario, $_POST['texto'] ?? '', $_FILES['foto'] ?? null);
        if (!$erro) {
            header('Location: conversas.php?id=' . $id_ativa . '#fim');
            exit;
        }
    }
}

$conversa = $id_ativa ? chat_conversa($pdo, $id_ativa, $id_usuario) : null;
if ($id_ativa && !$conversa) $erro = $erro ?: 'Essa conversa não existe ou você não participa dela.';
if ($conversa) chat_marcar_lidas($pdo, $conversa['id_interesse'], $id_usuario);

$conversas = array_map(fn($c) => chat_conversa_para_array($c, $id_usuario), chat_conversas_usuario($pdo, $id_usuario));
$mensagens = $conversa ? array_map(fn($m) => chat_mensagem_para_array($m, $id_usuario), chat_mensagens($pdo, $conversa['id_interesse'])) : [];
$ultimo_id = $mensagens ? end($mensagens)['id'] : 0;

$sugestoes = !$conversa ? [] : (($conversa['tipo_oferta'] ?? 'doacao') === 'venda'
    ? ($conversa['sou_ofertante']
        ? ['Olá! Ainda está à venda, sim.', 'Aceito Pix ou dinheiro na retirada.', 'Quando você pode buscar?']
        : ['Olá! Ainda está à venda?', 'Aceita ' . formatar_preco(chat_contraproposta($conversa['preco'])) . '?', 'Posso pagar com Pix na retirada?'])
    : ($conversa['sou_ofertante']
        ? ['Olá! O material ainda está disponível.', 'Quando você pode retirar?', 'A retirada é no endereço combinado.']
        : ['Olá! O material ainda está disponível?', 'Qual o melhor horário para retirar?', 'Posso buscar ainda esta semana?']));

/** Sugestão de contraproposta: uns 15% a menos, arredondado (ex.: 1.250 -> 1.060). */
function chat_contraproposta($preco)
{
    $v = (float) $preco * 0.85;
    $passo = $v >= 100 ? 10 : ($v >= 20 ? 5 : 1);
    return max(1, floor($v / $passo) * $passo);
}

$titulo_pagina = $conversa ? 'Conversa com ' . $conversa['nome_outro'] : 'Conversas';
require __DIR__ . '/../includes/header.php';
?>
<section class="chat-app <?php echo $conversa ? 'tem-ativa' : ''; ?>"
         data-api="<?php echo BASE_URL; ?>pages/chat_api.php"
         data-csrf="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"
         data-ativa="<?php echo $conversa ? (int) $conversa['id_interesse'] : 0; ?>"
         data-ultimo="<?php echo (int) $ultimo_id; ?>">

    <aside class="chat-lista" aria-label="Suas conversas">
        <div class="chat-lista-topo">
            <h1 class="h4 mb-0"><i class="bi bi-chat-dots me-2"></i>Conversas</h1>
            <a href="compartilhamentos.php" class="chat-lista-link" title="Ver materiais"><i class="bi bi-arrow-left-right"></i></a>
        </div>
        <?php if (count($conversas) > 4): ?>
        <div class="chat-filtro">
            <i class="bi bi-search"></i>
            <input type="search" placeholder="Buscar pessoa ou material" aria-label="Buscar conversa" class="chat-filtro-campo">
        </div>
        <?php endif; ?>
        <ul class="chat-itens">
            <?php if (!$conversas): ?>
                <li class="chat-lista-vazia">
                    <i class="bi bi-chat-square-heart"></i>
                    <strong>Nenhuma conversa ainda</strong>
                    <span>As conversas começam quando alguém se interessa por um material seu, ou quando você se interessa por um material de outra pessoa.</span>
                    <a href="compartilhamentos.php" class="btn btn-success btn-sm mt-2">Ver materiais</a>
                </li>
            <?php endif; ?>
            <?php foreach ($conversas as $c): ?>
            <li>
                <a class="chat-item <?php echo $conversa && $c['id'] === (int) $conversa['id_interesse'] ? 'ativa' : ''; ?> <?php echo $c['nao_lidas'] ? 'tem-novas' : ''; ?>"
                   href="conversas.php?id=<?php echo $c['id']; ?>" data-id="<?php echo $c['id']; ?>"
                   data-busca="<?php echo htmlspecialchars(mb_strtolower($c['nome'] . ' ' . $c['material'])); ?>">
                    <span class="chat-avatar"><?php echo htmlspecialchars($c['inicial']); ?></span>
                    <span class="chat-item-texto">
                        <span class="chat-item-linha"><strong><?php echo htmlspecialchars($c['nome']); ?></strong><small><?php echo htmlspecialchars($c['quando']); ?></small></span>
                        <span class="chat-item-material"><i class="bi bi-box-seam"></i><?php echo htmlspecialchars($c['material']); ?></span>
                        <span class="chat-item-linha"><span class="chat-item-previa"><?php echo htmlspecialchars($c['previa']); ?></span>
                            <?php if ($c['nao_lidas']): ?><span class="chat-badge"><?php echo $c['nao_lidas']; ?></span><?php elseif ($c['encerrada']): ?><span class="chat-tag-encerrada">Encerrada</span><?php endif; ?>
                        </span>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <div class="chat-janela">
        <?php if (!$conversa): ?>
            <div class="chat-nenhuma">
                <div class="chat-nenhuma-arte" aria-hidden="true"><i class="bi bi-chat-heart"></i></div>
                <h2 class="h4">Converse para combinar a retirada</h2>
                <p class="text-muted"><?php echo $erro ? htmlspecialchars($erro) : 'Escolha uma conversa ao lado. Aqui quem oferece e quem tem interesse tiram dúvidas e combinam o dia e o horário da retirada, sem precisar trocar telefone.'; ?></p>
            </div>
        <?php else: ?>
            <header class="chat-cabecalho">
                <a href="conversas.php" class="chat-voltar" aria-label="Voltar para as conversas"><i class="bi bi-arrow-left"></i></a>
                <span class="chat-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr(trim($conversa['nome_outro']), 0, 1))); ?></span>
                <div class="chat-cabecalho-texto">
                    <strong><?php echo htmlspecialchars($conversa['nome_outro']); ?></strong>
                    <span><?php echo $conversa['sou_ofertante'] ? 'Interessado(a) em' : 'Ofertante de'; ?> <a href="compartilhamento.php?id=<?php echo (int) $conversa['id_compartilhamento']; ?>"><?php echo htmlspecialchars($conversa['material']); ?></a>
                        <span class="chat-preco <?php echo ($conversa['tipo_oferta'] ?? 'doacao') === 'venda' ? 'venda' : 'doacao'; ?>"><?php echo htmlspecialchars(rotulo_preco_oferta($conversa)); ?><?php echo ($conversa['tipo_oferta'] ?? '') === 'venda' && $conversa['preco_negociavel'] ? ' · negociável' : ''; ?></span></span>
                </div>
                <a class="chat-material" href="compartilhamento.php?id=<?php echo (int) $conversa['id_compartilhamento']; ?>" title="Ver o material">
                    <img src="<?php echo imagem_material($conversa['imagem']); ?>" alt="<?php echo htmlspecialchars($conversa['material']); ?>">
                </a>
            </header>

            <div class="chat-faixa <?php echo $conversa['pode_enviar'] ? '' : 'encerrada'; ?>">
                <i class="bi <?php echo $conversa['pode_enviar'] ? 'bi-info-circle' : 'bi-lock'; ?>"></i>
                <span class="chat-faixa-texto"><?php echo htmlspecialchars($conversa['aviso']); ?></span>
                <?php if ($conversa['sou_ofertante'] && $conversa['status_interesse'] === 'pendente' && $conversa['status_oferta'] === 'disponivel'): ?>
                    <form method="post" action="meus_compartilhamentos.php" class="ms-auto" onsubmit="return confirm('Aceitar o interesse de <?php echo htmlspecialchars(addslashes($conversa['nome_outro']), ENT_QUOTES); ?>? O material fica reservado para essa pessoa.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="acao" value="aceitar_interesse">
                        <input type="hidden" name="id_compartilhamento" value="<?php echo (int) $conversa['id_compartilhamento']; ?>">
                        <input type="hidden" name="id_interesse" value="<?php echo (int) $conversa['id_interesse']; ?>">
                        <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Aceitar interesse</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="chat-mensagens" role="log" aria-live="polite" aria-label="Mensagens">
                <div class="chat-vazia" <?php echo $mensagens ? 'hidden' : ''; ?>>
                    <i class="bi bi-chat-text"></i>
                    <p>Nenhuma mensagem ainda. Que tal começar?</p>
                </div>
                <?php $dia_atual = null; foreach ($mensagens as $m): ?>
                    <?php if ($m['dia'] !== $dia_atual): $dia_atual = $m['dia']; ?><div class="chat-dia"><span><?php echo $dia_atual; ?></span></div><?php endif; ?>
                    <div class="chat-bolha <?php echo $m['minha'] ? 'minha' : 'outra'; ?><?php echo $m['imagem'] ? ' com-foto' : ''; ?><?php echo $m['apagada'] ? ' apagada' : ''; ?>" data-id="<?php echo $m['id']; ?>">
                        <?php if ($m['apagada']): ?>
                            <p class="chat-apagada-texto"><i class="bi bi-slash-circle"></i><?php echo $m['minha'] ? 'Você apagou esta mensagem' : 'Esta mensagem foi apagada'; ?></p>
                        <?php else: ?>
                            <?php if ($m['minha']): ?><button type="button" class="chat-bolha-menu" aria-label="Opções da mensagem" title="Opções"><i class="bi bi-chevron-down"></i></button><?php endif; ?>
                            <?php if ($m['imagem']): ?><a class="chat-foto" href="<?php echo htmlspecialchars($m['imagem']); ?>" target="_blank" rel="noopener"><img src="<?php echo htmlspecialchars($m['imagem']); ?>" alt="Foto enviada na conversa" loading="lazy"></a><?php endif; ?>
                            <?php if ($m['texto'] !== ''): ?><p><?php echo nl2br(htmlspecialchars($m['texto'])); ?></p><?php endif; ?>
                        <?php endif; ?>
                        <span class="chat-hora"><?php echo $m['hora']; ?><?php if ($m['minha'] && !$m['apagada']): ?><i class="bi <?php echo $m['lida'] ? 'bi-check2-all lida' : 'bi-check2'; ?>" title="<?php echo $m['lida'] ? 'Lida' : 'Enviada'; ?>"></i><?php endif; ?></span>
                    </div>
                <?php endforeach; ?>
                <span id="fim"></span>
            </div>

            <?php if ($conversa['pode_enviar']): ?>
                <?php if (!$mensagens): ?>
                <div class="chat-sugestoes" aria-label="Sugestões de mensagem">
                    <?php foreach ($sugestoes as $s): ?><button type="button" class="chat-sugestao"><?php echo htmlspecialchars($s); ?></button><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="chat-previa" hidden>
                    <div class="chat-previa-foto"><img alt="Prévia da foto escolhida"><button type="button" class="chat-previa-remover" aria-label="Remover foto"><i class="bi bi-x-lg"></i></button></div>
                    <span class="chat-previa-texto">Foto pronta para enviar. Se quiser, escreva uma legenda.</span>
                </div>
                <form class="chat-envio" method="post" enctype="multipart/form-data" action="conversas.php?id=<?php echo (int) $conversa['id_interesse']; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $conversa['id_interesse']; ?>">
                    <div class="dropup chat-anexo">
                        <button type="button" class="chat-anexo-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Enviar foto" title="Enviar foto"><i class="bi bi-paperclip"></i></button>
                        <ul class="dropdown-menu chat-anexo-menu">
                            <li><label class="dropdown-item" for="chat-galeria"><i class="bi bi-images"></i>Galeria<small>Escolher uma foto salva</small></label></li>
                            <li><button type="button" class="dropdown-item chat-abrir-camera"><i class="bi bi-camera"></i>Câmera<small class="chat-camera-status">Tirar uma foto agora</small></button></li>
                        </ul>
                        <input type="file" id="chat-galeria" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" class="visually-hidden" tabindex="-1">
                        <input type="file" id="chat-camera-celular" accept="image/*" capture="environment" class="visually-hidden" tabindex="-1">
                    </div>
                    <label for="chat-texto" class="visually-hidden">Mensagem</label>
                    <textarea id="chat-texto" name="texto" rows="1" maxlength="<?php echo CHAT_TAMANHO_MAXIMO; ?>" placeholder="Escreva uma mensagem…"></textarea>
                    <button type="submit" class="chat-enviar" aria-label="Enviar mensagem"><i class="bi bi-send-fill"></i></button>
                </form>
                <p class="chat-erro" role="alert" <?php echo $erro ? '' : 'hidden'; ?>><?php echo htmlspecialchars($erro); ?></p>
            <?php else: ?>
                <div class="chat-encerrado"><i class="bi bi-lock"></i>Esta conversa está encerrada. Você ainda pode ler o histórico.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="chat-camera" hidden role="dialog" aria-modal="true" aria-label="Tirar foto com a câmera">
        <div class="chat-camera-caixa">
            <div class="chat-camera-topo"><strong><i class="bi bi-camera me-2"></i>Tirar foto</strong><button type="button" class="chat-camera-fechar" aria-label="Fechar câmera"><i class="bi bi-x-lg"></i></button></div>
            <div class="chat-camera-tela">
                <video playsinline muted></video>
                <canvas hidden></canvas>
                <p class="chat-camera-msg" hidden></p>
            </div>
            <div class="chat-camera-acoes">
                <button type="button" class="btn btn-outline-light chat-camera-trocar" hidden title="Trocar de câmera"><i class="bi bi-arrow-repeat"></i></button>
                <button type="button" class="chat-camera-disparar" aria-label="Tirar foto"><span></span></button>
                <button type="button" class="btn btn-light chat-camera-usar" hidden><i class="bi bi-check2 me-1"></i>Usar foto</button>
                <button type="button" class="btn btn-outline-light chat-camera-refazer" hidden><i class="bi bi-arrow-counterclockwise me-1"></i>Tirar outra</button>
            </div>
        </div>
    </div>

    <div class="chat-confirmar" hidden role="alertdialog" aria-modal="true" aria-labelledby="chat-confirmar-titulo" aria-describedby="chat-confirmar-texto">
        <div class="chat-confirmar-caixa">
            <div class="chat-confirmar-icone" aria-hidden="true"><i class="bi bi-trash3"></i></div>
            <h2 id="chat-confirmar-titulo" class="h5">Apagar mensagem?</h2>
            <p id="chat-confirmar-texto">A mensagem será apagada para você e para <?php echo $conversa ? htmlspecialchars(strtok(trim($conversa['nome_outro']), ' ')) : 'a outra pessoa'; ?>. Isso não pode ser desfeito.</p>
            <div class="chat-confirmar-acoes">
                <button type="button" class="btn btn-outline-secondary chat-confirmar-nao">Cancelar</button>
                <button type="button" class="btn btn-danger chat-confirmar-sim"><i class="bi bi-trash3 me-1"></i>Apagar para todos</button>
            </div>
        </div>
    </div>

    <div class="chat-visualizador" hidden role="dialog" aria-modal="true" aria-label="Foto ampliada">
        <img alt="Foto ampliada">
        <div class="chat-visualizador-acoes">
            <a class="chat-visualizador-baixar" href="#" title="Baixar foto" aria-label="Baixar foto"><i class="bi bi-download"></i></a>
            <button type="button" class="chat-visualizador-fechar" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</section>
<?php $scripts_extras = [BASE_URL . 'js/chat.js?v=3']; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
