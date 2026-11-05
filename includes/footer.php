
</main>

<!-- Bootstrap JS (necessário para o menu responsivo funcionar) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Scripts específicos de cada página (ex.: Leaflet no mapa) -->
<?php foreach (($scripts_extras ?? []) as $script): ?>
<script src="<?php echo htmlspecialchars($script, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>

<!-- JS próprio (?v=2 força o navegador a baixar a versão nova e não usar cache) -->
<script src="<?php echo BASE_URL; ?>js/script.js?v=2"></script>

</body>
</html>
