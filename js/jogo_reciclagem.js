// Jogo educativo "Jogue no Lixeiro Certo" (Rhesys).
// Cada resíduo deve ir para a lixeira da cor certa (CONAMA 275/2001).
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var ROOT = document.getElementById('jogo-reciclagem');
    if (!ROOT) return;

    var LIXEIRAS = [
        { key: 'papel',    label: 'Papel',     cor: '#2563eb', clara: false },
        { key: 'plastico', label: 'Plástico',  cor: '#dc2626', clara: false },
        { key: 'vidro',    label: 'Vidro',     cor: '#16a34a', clara: false },
        { key: 'metal',    label: 'Metal',     cor: '#f5b301', clara: true  },
        { key: 'organico', label: 'Orgânico',  cor: '#7b4b2a', clara: false },
        { key: 'rejeito',  label: 'Não Reciclável',   cor: '#6b7280', clara: true  }
    ];

    var ITENS = [
        { e: '📰', n: 'Jornal',             b: 'papel' },
        { e: '📦', n: 'Caixa de papelão',   b: 'papel' },
        { e: '📚', n: 'Revista',            b: 'papel' },
        { e: '📄', n: 'Folha de papel',     b: 'papel' },
        { e: '✉️', n: 'Envelope',           b: 'papel' },
        { e: '🥤', n: 'Garrafa PET',        b: 'plastico' },
        { e: '🛍️', n: 'Sacola plástica',    b: 'plastico' },
        { e: '🧴', n: 'Frasco de shampoo',  b: 'plastico' },
        { e: '🥡', n: 'Pote de plástico',   b: 'plastico' },
        { e: '🪥', n: 'Escova de dente',    b: 'plastico' },
        { e: '🍾', n: 'Garrafa de vidro',   b: 'vidro' },
        { e: '🫙', n: 'Pote de vidro',      b: 'vidro' },
        { e: '🍷', n: 'Taça de vidro',      b: 'vidro' },
        { e: '🍶', n: 'Vasilha de vidro',   b: 'vidro' },
        { e: '🥫', n: 'Lata de alumínio',   b: 'metal' },
        { e: '🔩', n: 'Parafuso',           b: 'metal' },
        { e: '🥄', n: 'Colher de metal',    b: 'metal' },
        { e: '🔑', n: 'Chave',              b: 'metal' },
        { e: '🍌', n: 'Casca de banana',    b: 'organico' },
        { e: '🍎', n: 'Resto de maçã',      b: 'organico' },
        { e: '🥕', n: 'Casca de cenoura',   b: 'organico' },
        { e: '🍂', n: 'Folhas secas',       b: 'organico' },
        { e: '☕', n: 'Borra de café',      b: 'organico' },
        { e: '🧻', n: 'Papel higiênico sujo',    b: 'rejeito' },
        { e: '🪞', n: 'Espelho quebrado',   b: 'rejeito' },
        { e: '🚬', n: 'Bituca de cigarro',  b: 'rejeito' },
        { e: '🎈', n: 'Bexiga de festa',    b: 'rejeito' }
    ];

    var TOTAL_RODADAS = 10;
    var PONTOS_BASE = 10;

    var els = {
        hud: document.getElementById('jogo-hud'),
        pontos: document.getElementById('jogo-pontos'),
        rodada: document.getElementById('jogo-rodada'),
        streak: document.getElementById('jogo-streak'),
        telaInicial: document.getElementById('jogo-tela-inicial'),
        telaJogo: document.getElementById('jogo-tela-jogo'),
        telaResultado: document.getElementById('jogo-tela-resultado'),
        itemEmoji: document.getElementById('jogo-item-emoji'),
        itemNome: document.getElementById('jogo-item-nome'),
        lixeiras: document.getElementById('jogo-lixeiras'),
        feedback: document.getElementById('jogo-feedback'),
        proximo: document.getElementById('jogo-proximo'),
        proximoTxt: document.getElementById('jogo-proximo-txt'),
        comecar: document.getElementById('jogo-comecar'),
        recomecar: document.getElementById('jogo-recomecar'),
        resultadoEmoji: document.getElementById('jogo-resultado-emoji'),
        resultadoTitulo: document.getElementById('jogo-resultado-titulo'),
        resultadoTexto: document.getElementById('jogo-resultado-texto'),
        resultadoStats: document.getElementById('jogo-resultado-stats')
    };

    var fila = [];
    var rodada = 0;
    var pontos = 0;
    var acertos = 0;
    var streak = 0;
    var maxStreak = 0;
    var respondido = false;

    function embaralhar(arr) {
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
        }
        return arr;
    }

    function labelDe(key) {
        for (var i = 0; i < LIXEIRAS.length; i++) {
            if (LIXEIRAS[i].key === key) return LIXEIRAS[i].label;
        }
        return '';
    }

    function construirLixeiras() {
        LIXEIRAS.forEach(function (lx) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'jogo-lixeira' + (lx.clara ? ' jogo-lixeira-clara' : '');
            b.setAttribute('data-bin', lx.key);
            b.style.setProperty('--bin', lx.cor);
            b.innerHTML =
                '<span class="jogo-lixeira-lid"></span>' +
                '<span class="jogo-lixeira-corpo">' +
                    '<span class="jogo-lixeira-recicla"><i class="bi bi-recycle" aria-hidden="true"></i></span>' +
                    '<span class="jogo-lixeira-texto">' + lx.label + '</span>' +
                '</span>';
            b.addEventListener('click', function () { arremessar(lx.key, b); });
            els.lixeiras.appendChild(b);
        });
    }
