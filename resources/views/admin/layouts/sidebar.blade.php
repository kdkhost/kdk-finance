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
@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
    $canManage = $user->canManageFinance();
    $isDeveloper = $user->isDeveloperAdmin();

    $operacao = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'bi-grid-1x2',
            'url' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
        ],
        [
            'key' => 'financeiro',
            'label' => 'Financeiro',
            'icon' => 'bi-wallet2',
            'open_default' => true,
            'children' => [
                [
                    'key' => 'faturas',
                    'label' => 'Faturas',
                    'icon' => 'bi-receipt',
                    'open_default' => true,
                    'children' => array_values(array_filter([
                        [
                            'key' => 'faturas-lista',
                            'label' => 'Todas as faturas',
                            'icon' => 'bi-list-ul',
                            'url' => route('invoices.index'),
                            'active' => request()->routeIs('invoices.index', 'invoices.show', 'invoices.edit'),
                        ],
                        $canManage ? [
                            'key' => 'faturas-nova',
                            'label' => 'Nova fatura',
                            'icon' => 'bi-plus-square',
                            'url' => route('invoices.create'),
                            'active' => request()->routeIs('invoices.create'),
                        ] : null,
                    ])),
                ],
                [
                    'key' => 'conversoes',
                    'label' => 'Conversões',
                    'icon' => 'bi-currency-exchange',
                    'children' => array_values(array_filter([
                        [
                            'key' => 'conversoes-lista',
                            'label' => 'Todas as conversões',
                            'icon' => 'bi-list-ul',
                            'url' => route('conversions.index'),
                            'active' => request()->routeIs('conversions.index', 'conversions.show', 'conversions.edit'),
                        ],
                        $canManage ? [
                            'key' => 'conversoes-nova',
                            'label' => 'Nova conversão',
                            'icon' => 'bi-arrow-left-right',
                            'url' => route('conversions.create'),
                            'active' => request()->routeIs('conversions.create'),
                        ] : null,
                    ])),
                ],
                [
                    'key' => 'notificacoes',
                    'label' => 'Notificações',
                    'icon' => 'bi-bell',
                    'url' => route('notifications.index'),
                    'active' => request()->routeIs('notifications.*'),
                    'badge' => true,
                ],
            ],
        ],
        [
            'key' => 'infraestrutura',
            'label' => 'Infraestrutura',
            'icon' => 'bi-hdd-stack',
            'open_default' => true,
            'children' => [
                [
                    'key' => 'servicos-ovh',
                    'label' => 'Serviços OVH',
                    'icon' => 'bi-server',
                    'url' => route('infrastructure.index'),
                    'active' => request()->routeIs('infrastructure.*'),
                ],
                [
                    'key' => 'licencas',
                    'label' => 'Licenças',
                    'icon' => 'bi-key',
                    'children' => array_values(array_filter([
                        [
                            'key' => 'licencas-lista',
                            'label' => 'Licenças',
                            'icon' => 'bi-list-ul',
                            'url' => route('licenses.index'),
                            'active' => request()->routeIs('licenses.index', 'licenses.show', 'licenses.edit'),
                        ],
                        [
                            'key' => 'licencas-precos',
                            'label' => 'Tabela de preços',
                            'icon' => 'bi-tags',
                            'url' => route('licenses.prices'),
                            'active' => request()->routeIs('licenses.prices'),
                        ],
                        $canManage ? [
                            'key' => 'licencas-nova',
                            'label' => 'Nova licença',
                            'icon' => 'bi-plus-square',
                            'url' => route('licenses.create'),
                            'active' => request()->routeIs('licenses.create'),
                        ] : null,
                    ])),
                ],
            ],
        ],
    ];

    $sistemaChildren = array_values(array_filter([
        $canManage ? [
            'key' => 'configuracoes',
            'label' => 'Configurações',
            'icon' => 'bi-sliders2',
            'url' => route('settings.index'),
            'active' => request()->routeIs('settings.*'),
        ] : null,
        $isDeveloper ? [
            'key' => 'crons',
            'label' => 'Central de cron',
            'icon' => 'bi-clock-history',
            'url' => route('crons.index'),
            'active' => request()->routeIs('crons.*'),
        ] : null,
        $isDeveloper ? [
            'key' => 'email-templates',
            'label' => 'Modelos de e-mail',
            'icon' => 'bi-envelope-paper',
            'url' => route('email-templates.index'),
            'active' => request()->routeIs('email-templates.*'),
        ] : null,
        $isDeveloper ? [
            'key' => 'usuarios',
            'label' => 'Usuários e acessos',
            'icon' => 'bi-people',
            'url' => route('users.index'),
            'active' => request()->routeIs('users.*'),
        ] : null,
    ]));

    $administracao = array_values(array_filter([
        $sistemaChildren !== [] ? [
            'key' => 'sistema',
            'label' => 'Sistema',
            'icon' => 'bi-gear',
            'children' => $sistemaChildren,
        ] : null,
        [
            'key' => 'perfil',
            'label' => 'Meu perfil',
            'icon' => 'bi-person-gear',
            'url' => route('profile.edit'),
            'active' => request()->routeIs('profile.*'),
        ],
    ]));

    // Abre automaticamente todo grupo que contém a página atual.
    $markOpenPath = function (array $items) use (&$markOpenPath): array {
        foreach ($items as &$item) {
            if (! empty($item['children'])) {
                $item['children'] = $markOpenPath($item['children']);

                foreach ($item['children'] as $child) {
                    if (! empty($child['active']) || ! empty($child['open']) || ! empty($child['active_path'])) {
                        $item['open'] = true;
                        $item['active_path'] = true;
                        break;
                    }
                }
            }
        }

        unset($item);

        return $items;
    };

    $operacao = $markOpenPath($operacao);
    $administracao = $markOpenPath($administracao);
@endphp
<aside class="app-sidebar shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
            <span class="brand-mark">KDK</span>
            <span class="brand-text">
                KDK Finance
                <small>Multimoedas</small>
            </span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="sidebar-menu" aria-label="Menu principal">
            <div class="nav-header">Operação</div>
            <ul class="nav flex-column">
                @include('admin.layouts.sidebar-items', ['items' => $operacao, 'level' => 1])
            </ul>

            <div class="nav-header">Administração</div>
            <ul class="nav flex-column">
                @include('admin.layouts.sidebar-items', ['items' => $administracao, 'level' => 1])
            </ul>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="fw-semibold text-white-50">KDKHost Soluções</div>
        <div>CNPJ {{ config('finance.developer.cnpj') }}</div>
        <div class="mt-2">Desenvolvido por Marcelo Brad - RJ</div>
        <div class="mt-1 opacity-75">v1.10.5 · Laravel {{ Illuminate\Foundation\Application::VERSION }}</div>
    </div>
</aside>
