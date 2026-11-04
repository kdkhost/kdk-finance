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
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="{{ config('finance.developer.name') }} — {{ config('finance.developer.company') }}">
    <meta name="theme-color" content="{{ config('finance.pwa.theme_color') }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="error-page">
        <div class="error-card card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="brand-mark">KDK</span>
                    <button type="button" class="btn-icon" data-theme-toggle title="Alternar tema" aria-label="Alternar tema">
                        <i class="bi bi-moon-stars-fill"></i>
                    </button>
                </div>

                <div class="error-code">@yield('code')</div>
                <h1 class="fs-4 fw-bold mt-2 mb-2" style="letter-spacing:-.03em;">@yield('heading')</h1>
                <p class="text-muted-soft mb-4" style="font-size:.9rem;line-height:1.6;">@yield('message')</p>

                <div class="d-flex flex-wrap gap-2">
                    @if (auth()->check())
                        <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-grid-1x2 me-1"></i> Voltar ao painel</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i> Ir para o login</a>
                    @endif
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Ver faturas</a>
                </div>

                <hr class="my-4">
                <div class="text-muted-soft" style="font-size:.72rem;line-height:1.7;">
                    {{ config('finance.developer.company') }} · CNPJ {{ config('finance.developer.cnpj') }}<br>
                    Desenvolvido por {{ config('finance.developer.name') }} · v1.8.2
                </div>
            </div>
        </div>
    </main>
</body>
</html>
