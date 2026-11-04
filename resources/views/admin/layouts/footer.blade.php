{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : George Marcelo de Oliveira Pimentel
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
<footer class="app-footer">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold text-body">© {{ now()->year }} {{ config('finance.developer.company') }}</span>
            <span class="text-muted-soft">CNPJ {{ config('finance.developer.cnpj') }}</span>
            <span class="d-none d-md-inline text-muted-soft">·</span>
            <span class="text-muted-soft">
                Responsável técnico:
                <a href="{{ config('finance.developer.site') }}" target="_blank" rel="noopener" class="fw-semibold">
                    {{ config('finance.developer.name') }}
                </a>
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted-soft">{{ config('finance.developer.email') }}</span>
            <span class="badge text-bg-light border tabular">v1.5.4</span>
        </div>
    </div>
</footer>
