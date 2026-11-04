<script>
    // Define o tema antes do CSS carregar para não haver piscada em claro/escuro.
    (function () {
        try {
            var theme = window.localStorage.getItem('kdk-theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.setAttribute('data-bs-theme', theme);
                document.documentElement.style.colorScheme = theme;
            }
        } catch (error) {
            // Armazenamento bloqueado: segue o tema claro padrão.
        }
    })();
</script>
