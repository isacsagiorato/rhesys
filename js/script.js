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
});
