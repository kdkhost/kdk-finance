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
@include('admin.layouts.header')

<div class="app-wrapper">
    @include('admin.layouts.navbar')
    @include('admin.layouts.sidebar')

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <div class="page-eyebrow">@yield('eyebrow', 'Painel financeiro')</div>
                    <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                    @hasSection('subtitle')
                        <p class="page-subtitle">@yield('subtitle')</p>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @yield('actions')
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </main>

    @include('admin.layouts.footer')
</div>

@include('admin.layouts.scripts')
