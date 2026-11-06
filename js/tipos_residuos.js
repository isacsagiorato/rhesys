// Painel dinâmico de tipos de resíduos (página pages/residuos.php)
// Ao abrir a página nada é exibido no topo; ao clicar em um card de tipo,
// o painel correspondente aparece com animação e a lista é filtrada na hora,
// sem recarregar a página. Sem JavaScript, os cards continuam funcionando
// como links normais de busca (fallback do lado do servidor).

document.addEventListener('DOMContentLoaded', function () {
    var painel = document.getElementById('tipo-spotlight');
    if (!painel || !window.RHESYS_TIPOS) return;

    var tipos = window.RHESYS_TIPOS;
    var filtrosOrganicos = window.RHESYS_FILTROS_ORGANICOS || null;

    var titulo = document.getElementById('spotlight-titulo');
    var descricao = document.getElementById('spotlight-descricao');
    var foto = document.getElementById('spotlight-foto');
    var iconeWrap = document.getElementById('spotlight-icone');
    var chipsTitulo = document.getElementById('spotlight-filtros-titulo');
    var chipsWrap = document.getElementById('spotlight-filtros');
    var botaoFechar = document.getElementById('spotlight-fechar');

    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-tipo]'));
    var colunas = Array.prototype.slice.call(document.querySelectorAll('#lista-residuos [data-filtro]'));
    var contagem = document.getElementById('contagem-resultados');
    var textoContagemOriginal = contagem ? contagem.textContent.trim() : '';
    var semResultados = document.getElementById('sem-resultados-js');
    var inputBusca = document.querySelector('.search-pill input[name="busca"]');

    var listaOriginal = document.getElementById('lista-residuos');
    var grupoRelacionados = document.getElementById('grupo-relacionados');
    var grupoOutros = document.getElementById('grupo-outros');
    var listaRelacionados = document.getElementById('lista-relacionados');
    var listaOutros = document.getElementById('lista-outros');
    var ordemOriginal = colunas.slice(); // guarda a ordem inicial para restaurar

    var tipoAtivo = null;

    // Minúsculas + sem acentos, para o filtro funcionar como o LIKE do MySQL
    function normalizar(texto) {
        return (texto || '').toString().toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // Divide a lista em "Resíduos Relacionados" (que combinam com o termo)
    // e "Outros Tipos de Resíduos" (os demais), sem esconder nenhum card
    function dividirResultados(termo) {
        var alvo = normalizar(termo);
        var relacionados = 0;
        colunas.forEach(function (coluna) {
            var texto = normalizar(coluna.getAttribute('data-filtro'));
            var combina = alvo === '' || texto.indexOf(alvo) !== -1;
            coluna.classList.remove('d-none');
            if (combina) {
                listaRelacionados.appendChild(coluna);
                relacionados++;
            } else {
                listaOutros.appendChild(coluna);
            }
        });
        listaOriginal.hidden = true;
        grupoRelacionados.hidden = relacionados === 0;
        grupoOutros.hidden = (colunas.length - relacionados) === 0;
        if (contagem) {
            contagem.textContent = relacionados + ' resíduo(s) relacionado(s) a “' + termo + '”.';
        }
        if (semResultados) semResultados.hidden = relacionados !== 0;
        return relacionados;
    }

    // Volta todos os cards para a lista original, na ordem inicial
    function restaurarResultados() {
        ordemOriginal.forEach(function (coluna) {
            listaOriginal.appendChild(coluna);
        });
        listaOriginal.hidden = false;
        grupoRelacionados.hidden = true;
        grupoOutros.hidden = true;
        if (contagem) contagem.textContent = textoContagemOriginal;
        if (semResultados) semResultados.hidden = true;
    }

    // Chips de subfiltros (apenas no painel de orgânicos)
    function montarChips(termo) {
        chipsWrap.innerHTML = '';
        var ehOrganico = normalizar(termo) === normalizar('orgânico');
        if (!ehOrganico || !filtrosOrganicos) {
            chipsWrap.hidden = true;
            chipsTitulo.hidden = true;
            return;
        }
        chipsWrap.hidden = false;
        chipsTitulo.hidden = false;
        Object.keys(filtrosOrganicos).forEach(function (termoChip) {
            var chip = document.createElement('a');
            chip.href = 'residuos.php?busca=' + encodeURIComponent(termoChip) + '#resultados';
            chip.className = 'organic-filter' + (normalizar(termoChip) === normalizar(termo) ? ' active' : '');
            chip.textContent = filtrosOrganicos[termoChip];
            chip.addEventListener('click', function (evento) {
                evento.preventDefault();
                chipsWrap.querySelectorAll('.organic-filter').forEach(function (outro) {
                    outro.classList.remove('active');
                });
                chip.classList.add('active');
                dividirResultados(termoChip);
                if (inputBusca) inputBusca.value = termoChip;
            });
            chipsWrap.appendChild(chip);
        });
    }

    function marcarCardAtivo(termo) {
        cards.forEach(function (card) {
            var ativo = card.getAttribute('data-tipo') === termo;
            var caixa = card.querySelector('.card-tipo');
            if (caixa) caixa.classList.toggle('active', ativo);
        });
    }

    // Mostra o painel do tipo com animação e aplica o filtro na lista
    function selecionarTipo(termo) {
        var tipo = tipos[termo];
        if (!tipo) return;
        tipoAtivo = termo;

        titulo.textContent = tipo.rotulo;
        descricao.textContent = tipo.descricao;

        if (tipo.foto) {
            foto.src = tipo.foto;
            foto.alt = tipo.alt || tipo.rotulo;
            foto.hidden = false;
            iconeWrap.hidden = true;
        } else {
            foto.hidden = true;
            iconeWrap.hidden = false;
            iconeWrap.querySelector('i').className = 'bi ' + tipo.icone;
        }

        montarChips(termo);
        dividirResultados(termo);
        marcarCardAtivo(termo);
        if (inputBusca) inputBusca.value = termo;

        // Reinicia a animação mesmo se outro card já tinha sido clicado
        painel.hidden = false;
        painel.classList.remove('show');
        void painel.offsetWidth; // força reflow para a animação rodar de novo
        painel.classList.add('show');
        painel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Fecha o painel e volta a mostrar todos os resíduos
    function limparTudo() {
        tipoAtivo = null;
        painel.classList.remove('show');
        painel.hidden = true;
        marcarCardAtivo(null);
        restaurarResultados();
        if (inputBusca) inputBusca.value = '';
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function (evento) {
            var termo = card.getAttribute('data-tipo');
            if (!tipos[termo]) return; // segurança: deixa o link seguir normalmente
            evento.preventDefault();
            if (tipoAtivo === termo) {
                limparTudo(); // clicar de novo no mesmo card desfaz o filtro
            } else {
                selecionarTipo(termo);
            }
        });
    });

    if (botaoFechar) botaoFechar.addEventListener('click', limparTudo);
});