function mostrar(tela) {
        els.telaInicial.classList.toggle('d-none', tela !== 'inicial');
        els.telaJogo.classList.toggle('d-none', tela !== 'jogo');
        els.telaResultado.classList.toggle('d-none', tela !== 'resultado');
        els.hud.classList.toggle('d-none', tela === 'inicial');
    }

    function atualizarHud() {
        els.pontos.textContent = pontos;
        els.rodada.textContent = rodada + '/' + TOTAL_RODADAS;
        els.streak.textContent = streak + ' 🔥';
    }

    function comecar() {
        fila = embaralhar(ITENS.slice()).slice(0, TOTAL_RODADAS);
        pontos = 0; acertos = 0; streak = 0; maxStreak = 0; rodada = 0;
        atualizarHud();
        mostrar('jogo');
        proximaRodada();
    }

    function proximaRodada() {
        if (rodada >= fila.length) { terminar(); return; }
        respondido = false;
        var item = fila[rodada];
        els.itemEmoji.textContent = item.e;
        els.itemNome.textContent = item.n;
        els.feedback.innerHTML = '';
        els.proximo.classList.add('d-none');
        els.proximoTxt.textContent = 'Próximo';

        els.itemEmoji.style.transition = 'none';
        els.itemEmoji.style.transform = '';
        els.itemEmoji.style.opacity = '';
        void els.itemEmoji.offsetWidth;
        els.itemEmoji.style.transition = 'transform .42s cubic-bezier(.2,.75,.3,1), opacity .42s';

        document.querySelectorAll('.jogo-lixeira').forEach(function (b) {
            b.classList.remove('acerto', 'erro');
        });
        atualizarHud();
    }

    function arremessar(binKey, binEl) {
        if (respondido || rodada >= fila.length) return;
        respondido = true;
        var item = fila[rodada];
        rodada++;

        var corpo = binEl.querySelector('.jogo-lixeira-corpo');
        var de = els.itemEmoji.getBoundingClientRect();
        var para = corpo.getBoundingClientRect();
        var dx = para.left - de.left + (para.width - de.width) / 2;
        var dy = para.top - de.top + (para.height - de.height) / 2;
        els.itemEmoji.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(.12)';
        els.itemEmoji.style.opacity = '0';

        var acertou = (binKey === item.b);
        els.proximoTxt.textContent = (rodada >= TOTAL_RODADAS) ? 'Ver resultado' : 'Próximo';

        setTimeout(function () {
            var html = '';
            if (acertou) {
                streak += 1;
                if (streak > maxStreak) maxStreak = streak;
                var bonus = Math.max(0, streak - 1) * 2;
                var ganho = PONTOS_BASE + bonus;
                pontos += ganho;
                acertos += 1;
                binEl.classList.add('acerto');
                html = '<span class="jogo-feedback-ok"><i class="bi bi-check-circle-fill me-2"></i>Correto! '
                    + item.n + ' vai no ' + labelDe(item.b) + ' (+' + ganho + ' pts)</span>';
            } else {
                streak = 0;
                binEl.classList.add('erro');
                var correta = document.querySelector('.jogo-lixeira[data-bin="' + item.b + '"]');
                if (correta) correta.classList.add('acerto');
                html = '<span class="jogo-feedback-erro"><i class="bi bi-x-circle-fill me-2"></i>Ops! '
                    + item.n + ' vai no&nbsp;<strong>' + labelDe(item.b) + '</strong>.</span>';
            }
            els.feedback.innerHTML = html;

            els.itemEmoji.style.transition = 'none';
            els.itemEmoji.style.transform = '';
            els.itemEmoji.style.opacity = '';

            atualizarHud();
            els.proximo.classList.remove('d-none');
        }, 440);
    }

    function terminar() {
        mostrar('resultado');
        var taxa = acertos / TOTAL_RODADAS;
        var emoji, titulo, texto;
        if (taxa === 1) { emoji = '🏆'; titulo = 'Reciclador impecável!'; texto = 'Você acertou tudo — pode dar aula de coleta seletiva.'; }
        else if (taxa >= 0.7) { emoji = '🥇'; titulo = 'Mandou muito bem!'; texto = 'Você domina as cores da reciclagem.'; }
        else if (taxa >= 0.5) { emoji = '😊'; titulo = 'Bom trabalho!'; texto = 'Dá para acertar ainda mais na próxima rodada.'; }
        else if (taxa >= 0.3) { emoji = '😅'; titulo = 'Quase lá!'; texto = 'Revise as cores da CONAMA e tente de novo.'; }
        else { emoji = '💪'; titulo = 'Vamos treinar!'; texto = 'Não desista — cada erro é um aprendizado.'; }

        els.resultadoEmoji.textContent = emoji;
        els.resultadoTitulo.textContent = titulo;
        els.resultadoTexto.textContent = texto;
        els.resultadoStats.innerHTML =
            '<div><div class="jogo-hud-valor">' + pontos + '</div><div class="jogo-hud-label">pontos</div></div>' +
            '<div><div class="jogo-hud-valor">' + acertos + '/' + TOTAL_RODADAS + '</div><div class="jogo-hud-label">acertos</div></div>' +
            '<div><div class="jogo-hud-valor">' + maxStreak + 'x</div><div class="jogo-hud-label">melhor sequência</div></div>';
    }

    construirLixeiras();
    els.comecar.addEventListener('click', comecar);
    els.recomecar.addEventListener('click', comecar);
    els.proximo.addEventListener('click', proximaRodada);
});