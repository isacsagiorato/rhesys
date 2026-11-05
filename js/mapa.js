// Mapa dos pontos de coleta com Leaflet + OpenStreetMap (Sprint 4).
document.addEventListener('DOMContentLoaded', function () {
    var elemento = document.getElementById('mapa-pontos');
    var dados = document.getElementById('dados-pontos');
    if (!elemento || !dados) return;

    if (typeof L === 'undefined') {
        elemento.textContent = 'O mapa não pôde ser carregado. Os endereços e links para o OpenStreetMap continuam disponíveis nos cartões abaixo.';
        return;
    }

    var pontos;
    try {
        pontos = JSON.parse(dados.textContent);
    } catch (erro) {
        elemento.textContent = 'Não foi possível interpretar os dados do mapa.';
        return;
    }
    if (!Array.isArray(pontos) || pontos.length === 0) return;

    var mapa = L.map(elemento, { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>'
    }).addTo(mapa);

    var limites = [];
    pontos.forEach(function (ponto) {
        var posicao = [Number(ponto.latitude), Number(ponto.longitude)];
        if (!Number.isFinite(posicao[0]) || !Number.isFinite(posicao[1])) return;

        var marcador = L.marker(posicao).addTo(mapa);
        var popup = document.createElement('div');
        var titulo = document.createElement('strong');
        titulo.textContent = ponto.nome;
        popup.appendChild(titulo);

        if (ponto.endereco) {
            var endereco = document.createElement('p');
            endereco.className = 'small mb-2 mt-1';
            endereco.textContent = ponto.endereco;
            popup.appendChild(endereco);
        }

        var link = document.createElement('a');
        link.href = 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(posicao[0])
            + '&mlon=' + encodeURIComponent(posicao[1]) + '#map=18/' + encodeURIComponent(posicao[0])
            + '/' + encodeURIComponent(posicao[1]);
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = 'Abrir no OpenStreetMap';
        popup.appendChild(link);
        marcador.bindPopup(popup);
        limites.push(posicao);
    });

    if (limites.length === 1) {
        mapa.setView(limites[0], 16);
    } else if (limites.length > 1) {
        mapa.fitBounds(limites, { padding: [35, 35], maxZoom: 16 });
    } else {
        elemento.textContent = 'Nenhum ponto com coordenadas válidas.';
    }
});
