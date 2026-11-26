// Chat dos compartilhamentos: mensagens em tempo (quase) real, envio sem
// recarregar, "visto", sugestões, fotos da galeria ou da câmera
// (webcam no computador / câmera do celular) e visualizador de fotos.
(function () {
    var app = document.querySelector('.chat-app');
    if (!app) return;

    var api = app.getAttribute('data-api');
    var csrf = app.getAttribute('data-csrf');
    var ativa = parseInt(app.getAttribute('data-ativa'), 10) || 0;
    var ultimo = parseInt(app.getAttribute('data-ultimo'), 10) || 0;
    var caixa = app.querySelector('.chat-mensagens');
    var form = app.querySelector('.chat-envio');
    var campo = form ? form.querySelector('textarea') : null;
    var botaoEnviar = form ? form.querySelector('.chat-enviar') : null;
    var erro = app.querySelector('.chat-erro');
    var vazia = app.querySelector('.chat-vazia');
    var sugestoes = app.querySelector('.chat-sugestoes');
    var faixaTexto = app.querySelector('.chat-faixa-texto');
    var lista = app.querySelector('.chat-itens');
    var enviando = false;
    var ultimoDia = null;
    var dias = app.querySelectorAll('.chat-dia span');
    if (dias.length) ultimoDia = dias[dias.length - 1].textContent;

    function esc(t) {
        return String(t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- filtro da lista ---------- */
    var filtro = app.querySelector('.chat-filtro-campo');
    if (filtro) {
        filtro.addEventListener('input', function () {
            var termo = filtro.value.trim().toLowerCase();
            app.querySelectorAll('.chat-item').forEach(function (item) {
                item.parentNode.hidden = termo !== '' && item.getAttribute('data-busca').indexOf(termo) === -1;
            });
        });
    }

    /* ---------- contador de mensagens no topo do site ---------- */
    function atualizarBalao(total) {
        document.querySelectorAll('.chat-topo-num').forEach(function (n) {
            n.hidden = !total;
            n.textContent = total > 9 ? '9+' : total;
        });
    }

    /* ---------- lista de conversas ---------- */
    function atualizarLista() {
        if (!lista) return;
        fetch(api + '?lista=1', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.ok) return;
                atualizarBalao(d.nao_lidas);
                if (!d.conversas.length) return;
                var termo = filtro ? filtro.value.trim().toLowerCase() : '';
                lista.innerHTML = d.conversas.map(function (c) {
                    var busca = (c.nome + ' ' + c.material).toLowerCase();
                    var oculto = termo !== '' && busca.indexOf(termo) === -1;
                    var extra = c.nao_lidas && c.id !== ativa ? '<span class="chat-badge">' + c.nao_lidas + '</span>'
                        : (c.encerrada ? '<span class="chat-tag-encerrada">Encerrada</span>' : '');
                    return '<li' + (oculto ? ' hidden' : '') + '><a class="chat-item' + (c.id === ativa ? ' ativa' : '') + (c.nao_lidas && c.id !== ativa ? ' tem-novas' : '')
                        + '" href="conversas.php?id=' + c.id + '" data-id="' + c.id + '" data-busca="' + esc(busca) + '">'
                        + '<span class="chat-avatar">' + esc(c.inicial) + '</span><span class="chat-item-texto">'
                        + '<span class="chat-item-linha"><strong>' + esc(c.nome) + '</strong><small>' + esc(c.quando) + '</small></span>'
                        + '<span class="chat-item-material"><i class="bi bi-box-seam"></i>' + esc(c.material) + '</span>'
                        + '<span class="chat-item-linha"><span class="chat-item-previa">' + esc(c.previa) + '</span>' + extra + '</span>'
                        + '</span></a></li>';
                }).join('');
            })
            .catch(function () {});
    }

    if (!ativa || !caixa) {
        setInterval(function () { if (!document.hidden) atualizarLista(); }, 10000);
        return;
    }

    /* ---------- mensagens ---------- */
    function pertoDoFim() {
        return caixa.scrollHeight - caixa.scrollTop - caixa.clientHeight < 120;
    }
    function rolarParaFim(suave) {
        caixa.scrollTo({ top: caixa.scrollHeight, behavior: suave ? 'smooth' : 'auto' });
    }

    function textoApagada(minha) {
        return '<p class="chat-apagada-texto"><i class="bi bi-slash-circle"></i>' + (minha ? 'Você apagou esta mensagem' : 'Esta mensagem foi apagada') + '</p>';
    }

    function bolhaHtml(m, pendente) {
        if (m.apagada) return textoApagada(m.minha) + '<span class="chat-hora">' + esc(m.hora) + '</span>';
        var visto = m.minha ? '<i class="bi ' + (pendente ? 'bi-clock' : (m.lida ? 'bi-check2-all lida' : 'bi-check2')) + '"></i>' : '';
        var menu = m.minha && !pendente ? '<button type="button" class="chat-bolha-menu" aria-label="Opções da mensagem" title="Opções"><i class="bi bi-chevron-down"></i></button>' : '';
        var foto = m.imagem ? '<a class="chat-foto" href="' + esc(m.imagem) + '" target="_blank" rel="noopener"><img src="' + esc(m.imagem) + '" alt="Foto enviada na conversa"></a>' : '';
        var texto = m.texto ? '<p>' + esc(m.texto).replace(/\n/g, '<br>') + '</p>' : '';
        return menu + foto + texto + '<span class="chat-hora">' + esc(m.hora) + visto + '</span>';
    }

    // Transforma uma bolha em "mensagem apagada" (vale para os dois lados)
    function marcarApagada(b) {
        if (!b || b.classList.contains('apagada')) return;
        var hora = b.querySelector('.chat-hora');
        var h = hora && hora.firstChild ? hora.firstChild.textContent : '';
        b.classList.add('apagada');
        b.classList.remove('com-foto');
        b.innerHTML = textoApagada(b.classList.contains('minha')) + '<span class="chat-hora">' + esc(h) + '</span>';
    }

    function adicionar(mensagens) {
        if (!mensagens.length) return;
        var rolar = pertoDoFim();
        var fim = caixa.querySelector('#fim');
        mensagens.forEach(function (m) {
            if (m.id <= ultimo || caixa.querySelector('.chat-bolha[data-id="' + m.id + '"]')) return;
            if (m.dia !== ultimoDia) {
                var dia = document.createElement('div');
                dia.className = 'chat-dia';
                dia.innerHTML = '<span>' + esc(m.dia) + '</span>';
                caixa.insertBefore(dia, fim);
                ultimoDia = m.dia;
            }
            var b = document.createElement('div');
            b.className = 'chat-bolha nova ' + (m.minha ? 'minha' : 'outra') + (m.imagem ? ' com-foto' : '') + (m.apagada ? ' apagada' : '');
            b.setAttribute('data-id', m.id);
            b.innerHTML = bolhaHtml(m, false);
            caixa.insertBefore(b, fim);
            ultimo = Math.max(ultimo, m.id);
            // Fotos carregam depois: mantém a conversa no fim quando terminam
            var img = b.querySelector('img');
            if (img && rolar) img.addEventListener('load', function () { rolarParaFim(false); });
        });
        if (vazia) vazia.hidden = true;
        if (sugestoes) sugestoes.hidden = true;
        if (rolar) rolarParaFim(true);
        else mostrarAvisoNovas();
    }

    var avisoNovas = null;
    function mostrarAvisoNovas() {
        if (avisoNovas) return;
        avisoNovas = document.createElement('button');
        avisoNovas.type = 'button';
        avisoNovas.className = 'chat-aviso-novas';
        avisoNovas.innerHTML = '<i class="bi bi-arrow-down"></i>Novas mensagens';
        avisoNovas.addEventListener('click', function () { rolarParaFim(true); });
        app.querySelector('.chat-janela').appendChild(avisoNovas);
    }
    caixa.addEventListener('scroll', function () {
        if (avisoNovas && pertoDoFim()) { avisoNovas.remove(); avisoNovas = null; }
    });

    function marcarVistos(ate) {
        caixa.querySelectorAll('.chat-bolha.minha').forEach(function (b) {
            if (parseInt(b.getAttribute('data-id'), 10) <= ate) {
                var i = b.querySelector('.chat-hora .bi');
                if (i && !i.classList.contains('lida') && !i.classList.contains('bi-clock')) {
                    i.className = 'bi bi-check2-all lida';
                    i.title = 'Lida';
                }
            }
        });
    }

    function aplicarEstado(d) {
        if (faixaTexto && d.aviso) faixaTexto.textContent = d.aviso;
        if (typeof d.lida_ate === 'number') marcarVistos(d.lida_ate);
        (d.apagadas || []).forEach(function (id) {
            marcarApagada(caixa.querySelector('.chat-bolha[data-id="' + id + '"]'));
        });
        if (typeof d.nao_lidas === 'number') atualizarBalao(d.nao_lidas);
        if (d.pode_enviar === false && form) {
            form.remove();
            form = null;
            if (sugestoes) sugestoes.remove();
            removerFoto();
            var aviso = document.createElement('div');
            aviso.className = 'chat-encerrado';
            aviso.innerHTML = '<i class="bi bi-lock"></i>Esta conversa foi encerrada. Você ainda pode ler o histórico.';
            app.querySelector('.chat-janela').appendChild(aviso);
            var faixa = app.querySelector('.chat-faixa');
            if (faixa) faixa.classList.add('encerrada');
        }
    }

    function buscarNovas() {
        return fetch(api + '?id=' + ativa + '&depois=' + ultimo, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.ok) return;
                adicionar(d.mensagens || []);
                aplicarEstado(d);
            })
            .catch(function () {});
    }

    /* ---------- menu da mensagem: copiar e apagar ---------- */
    var opcoes = null;           // menu aberto
    var paraApagar = null;       // bolha que aguarda confirmação
    var confirmar = app.querySelector('.chat-confirmar');

    function fecharOpcoes() {
        if (opcoes) { opcoes.remove(); opcoes = null; }
        caixa.querySelectorAll('.chat-bolha.menu-aberto').forEach(function (b) { b.classList.remove('menu-aberto'); });
    }

    function abrirOpcoes(bolha) {
        fecharOpcoes();
        bolha.classList.add('menu-aberto');
        var temTexto = !!bolha.querySelector('p:not(.chat-apagada-texto)');
        opcoes = document.createElement('div');
        opcoes.className = 'chat-bolha-opcoes';
        opcoes.setAttribute('role', 'menu');
        opcoes.innerHTML = (temTexto ? '<button type="button" role="menuitem" data-op="copiar"><i class="bi bi-copy"></i>Copiar texto</button>' : '')
            + '<button type="button" role="menuitem" data-op="apagar" class="perigo"><i class="bi bi-trash3"></i>Apagar mensagem</button>';
        bolha.appendChild(opcoes);
        // Perto do fim da tela, o menu abre para cima
        var r = opcoes.getBoundingClientRect(), rc = caixa.getBoundingClientRect();
        if (r.bottom > rc.bottom - 8) opcoes.classList.add('para-cima');
        opcoes.querySelector('button').focus();
    }

    function pedirConfirmacao(bolha) {
        paraApagar = bolha;
        if (!confirmar) { if (window.confirm('Apagar esta mensagem para todos?')) apagar(); return; }
        confirmar.hidden = false;
        document.body.classList.add('chat-travado');
        confirmar.querySelector('.chat-confirmar-nao').focus();
    }

    function fecharConfirmacao() {
        if (confirmar) confirmar.hidden = true;
        document.body.classList.remove('chat-travado');
        paraApagar = null;
    }

    function apagar() {
        var bolha = paraApagar;
        if (!bolha) return;
        var id = bolha.getAttribute('data-id');
        var botaoSim = confirmar ? confirmar.querySelector('.chat-confirmar-sim') : null;
        if (botaoSim) botaoSim.disabled = true;
        var dados = new FormData();
        dados.append('acao', 'apagar');
        dados.append('id', ativa);
        dados.append('id_mensagem', id);
        dados.append('depois', ultimo);
        dados.append('csrf_token', csrf);
        fetch(api, { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { mostrarErro(d.erro || 'Não foi possível apagar a mensagem.'); return; }
                bolha.classList.add('sumindo');
                setTimeout(function () { marcarApagada(bolha); bolha.classList.remove('sumindo'); }, 180);
                adicionar(d.mensagens || []);
                aplicarEstado(d);
                atualizarLista();
            })
            .catch(function () { mostrarErro('Sem conexão. A mensagem não foi apagada.'); })
            .then(function () {
                if (botaoSim) botaoSim.disabled = false;
                fecharConfirmacao();
            });
    }

    caixa.addEventListener('click', function (e) {
        var botaoMenu = e.target.closest('.chat-bolha-menu');
        if (botaoMenu) {
            e.preventDefault();
            var b = botaoMenu.closest('.chat-bolha');
            if (b.classList.contains('menu-aberto')) fecharOpcoes(); else abrirOpcoes(b);
            return;
        }
        var op = e.target.closest('.chat-bolha-opcoes [data-op]');
        if (op) {
            var bolha = op.closest('.chat-bolha');
            fecharOpcoes();
            if (op.getAttribute('data-op') === 'copiar') {
                var p = bolha.querySelector('p');
                var texto = p ? p.innerText : '';
                if (navigator.clipboard) navigator.clipboard.writeText(texto).catch(function () {});
            } else {
                pedirConfirmacao(bolha);
            }
        }
    });
    document.addEventListener('click', function (e) {
        if (opcoes && !e.target.closest('.chat-bolha-opcoes') && !e.target.closest('.chat-bolha-menu')) fecharOpcoes();
    });
    if (confirmar) {
        confirmar.querySelector('.chat-confirmar-nao').addEventListener('click', fecharConfirmacao);
        confirmar.querySelector('.chat-confirmar-sim').addEventListener('click', apagar);
        confirmar.addEventListener('click', function (e) { if (e.target === confirmar) fecharConfirmacao(); });
    }

    /* ---------- visualizador de fotos (tela cheia) ---------- */
    var visualizador = app.querySelector('.chat-visualizador');
    function abrirFoto(url) {
        if (!visualizador) { window.open(url, '_blank'); return; }
        visualizador.querySelector('img').src = url;
        visualizador.querySelector('.chat-visualizador-baixar').href = url + (url.indexOf('?') === -1 ? '?' : '&') + 'baixar=1';
        visualizador.hidden = false;
        document.body.classList.add('chat-travado');
        visualizador.querySelector('.chat-visualizador-fechar').focus();
    }
    function fecharFoto() {
        if (!visualizador || visualizador.hidden) return;
        visualizador.hidden = true;
        document.body.classList.remove('chat-travado');
    }
    caixa.addEventListener('click', function (e) {
        var link = e.target.closest('.chat-foto');
        if (!link) return;
        e.preventDefault();
        abrirFoto(link.getAttribute('href'));
    });
    if (visualizador) {
        visualizador.addEventListener('click', function (e) {
            if (e.target === visualizador || e.target.closest('.chat-visualizador-fechar')) fecharFoto();
        });
    }

    /* ---------- foto anexada (galeria, câmera, colar ou arrastar) ---------- */
    var previa = app.querySelector('.chat-previa');
    var inputGaleria = app.querySelector('#chat-galeria');
    var inputCameraCelular = app.querySelector('#chat-camera-celular');
    var foto = null;       // Blob pronto para enviar
    var fotoUrl = null;    // endereço local da prévia
    var LADO_MAXIMO = 1600;

    function mostrarErro(texto) {
        if (!erro) return;
        erro.textContent = texto || '';
        erro.hidden = !texto;
    }

    function removerFoto() {
        foto = null;
        if (fotoUrl) URL.revokeObjectURL(fotoUrl);
        fotoUrl = null;
        if (previa) previa.hidden = true;
        if (inputGaleria) inputGaleria.value = '';
        if (inputCameraCelular) inputCameraCelular.value = '';
    }

    // Reduz a foto para no máximo 1600 px e converte para JPG. Além de deixar
    // o envio leve, isso apaga os dados escondidos da foto (como o GPS).
    function prepararFoto(arquivo) {
        mostrarErro('');
        if (!arquivo || !/^image\//.test(arquivo.type)) {
            mostrarErro('Escolha um arquivo de imagem (JPG, PNG, WEBP ou GIF).');
            return;
        }
        if (arquivo.size > 30 * 1024 * 1024) {
            mostrarErro('Essa foto é grande demais. Escolha uma de até 30 MB.');
            return;
        }
        if (arquivo.type === 'image/gif') {     // GIF fica como está (pode ser animado)
            if (arquivo.size > 8 * 1024 * 1024) { mostrarErro('GIFs podem ter no máximo 8 MB.'); return; }
            usarFoto(arquivo);
            return;
        }
        var url = URL.createObjectURL(arquivo);
        var img = new Image();
        img.onload = function () {
            var escala = Math.min(1, LADO_MAXIMO / Math.max(img.naturalWidth, img.naturalHeight));
            var tela = document.createElement('canvas');
            tela.width = Math.round(img.naturalWidth * escala);
            tela.height = Math.round(img.naturalHeight * escala);
            var ctx = tela.getContext('2d');
            ctx.fillStyle = '#fff';                       // PNG transparente vira fundo branco
            ctx.fillRect(0, 0, tela.width, tela.height);
            ctx.drawImage(img, 0, 0, tela.width, tela.height);
            URL.revokeObjectURL(url);
            tela.toBlob(function (blob) {
                if (!blob) { mostrarErro('Não foi possível preparar essa foto.'); return; }
                usarFoto(blob);
            }, 'image/jpeg', 0.85);
        };
        img.onerror = function () {
            URL.revokeObjectURL(url);
            mostrarErro('Não foi possível abrir essa imagem. Tente outra foto.');
        };
        img.src = url;
    }

    function usarFoto(blob) {
        removerFoto();
        foto = blob;
        fotoUrl = URL.createObjectURL(blob);
        if (previa) {
            previa.querySelector('img').src = fotoUrl;
            previa.hidden = false;
        }
        if (campo) campo.focus();
    }

    if (inputGaleria) {
        inputGaleria.addEventListener('change', function () {
            if (inputGaleria.files[0]) prepararFoto(inputGaleria.files[0]);
        });
    }
    if (inputCameraCelular) {
        inputCameraCelular.addEventListener('change', function () {
            if (inputCameraCelular.files[0]) prepararFoto(inputCameraCelular.files[0]);
        });
    }
    if (previa) {
        previa.querySelector('.chat-previa-remover').addEventListener('click', function () {
            removerFoto();
            if (campo) campo.focus();
        });
    }
    // Colar uma imagem (Ctrl+V) no campo de mensagem
    if (campo) {
        campo.addEventListener('paste', function (e) {
            var itens = (e.clipboardData && e.clipboardData.items) || [];
            for (var i = 0; i < itens.length; i++) {
                if (itens[i].kind === 'file' && /^image\//.test(itens[i].type)) {
                    e.preventDefault();
                    prepararFoto(itens[i].getAsFile());
                    return;
                }
            }
        });
    }
    // Arrastar uma foto para dentro da conversa
    var janela = app.querySelector('.chat-janela');
    if (form && janela) {
        ['dragenter', 'dragover'].forEach(function (ev) {
            janela.addEventListener(ev, function (e) {
                if (!form || !e.dataTransfer || Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') === -1) return;
                e.preventDefault();
                janela.classList.add('arrastando');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            janela.addEventListener(ev, function (e) {
                if (ev === 'dragleave' && janela.contains(e.relatedTarget)) return;
                janela.classList.remove('arrastando');
            });
        });
        janela.addEventListener('drop', function (e) {
            if (!form || !e.dataTransfer || !e.dataTransfer.files.length) return;
            e.preventDefault();
            prepararFoto(e.dataTransfer.files[0]);
        });
    }

    /* ---------- câmera ---------- */
    var botaoCamera = app.querySelector('.chat-abrir-camera');
    var statusCamera = app.querySelector('.chat-camera-status');
    var modal = app.querySelector('.chat-camera');
    var video = modal ? modal.querySelector('video') : null;
    var tela = modal ? modal.querySelector('canvas') : null;
    var msgCamera = modal ? modal.querySelector('.chat-camera-msg') : null;
    var btnDisparar = modal ? modal.querySelector('.chat-camera-disparar') : null;
    var btnUsar = modal ? modal.querySelector('.chat-camera-usar') : null;
    var btnRefazer = modal ? modal.querySelector('.chat-camera-refazer') : null;
    var btnTrocar = modal ? modal.querySelector('.chat-camera-trocar') : null;
    var fluxo = null;
    var cameras = [];
    var cameraAtual = 0;
    var semCamera = false;
    var motivoSemCamera = '';
    // Celular/tablet: usa a câmera do próprio aparelho (abre o app de câmera)
    var aparelhoMovel = window.matchMedia('(pointer: coarse)').matches && /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

    function marcarSemCamera(motivo) {
        semCamera = true;
        motivoSemCamera = motivo;
        if (botaoCamera) botaoCamera.classList.add('sem-camera');
        if (statusCamera) statusCamera.textContent = 'Nenhuma câmera encontrada';
    }

    // No computador, verifica se existe webcam antes de oferecer a câmera
    if (!aparelhoMovel) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            marcarSemCamera(window.isSecureContext === false
                ? 'A câmera só funciona quando o site é aberto em https ou localhost. Use a Galeria para enviar uma foto salva.'
                : 'Seu navegador não permite usar a câmera. Use a Galeria para enviar uma foto salva.');
        } else if (navigator.mediaDevices.enumerateDevices) {
            navigator.mediaDevices.enumerateDevices().then(function (dispositivos) {
                cameras = dispositivos.filter(function (d) { return d.kind === 'videoinput'; });
                if (!cameras.length) marcarSemCamera('Não encontramos nenhuma webcam neste computador. Use a Galeria para enviar uma foto salva.');
            }).catch(function () {});
        }
    }

    function avisoCamera(texto) {
        if (!msgCamera) return;
        msgCamera.textContent = texto || '';
        msgCamera.hidden = !texto;
        if (video) video.hidden = !!texto;
        if (btnDisparar) btnDisparar.hidden = !!texto;
    }

    function pararCamera() {
        if (fluxo) fluxo.getTracks().forEach(function (t) { t.stop(); });
        fluxo = null;
        if (video) video.srcObject = null;
    }

    function ligarCamera() {
        pararCamera();
        avisoCamera('');
        tela.hidden = true;
        video.hidden = false;
        btnDisparar.hidden = false;
        btnUsar.hidden = true;
        btnRefazer.hidden = true;
        var pedido = cameras[cameraAtual] && cameras[cameraAtual].deviceId
            ? { video: { deviceId: { exact: cameras[cameraAtual].deviceId }, width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false }
            : { video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false };
        navigator.mediaDevices.getUserMedia(pedido).then(function (s) {
            fluxo = s;
            video.srcObject = s;
            video.play().catch(function () {});
            // Depois da permissão os nomes das câmeras aparecem: atualiza a lista
            navigator.mediaDevices.enumerateDevices().then(function (d) {
                cameras = d.filter(function (x) { return x.kind === 'videoinput'; });
                btnTrocar.hidden = cameras.length < 2;
            });
        }).catch(function (e) {
            var nome = e && e.name;
            if (nome === 'NotAllowedError' || nome === 'SecurityError') {
                avisoCamera('Você não permitiu o uso da câmera. Para liberar, clique no ícone de cadeado ao lado do endereço do site e permita a câmera. Ou use a Galeria.');
            } else if (nome === 'NotFoundError' || nome === 'OverconstrainedError') {
                marcarSemCamera('Não encontramos nenhuma webcam neste computador. Use a Galeria para enviar uma foto salva.');
                avisoCamera(motivoSemCamera);
            } else if (nome === 'NotReadableError') {
                avisoCamera('A câmera está sendo usada por outro programa (como uma chamada de vídeo). Feche-o e tente de novo.');
            } else {
                avisoCamera('Não foi possível ligar a câmera. Use a Galeria para enviar uma foto salva.');
            }
        });
    }

    function abrirCamera() {
        mostrarErro('');
        if (aparelhoMovel && inputCameraCelular) { inputCameraCelular.click(); return; }
        if (semCamera) { mostrarErro(motivoSemCamera); return; }
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('chat-travado');
        ligarCamera();
    }

    function fecharCamera() {
        if (!modal || modal.hidden) return;
        pararCamera();
        modal.hidden = true;
        document.body.classList.remove('chat-travado');
        if (campo) campo.focus();
    }

    if (botaoCamera) botaoCamera.addEventListener('click', abrirCamera);
    if (modal) {
        modal.querySelector('.chat-camera-fechar').addEventListener('click', fecharCamera);
        modal.addEventListener('click', function (e) { if (e.target === modal) fecharCamera(); });
        btnDisparar.addEventListener('click', function () {
            if (!video.videoWidth) return;
            tela.width = video.videoWidth;
            tela.height = video.videoHeight;
            tela.getContext('2d').drawImage(video, 0, 0, tela.width, tela.height);
            tela.hidden = false;
            video.hidden = true;
            btnDisparar.hidden = true;
            btnTrocar.hidden = true;
            btnUsar.hidden = false;
            btnRefazer.hidden = false;
            modal.classList.remove('flash');
            void modal.offsetWidth;
            modal.classList.add('flash');
        });
        btnRefazer.addEventListener('click', function () {
            tela.hidden = true;
            video.hidden = false;
            btnDisparar.hidden = false;
            btnUsar.hidden = true;
            btnRefazer.hidden = true;
            btnTrocar.hidden = cameras.length < 2;
        });
        btnUsar.addEventListener('click', function () {
            tela.toBlob(function (blob) {
                fecharCamera();
                if (blob) prepararFoto(new File([blob], 'camera.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.9);
        });
        btnTrocar.addEventListener('click', function () {
            if (cameras.length < 2) return;
            cameraAtual = (cameraAtual + 1) % cameras.length;
            ligarCamera();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { fecharCamera(); fecharFoto(); fecharOpcoes(); if (paraApagar) fecharConfirmacao(); }
    });

    /* ---------- envio ---------- */
    function ajustarAltura() {
        campo.style.height = 'auto';
        campo.style.height = Math.min(campo.scrollHeight, 160) + 'px';
    }

    function enviar() {
        var texto = campo.value.trim();
        if ((!texto && !foto) || enviando) return;
        enviando = true;
        mostrarErro('');
        botaoEnviar.disabled = true;

        // Mostra a mensagem na hora, com um reloginho até o servidor confirmar
        var agora = new Date();
        var fotoEnviada = foto;
        var provisoria = document.createElement('div');
        provisoria.className = 'chat-bolha minha nova enviando' + (fotoEnviada ? ' com-foto' : '');
        provisoria.innerHTML = bolhaHtml({ minha: true, texto: texto, imagem: fotoEnviada ? fotoUrl : null, hora: ('0' + agora.getHours()).slice(-2) + ':' + ('0' + agora.getMinutes()).slice(-2) }, true);
        caixa.insertBefore(provisoria, caixa.querySelector('#fim'));
        if (vazia) vazia.hidden = true;
        if (sugestoes) sugestoes.hidden = true;
        rolarParaFim(true);
        campo.value = '';
        ajustarAltura();

        var dados = new FormData();
        dados.append('acao', 'enviar');
        dados.append('id', ativa);
        dados.append('texto', texto);
        dados.append('depois', ultimo);
        dados.append('csrf_token', csrf);
        if (fotoEnviada) dados.append('foto', fotoEnviada, fotoEnviada.type === 'image/gif' ? 'foto.gif' : 'foto.jpg');
        var urlPrevia = fotoUrl;
        foto = null; fotoUrl = null;                 // a prévia some; a bolha provisória mostra a foto
        if (previa) previa.hidden = true;

        function restaurar(msg) {
            mostrarErro(msg);
            if (!campo.value) { campo.value = texto; ajustarAltura(); }
            if (fotoEnviada && !foto) {                // devolve a foto para tentar de novo
                foto = fotoEnviada; fotoUrl = urlPrevia;
                if (previa) { previa.querySelector('img').src = fotoUrl; previa.hidden = false; }
            }
        }

        fetch(api, { method: 'POST', body: dados, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                provisoria.remove();
                if (!d.ok) { restaurar(d.erro || 'Não foi possível enviar. Tente de novo.'); return; }
                if (urlPrevia) URL.revokeObjectURL(urlPrevia);
                if (inputGaleria) inputGaleria.value = '';
                adicionar(d.mensagens || []);
                aplicarEstado(d);
                rolarParaFim(true);
                atualizarLista();
            })
            .catch(function () {
                provisoria.remove();
                restaurar('Sem conexão. A mensagem não foi enviada.');
            })
            .then(function () {
                enviando = false;
                if (botaoEnviar) botaoEnviar.disabled = false;
                if (campo) campo.focus();
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) { e.preventDefault(); enviar(); });
        campo.addEventListener('input', ajustarAltura);
        campo.addEventListener('keydown', function (e) {
            // Enter envia; Shift+Enter quebra a linha
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); enviar(); }
        });
        if (window.matchMedia('(min-width: 768px)').matches) campo.focus();
    }
    if (sugestoes) {
        sugestoes.addEventListener('click', function (e) {
            var s = e.target.closest('.chat-sugestao');
            if (!s || !campo) return;
            campo.value = s.textContent;
            ajustarAltura();
            campo.focus();
        });
    }

    rolarParaFim(false);
    window.addEventListener('load', function () { rolarParaFim(false); }); // depois das fotos carregarem
    var ciclo = 0;
    setInterval(function () {
        if (document.hidden) return;
        buscarNovas();
        if (++ciclo % 3 === 0) atualizarLista();
    }, 3500);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) buscarNovas(); });
    window.addEventListener('pagehide', pararCamera);
})();
