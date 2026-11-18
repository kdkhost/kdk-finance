{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : Marcelo Brad - RJ
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
@stack('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    // Service Worker — instalação no Windows (Edge/Chrome) e Android.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' }).catch(function () {
                // Falha silenciosa: PWA é um recurso progressivo.
            });
        });
    }
</script>
</body>
</html>
