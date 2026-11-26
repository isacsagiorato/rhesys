// Notificações: sino no topo (abas Todas / Não lidas / Lidas, itens que
// expandem ao clicar) e a página de notificações (cartões expansíveis).
(function () {
    var sino = document.querySelector('.notif-sino');
    if (!sino) return;

    var botao = sino.querySelector('.notif-sino-btn');
    var numero = sino.querySelector('.notif-sino-num');
    var icone = botao.querySelector('.bi');
    var lista = sino.querySelector('.notif-painel-lista');
    var marcarTodas = sino.querySelector('.notif-marcar-todas');
    var abas = sino.querySelectorAll('.notif-painel-abas [data-filtro]');
    var api = sino.getAttribute('data-api');
    var acao = sino.getAttribute('data-acao');
    var csrf = sino.getAttribute('data-csrf');
    var filtro = 'todas';
    var aberta = null;      // id da notificação expandida no painel
    var itensAtuais = [];
    var ultimoTotal = numero.hidden ? 0 : (parseInt(numero.textContent, 10) || 0);
    var INTERVALO = 30000;

    function esc(t) {
        return String(t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- contador do sino ---------- */
    function atualizarContador(total) {
        numero.hidden = total === 0;
        numero.textContent = total > 9 ? '9+' : total;
        botao.classList.toggle('tem', total > 0);
        icone.className = 'bi ' + (total > 0 ? 'bi-bell-fill' : 'bi-bell');
        botao.setAttribute('aria-label', 'Notificações' + (total ? ' (' + total + ' não lidas)' : ''));
        if (marcarTodas) marcarTodas.hidden = total === 0;
        sino.querySelectorAll('[data-conta="nao_lidas"]').forEach(function (el) { el.textContent = total; });
        if (total > ultimoTotal) {
            botao.classList.remove('tocar');
            void botao.offsetWidth;
            botao.classList.add('tocar');
        }
        ultimoTotal = total;
    }

    /* ---------- lista do painel ---------- */
    var vazios = {
        todas: ['bi-bell-slash', 'Nenhuma notificação por enquanto.'],
        nao_lidas: ['bi-check2-circle', 'Tudo em dia! Nenhuma notificação não lida.'],
        lidas: ['bi-envelope-open', 'Nenhuma notificação lida ainda.']
    };

    function desenharLista(itens) {
        itensAtuais = itens;
        if (!itens.length) {
            var v = vazios[filtro];
            lista.innerHTML = '<li class="notif-painel-vazio"><i class="bi ' + v[0] + '"></i>' + v[1] + '</li>';
            return;
        }
        lista.innerHTML = itens.map(function (n) {
            var exp = aberta === n.id;
            return '<li class="notif-painel-li' + (exp ? ' expandida' : '') + '" data-id="' + n.id + '">'
                + '<button type="button" class="notif-painel-item' + (n.lida ? '' : ' nao-lida') + '" aria-expanded="' + exp + '">'
                + '<span class="notif-icone cor-' + esc(n.cor) + '"><i class="bi ' + esc(n.icone) + '"></i></span>'
                + '<span class="notif-painel-texto"><strong>' + esc(n.titulo) + '</strong><span>' + esc(n.mensagem) + '</span>'
                + '<small>' + esc(n.quando) + '</small></span>'
                + '<i class="bi bi-chevron-down notif-seta" aria-hidden="true"></i></button>'
                + '<div class="notif-painel-detalhe">'
                + '<p class="notif-data"><i class="bi bi-calendar3"></i>Recebida em ' + esc(n.data) + '</p>'
                + '<div class="notif-painel-acoes">'
                + (n.tem_link ? '<a class="btn btn-success btn-sm" href="' + esc(n.url) + '"><i class="bi bi-box-arrow-up-right me-1"></i>' + esc(n.rotulo_link) + '</a>' : '')
                + '<button type="button" class="btn btn-outline-success btn-sm" data-acao="' + (n.lida ? 'marcar_nao_lida' : 'marcar_lida') + '">'
                + '<i class="bi ' + (n.lida ? 'bi-envelope' : 'bi-envelope-open') + ' me-1"></i>' + (n.lida ? 'Não lida' : 'Lida') + '</button>'
                + '<button type="button" class="btn btn-outline-danger btn-sm notif-btn-excluir" data-acao="excluir" title="Excluir" aria-label="Excluir notificação"><i class="bi bi-trash3"></i></button>'
                + '</div></div></li>';
        }).join('');
    }

    function buscar() {
        return fetch(api + '?filtro=' + filtro, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (dados) {
                if (!dados) return;
                atualizarContador(dados.nao_lidas);
                desenharLista(dados.itens || []);
                if (typeof dados.chat_nao_lidas === 'number') {
                    document.querySelectorAll('.chat-topo-num').forEach(function (n) {
                        n.hidden = !dados.chat_nao_lidas;
                        n.textContent = dados.chat_nao_lidas > 9 ? '9+' : dados.chat_nao_lidas;
                    });
                }
            })
            .catch(function () { /* sem conexão: tenta de novo no próximo ciclo */ });
    }

    function enviar(dadosAcao) {
        var dados = new FormData();
        Object.keys(dadosAcao).forEach(function (k) { dados.append(k, dadosAcao[k]); });
        dados.append('ajax', '1');
        dados.append('csrf_token', csrf);
        return fetch(acao, { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.ok) {
                    atualizarContador(res.nao_lidas);
                    document.dispatchEvent(new CustomEvent('notificacoes:mudou', { detail: res }));
                }
                return res;
            });
    }

    // Dados iniciais já vêm na página (sem esperar a primeira consulta)
    var inicial = sino.querySelector('.notif-dados');
    if (inicial) {
        try { desenharLista(JSON.parse(inicial.textContent).itens || []); } catch (e) { buscar(); }
    }

    // Abas do painel
    abas.forEach(function (aba) {
        aba.addEventListener('click', function () {
            filtro = aba.getAttribute('data-filtro');
            aberta = null;
            abas.forEach(function (a) {
                var ativa = a === aba;
                a.classList.toggle('ativo', ativa);
                a.setAttribute('aria-selected', ativa ? 'true' : 'false');
            });
            lista.classList.add('carregando');
            buscar().then(function () { lista.classList.remove('carregando'); });
        });
    });

    // Clique em um item: expande/recolhe; ao expandir, marca como lida
    lista.addEventListener('click', function (e) {
        var li = e.target.closest('.notif-painel-li');
        if (!li) return;
        var id = parseInt(li.getAttribute('data-id'), 10);
        var botaoAcao = e.target.closest('.notif-painel-acoes [data-acao]');

        if (botaoAcao) {
            var tipo = botaoAcao.getAttribute('data-acao');
            botaoAcao.disabled = true;
            enviar({ acao: tipo, id: id }).then(function (res) {
                if (!res || !res.ok) { botaoAcao.disabled = false; return; }
                if (tipo === 'excluir') {
                    li.classList.add('saindo');
                    setTimeout(function () { aberta = null; buscar(); }, 250);
                } else {
                    buscar();
                }
            }).catch(function () { botaoAcao.disabled = false; });
            return;
        }
        if (!e.target.closest('.notif-painel-item')) return;

        aberta = aberta === id ? null : id;
        lista.querySelectorAll('.notif-painel-li').forEach(function (outro) {
            var exp = parseInt(outro.getAttribute('data-id'), 10) === aberta;
            outro.classList.toggle('expandida', exp);
            outro.querySelector('.notif-painel-item').setAttribute('aria-expanded', exp ? 'true' : 'false');
        });
        var item = itensAtuais.filter(function (n) { return n.id === id; })[0];
        if (aberta === id && item && !item.lida) {
            item.lida = true;
            li.querySelector('.notif-painel-item').classList.remove('nao-lida');
            var b = li.querySelector('[data-acao="marcar_lida"]');
            if (b) { b.setAttribute('data-acao', 'marcar_nao_lida'); b.innerHTML = '<i class="bi bi-envelope me-1"></i>Não lida'; }
            enviar({ acao: 'marcar_lida', id: id }).catch(function () {});
        }
    });

    // Consulta periódica (pausa quando a aba do navegador está escondida)
    var timer = setInterval(function () { if (!document.hidden) buscar(); }, INTERVALO);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) buscar(); });
    sino.addEventListener('show.bs.dropdown', buscar);

    if (marcarTodas) {
        marcarTodas.addEventListener('click', function () {
            marcarTodas.disabled = true;
            enviar({ acao: 'marcar_todas' }).then(buscar).catch(function () {})
                .then(function () { marcarTodas.disabled = false; });
        });
    }
    window.addEventListener('pagehide', function () { clearInterval(timer); });

    /* ---------- página de notificações ---------- */
    var pagina = document.querySelector('.notif-pagina');
    if (!pagina) return;

    function atualizarAbasPagina(res) {
        var mapa = { nao_lidas: res.nao_lidas, lidas: res.lidas, todas: res.nao_lidas + res.lidas };
        pagina.querySelectorAll('.notif-abas [data-filtro]').forEach(function (a) {
            var n = a.querySelector('.comp-aba-num');
            if (n) n.textContent = mapa[a.getAttribute('data-filtro')];
        });
        var resumo = pagina.querySelector('.notif-resumo');
        if (resumo && resumo.firstChild) {
            resumo.firstChild.textContent = (res.nao_lidas === 0 ? 'Você está em dia com tudo.'
                : res.nao_lidas === 1 ? 'Você tem 1 notificação não lida.'
                : 'Você tem ' + res.nao_lidas + ' notificações não lidas.') + ' ';
        }
    }
    document.addEventListener('notificacoes:mudou', function (e) { atualizarAbasPagina(e.detail); });

    function marcarVisual(det, lida) {
        det.classList.toggle('nao-lida', !lida);
        var status = det.querySelector('.notif-status');
        if (status) status.textContent = lida ? 'Lida' : 'Não lida';
        var form = det.querySelector('.notif-form-lida');
        if (form) {
            form.querySelector('[name="acao"]').value = lida ? 'marcar_nao_lida' : 'marcar_lida';
            form.querySelector('button').innerHTML = '<i class="bi ' + (lida ? 'bi-envelope' : 'bi-envelope-open') + ' me-1"></i>'
                + (lida ? 'Marcar como não lida' : 'Marcar como lida');
        }
    }

    // Abrir um cartão: fecha os outros e marca como lido
    pagina.querySelectorAll('details.notif-item').forEach(function (det) {
        det.addEventListener('toggle', function () {
            if (!det.open) return;
            pagina.querySelectorAll('details.notif-item[open]').forEach(function (outro) {
                if (outro !== det) outro.open = false;
            });
            if (det.classList.contains('nao-lida')) {
                marcarVisual(det, true);
                enviar({ acao: 'marcar_lida', id: det.getAttribute('data-id') }).catch(function () {});
            }
        });
    });

    // Botões dentro do cartão funcionam sem recarregar a página
    pagina.addEventListener('submit', function (e) {
        var form = e.target;
        var det = form.closest('details.notif-item');
        if (!det) return;
        e.preventDefault();
        var tipo = form.querySelector('[name="acao"]').value;
        var id = det.getAttribute('data-id');
        enviar({ acao: tipo, id: id }).then(function (res) {
            if (!res || !res.ok) { form.submit(); return; }
            if (tipo === 'excluir') {
                det.classList.add('saindo');
                setTimeout(function () {
                    var lista = det.parentNode;
                    det.remove();
                    if (!lista.children.length) {
                        var titulo = lista.previousElementSibling;
                        if (titulo && titulo.classList.contains('notif-grupo')) titulo.remove();
                        lista.remove();
                    }
                    if (!pagina.querySelector('details.notif-item')) location.reload();
                }, 300);
            } else {
                marcarVisual(det, tipo === 'marcar_lida');
            }
        }).catch(function () { form.submit(); });
    });

    // Vindo do sino com ?ver=ID: rola até o cartão aberto
    var alvo = pagina.querySelector('details.notif-item[open]');
    if (alvo) alvo.scrollIntoView({ block: 'center' });
})();
