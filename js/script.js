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
});
