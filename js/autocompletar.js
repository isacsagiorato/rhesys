// Busca automática (autocompletar) dos resíduos.
// - Sugestões a partir da 1ª letra, tolerantes a erros (servidor: pages/sugestoes.php);
// - destaca no nome a parte que a pessoa digitou (sem ligar para acentos);
// - atalho para o tipo de material, "Você quis dizer…" clicável e "Pesquisar por…";
// - campo vazio: mostra buscas recentes (guardadas no navegador) e populares;
// - teclado: ↑/↓ escolhem, Enter abre, Esc fecha. Sem JavaScript, a busca normal continua funcionando.
document.addEventListener('DOMContentLoaded', function () {
    var POPULARES = ['pilha', 'óleo de cozinha', 'remédio vencido', 'lâmpada', 'celular', 'isopor', 'pneu', 'caixa de pizza'];
    var CHAVE_RECENTES = 'rhesys_buscas_recentes';
    var cache = {};

    function semAcento(t) {
        return (t || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    function lerRecentes() {
        try { return JSON.parse(localStorage.getItem(CHAVE_RECENTES) || '[]').slice(0, 5); } catch (e) { return []; }
    }
    function guardarRecente(texto) {
        texto = (texto || '').trim();
        if (texto.length < 2) return;
        try {
            var lista = lerRecentes().filter(function (t) { return semAcento(t) !== semAcento(texto); });
            lista.unshift(texto);
            localStorage.setItem(CHAVE_RECENTES, JSON.stringify(lista.slice(0, 5)));
        } catch (e) { /* navegador sem armazenamento: segue sem recentes */ }
    }
    function el(tag, classe, texto) {
        var e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto !== undefined) e.textContent = texto;
        return e;
    }

    // Monta o nome com as partes digitadas em negrito (início de palavra, sem acentos)
    function nomeDestacado(nome, texto) {
        var alvo = semAcento(nome);
        var termos = semAcento(texto).split(/[^a-z0-9]+/).filter(function (t) { return t.length >= 1; });
        var marcar = new Array(nome.length).fill(false);
        termos.forEach(function (t) {
            var re = new RegExp('(^|[^a-z0-9])(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'g'), m;
            while ((m = re.exec(alvo)) !== null) {
                var ini = m.index + m[1].length;
                for (var k = ini; k < ini + t.length && k < marcar.length; k++) marcar[k] = true;
                if (re.lastIndex === m.index) re.lastIndex++;
            }
        });
        var frag = document.createDocumentFragment(), buf = '', emMarca = false;
        function solta() {
            if (!buf) return;
            frag.appendChild(emMarca ? el('mark', '', buf) : document.createTextNode(buf));
            buf = '';
        }
        for (var i = 0; i < nome.length; i++) {
            if (marcar[i] !== emMarca) { solta(); emMarca = marcar[i]; }
            buf += nome[i];
        }
        solta();
        return frag;
    }

    document.querySelectorAll('input[data-autocompletar]').forEach(function (input, n) {
        var url = input.getAttribute('data-autocompletar');
        var form = input.closest('form');
        var caixa = input.closest('.search-pill') || input.parentNode;
        var idLista = 'sugestoes-' + n;
        var lista = el('div', 'autocompletar');
        lista.id = idLista;
        lista.setAttribute('role', 'listbox');
        lista.setAttribute('aria-label', 'Sugestões de resíduos');
        lista.hidden = true;
        var anuncio = el('div', 'visually-hidden');
        anuncio.setAttribute('aria-live', 'polite');
        caixa.classList.add('tem-autocompletar');
        caixa.appendChild(lista);
        caixa.appendChild(anuncio);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', idLista);
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('autocomplete', 'off');

        var timer = null, controle = null, ativo = -1, opcoes = [];

        function abrir() { lista.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function fechar() {
            lista.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            ativo = -1;
        }
        function marcar(i) {
            opcoes.forEach(function (o, k) {
                o.el.classList.toggle('ativo', k === i);
                o.el.setAttribute('aria-selected', k === i ? 'true' : 'false');
            });
            ativo = i;
            if (i >= 0 && opcoes[i]) {
                input.setAttribute('aria-activedescendant', opcoes[i].el.id);
                opcoes[i].el.scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }
        // Cria uma linha clicável/navegável
        function opcao(conteudo, classe, acao) {
            var a = el(acao.href ? 'a' : 'button', 'autocompletar-item ' + (classe || ''));
            if (acao.href) a.href = acao.href; else a.type = 'button';
            a.id = idLista + '-op' + opcoes.length;
            a.setAttribute('role', 'option');
            a.setAttribute('aria-selected', 'false');
            a.tabIndex = -1;
            conteudo.forEach(function (c) { a.appendChild(c); });
            var indice = opcoes.length;
            a.addEventListener('mousemove', function () { if (ativo !== indice) marcar(indice); });
            a.addEventListener('mousedown', function (e) { e.preventDefault(); });   // não tira o foco do campo
            a.addEventListener('click', function (e) {
                if (acao.fazer) { e.preventDefault(); acao.fazer(); }
                else guardarRecente(input.value);
            });
            opcoes.push({ el: a, acao: acao });
            lista.appendChild(a);
            return a;
        }
        function titulo(texto) { lista.appendChild(el('div', 'autocompletar-titulo', texto)); }
        function usarTexto(texto) {
            input.value = texto;
            input.focus();
            buscar();
        }

        // Campo vazio: recentes e populares
        function mostrarInicio() {
            lista.innerHTML = ''; opcoes = []; ativo = -1;
            var recentes = lerRecentes();
            if (recentes.length) {
                titulo('Suas buscas recentes');
                recentes.forEach(function (t) {
                    opcao([el('i', 'bi bi-clock-history autocompletar-icone'), el('span', 'autocompletar-nome', t)], 'compacta', { fazer: function () { usarTexto(t); } });
                });
            }
            titulo('Mais buscados');
            var chips = el('div', 'autocompletar-chips');
            POPULARES.forEach(function (t) {
                var b = el('button', 'autocompletar-chip', t);
                b.type = 'button';
                b.addEventListener('mousedown', function (e) { e.preventDefault(); });
                b.addEventListener('click', function () { usarTexto(t); });
                chips.appendChild(b);
            });
            lista.appendChild(chips);
            abrir();
        }

        function mostrar(dados, texto) {
            lista.innerHTML = ''; opcoes = []; ativo = -1;
            if (dados.corrigida) {
                var aviso = el('button', 'autocompletar-aviso');
                aviso.type = 'button';
                aviso.appendChild(el('i', 'bi bi-magic'));
                aviso.appendChild(document.createTextNode(' Você quis dizer '));
                aviso.appendChild(el('strong', '', dados.corrigida));
                aviso.appendChild(document.createTextNode('?'));
                aviso.addEventListener('mousedown', function (e) { e.preventDefault(); });
                aviso.addEventListener('click', function () { usarTexto(dados.corrigida); });
                lista.appendChild(aviso);
            }
            if (dados.tipo) {
                opcao([
                    (function () { var c = el('span', 'autocompletar-tipo-icone'); c.appendChild(el('i', 'bi ' + dados.tipo.icone)); return c; })(),
                    el('span', 'autocompletar-nome', 'Ver todos: ' + dados.tipo.rotulo),
                    el('span', 'autocompletar-qtd', dados.tipo.total + ' itens')
                ], 'destaque', { href: dados.tipo.url });
            }
            (dados.itens || []).forEach(function (item) {
                var img = el('img'); img.src = item.foto; img.alt = ''; img.loading = 'lazy';
                var textos = el('span', 'autocompletar-textos');
                var nome = el('span', 'autocompletar-nome');
                nome.appendChild(nomeDestacado(item.nome, texto));
                textos.appendChild(nome);
                if (item.tipo) {
                    var meta = el('span', 'autocompletar-meta');
                    meta.appendChild(el('i', 'bi ' + item.tipo_icone));
                    meta.appendChild(document.createTextNode(' ' + item.tipo));
                    textos.appendChild(meta);
                }
                opcao([img, textos, el('span', 'badge-classe badge-classe-sm ' + item.classe_css, item.classe)], '', { href: item.url });
            });
            if (!dados.itens || !dados.itens.length) {
                lista.appendChild(el('div', 'autocompletar-vazio', 'Nenhum resíduo encontrado para “' + texto + '”. Tente o nome do material, como “plástico” ou “metal”.'));
            }
            if (dados.busca_url) {
                var rotulo = dados.total > (dados.itens || []).length ? 'Ver todos os ' + dados.total + ' resultados para ' : 'Pesquisar ';
                var linha = el('span', 'autocompletar-nome');
                linha.appendChild(document.createTextNode(rotulo));
                linha.appendChild(el('strong', '', '“' + texto.trim() + '”'));
                opcao([el('i', 'bi bi-search autocompletar-icone'), linha], 'compacta rodape', { href: dados.busca_url });
            }
            anuncio.textContent = (dados.itens || []).length ? dados.itens.length + ' sugestões. Use as setas para escolher.' : 'Nenhuma sugestão.';
            abrir();
        }

        function buscar() {
            var texto = input.value;
            if (!texto.trim()) { mostrarInicio(); return; }
            var chave = texto.toLowerCase();
            if (cache[chave]) { mostrar(cache[chave], texto); return; }
            if (controle) controle.abort();
            controle = 'AbortController' in window ? new AbortController() : null;
            fetch(url + '?q=' + encodeURIComponent(texto), controle ? { signal: controle.signal } : {})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (dados) {
                    if (!dados) return;
                    cache[chave] = dados;
                    if (input.value === texto && document.activeElement === input) mostrar(dados, texto);
                })
                .catch(function () {});
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(buscar, 120);
        });
        input.addEventListener('focus', buscar);
        input.addEventListener('keydown', function (e) {
            if (lista.hidden) {
                if (e.key === 'ArrowDown') { e.preventDefault(); buscar(); }
                return;
            }
            var total = opcoes.length;
            if (e.key === 'ArrowDown' && total) { e.preventDefault(); marcar((ativo + 1) % total); }
            else if (e.key === 'ArrowUp' && total) { e.preventDefault(); marcar(ativo <= 0 ? total - 1 : ativo - 1); }
            else if (e.key === 'Enter' && ativo >= 0) {
                e.preventDefault();
                var escolhida = opcoes[ativo].acao;
                if (escolhida.fazer) { escolhida.fazer(); } else { guardarRecente(input.value); window.location.href = escolhida.href; }
            }
            else if (e.key === 'Escape') { e.preventDefault(); fechar(); }
            else if (e.key === 'Tab') { fechar(); }
        });
        if (form) form.addEventListener('submit', function () { guardarRecente(input.value); });
        document.addEventListener('click', function (e) { if (!caixa.contains(e.target)) fechar(); });
    });
});
