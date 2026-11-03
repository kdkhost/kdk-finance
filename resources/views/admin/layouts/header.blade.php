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
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="author" content="{{ config('finance.developer.name') }} — {{ config('finance.developer.company') }}">
    <meta name="copyright" content="{{ config('finance.developer.company') }} (CNPJ {{ config('finance.developer.cnpj') }})">
    <meta name="description" content="Sistema financeiro multimoedas para conciliação de faturas OVHcloud e licenças internacionais.">
    <meta name="theme-color" content="{{ config('finance.pwa.theme_color') }}">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Painel') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.__financeUser = {!! json_encode([
            'id' => auth()->id(),
            'name' => auth()->user()?->name,
            'role' => auth()->user()?->role,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};
    </script>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
