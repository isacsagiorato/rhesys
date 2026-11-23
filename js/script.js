// JavaScript próprio do Rhesys (RNF05)
// Efeitos de interface: menu com sombra ao rolar + animações suaves de entrada.

document.addEventListener('DOMContentLoaded', function () {
    // Compacta o menu e adiciona sombra quando a página é rolada
    var nav = document.querySelector('.navbar-rhe');
    if (nav) {
        var aoRolar = function () {
            nav.classList.toggle('nav-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', aoRolar, { passive: true });
        aoRolar();
    }

    // Revela elementos .reveal conforme entram na área visível da tela
    var revelaveis = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && revelaveis.length > 0) {
        var observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visible');
                    observador.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.1 });
        revelaveis.forEach(function (el) { observador.observe(el); });
    } else {
        revelaveis.forEach(function (el) { el.classList.add('visible'); });
    }

    // ============================================================
    // Senhas: mostrar/ocultar + pré-visualização enquanto digita
    // ============================================================
    // Ao digitar, os caracteres ficam visíveis por PEEK_MS e depois
    // voltam a virar bolinhas — dá tempo de conferir o que foi digitado.
    // Se o usuário clicar no olho, a escolha manual prevalece.
    var PEEK_MS = 1500;

    function botaoDaSenha(campo) {
        return document.querySelector('[data-toggle-senha="#' + campo.id + '"]');
    }

    function sincronizarBotao(campo) {
        var botao = botaoDaSenha(campo);
        if (!botao) return;

        var visivel = campo.type === 'text';
        var icone = botao.querySelector('i');
        if (icone) {
            icone.className = visivel ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
        var rotulo = visivel ? 'Ocultar senha' : 'Mostrar senha';
        botao.setAttribute('aria-label', rotulo);
        botao.setAttribute('title', rotulo);
    }

    // Botão do olho: trava a senha aberta (text) ou fechada (password)
    document.querySelectorAll('[data-toggle-senha]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            var campo = document.querySelector(botao.getAttribute('data-toggle-senha'));
            if (!campo) return;

            var aberta = campo.getAttribute('data-trava') === 'aberta';
            campo.setAttribute('data-trava', aberta ? 'fechada' : 'aberta');
            campo.type = aberta ? 'password' : 'text';

            if (campo.__peekTimer) {
                clearTimeout(campo.__peekTimer);
                campo.__peekTimer = null;
            }
            sincronizarBotao(campo);
        });
    });

    // Pré-visualização ao digitar (funciona em qualquer campo password,
    // cobrindo login e cadastro automaticamente)
    document.querySelectorAll('input[type="password"]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            // Quem usou o olho assume o controle: não interfere mais
            if (campo.getAttribute('data-trava')) return;

            campo.type = 'text';
            sincronizarBotao(campo);

            if (campo.__peekTimer) clearTimeout(campo.__peekTimer);
            campo.__peekTimer = setTimeout(function () {
                campo.__peekTimer = null;
                if (campo.getAttribute('data-trava')) return;
                campo.type = 'password';
                sincronizarBotao(campo);
            }, PEEK_MS);
        });
    });

    // ============================================================
    // Cards das cores CONAMA (página inicial): abrem um modal com
    // uma breve explicação sobre o material ao serem clicados.
    // ============================================================
    var modalConama = document.getElementById('modalConama');

    function preencherModalConama(card) {
        if (!modalConama) return;

        var titulo = modalConama.querySelector('#modalConamaTitulo');
        var corNome = modalConama.querySelector('#modalConamaCorNome');
        var texto = modalConama.querySelector('#modalConamaTexto');
        var faixa = modalConama.querySelector('#modalConamaCor');

        if (titulo) titulo.textContent = card.getAttribute('data-material');
        if (corNome) corNome.textContent = 'Lixeira ' + card.getAttribute('data-nome');
        if (texto) texto.textContent = card.getAttribute('data-explicacao');
        if (faixa) faixa.style.background = card.getAttribute('data-hex');
    }

    document.querySelectorAll('.conama-card').forEach(function (card) {
        card.addEventListener('click', function () {
            // Preenche o conteúdo antes do Bootstrap abrir o modal,
            // garantindo que sempre mostre o cartão clicado.
            preencherModalConama(card);
        });

        // Acessibilidade: Enter/Espaço também abrem o modal
        card.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter' || evento.key === ' ') {
                evento.preventDefault();
                card.click();
            }
        });
    });
});

// Números que sobem de 0 até o valor quando aparecem na tela (ex.: topo dos compartilhamentos)
document.addEventListener('DOMContentLoaded', function () {
    var numeros = document.querySelectorAll('[data-contar]');
    if (!numeros.length) return;
    var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function animar(el) {
        var alvo = parseInt(el.getAttribute('data-contar'), 10) || 0;
        if (reduzir || alvo === 0) { el.textContent = alvo; return; }
        var inicio = null, duracao = 1200;
        function passo(t) {
            if (inicio === null) inicio = t;
            var p = Math.min((t - inicio) / duracao, 1);
            el.textContent = Math.round(alvo * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(passo);
        }
        el.textContent = '0';
        requestAnimationFrame(passo);
    }

    if ('IntersectionObserver' in window) {
        var obs = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (e.isIntersecting) { animar(e.target); obs.unobserve(e.target); }
            });
        }, { threshold: 0.4 });
        numeros.forEach(function (el) { obs.observe(el); });
    } else {
        numeros.forEach(animar);
    }
});
