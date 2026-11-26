
</main>

<!-- Rodapé -->
<footer class="footer-rhe mt-5">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <a class="footer-brand" href="<?php echo BASE_URL; ?>index.php">
                    <img src="<?php echo BASE_URL; ?>img/logo-icon.svg" alt="" width="34" height="34">
                    <span>Rhe<span class="brand-sys">sys</span></span>
                </a>
                <p class="footer-texto mt-3 mb-0">
                    Educação ambiental e tecnologia para descartar cada resíduo do jeito certo,
                    reaproveitar materiais e encontrar pontos de coleta.
                </p>
            </div>
            <div class="col-6 col-md-4 col-lg-2 offset-lg-1">
                <h2 class="footer-titulo">Descarte</h2>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>pages/residuos.php">Resíduos</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/pontos_coleta.php">Pontos de coleta</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/compartilhamentos.php">Compartilhamentos</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h2 class="footer-titulo">Aprenda</h2>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>pages/conteudos.php">Conteúdos</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/quiz.php">Quizzes</a></li>
                    <li><a href="<?php echo BASE_URL; ?>pages/jogo.php">Jogo da reciclagem</a></li>
                </ul>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <h2 class="footer-titulo">Projeto</h2>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>pages/sobre.php">Sobre o Rhesys</a></li>
                    <?php if (usuario_logado()): ?>
                        <li><a href="<?php echo BASE_URL; ?>pages/meus_compartilhamentos.php">Meus compartilhamentos</a></li>
                        <li><a href="<?php echo BASE_URL; ?>pages/logout.php">Sair</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>pages/login.php">Entrar</a></li>
                        <li><a href="<?php echo BASE_URL; ?>pages/cadastro.php">Criar conta</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-base">
            <span>&copy; <?php echo date('Y'); ?> Rhesys · Trabalho de Conclusão de Curso — IFSP São João da Boa Vista</span>
            <span>Fotos: Unsplash e <a href="<?php echo BASE_URL; ?>docs/FONTES_FOTOS_RESIDUOS.md">Wikimedia Commons</a></span>
        </div>
    </div>
</footer>

<!-- Bootstrap JS (necessário para o menu responsivo funcionar) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts específicos de cada página (ex.: Leaflet no mapa) -->
<?php foreach (($scripts_extras ?? []) as $script): ?>
<script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>

<!-- JS próprio (?v= força o navegador a baixar a versão nova e não usar cache) -->
<script src="<?php echo BASE_URL; ?>js/script.js?v=6"></script>
<script src="<?php echo BASE_URL; ?>js/autocompletar.js?v=3"></script>
<?php if (usuario_logado()): ?><script src="<?php echo BASE_URL; ?>js/notificacoes.js?v=4"></script><?php endif; ?>

</body>
</html>
