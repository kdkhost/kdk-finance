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
@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
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
        <div class="sidebar-user">
            <span class="avatar">{{ $user->initials() }}</span>
            <div class="min-w-0">
                <div class="name text-truncate">{{ $user->name }}</div>
                <div class="role">{{ $user->roleLabel() }}</div>
            </div>
        </div>

        <nav class="sidebar-menu mt-1">
            <div class="nav-header">Operação</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.index', 'invoices.show', 'invoices.edit') ? 'active' : '' }}">
                        <i class="bi bi-receipt"></i>
                        <span>Faturas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('infrastructure.index') }}" class="nav-link {{ request()->routeIs('infrastructure.*') ? 'active' : '' }}">
                        <i class="bi bi-hdd-stack"></i>
                        <span>Serviços OVH</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('licenses.index') }}" class="nav-link {{ request()->routeIs('licenses.*') ? 'active' : '' }}">
                        <i class="bi bi-key"></i>
                        <span>Licenças</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('conversions.index') }}" class="nav-link {{ request()->routeIs('conversions.*') ? 'active' : '' }}">
                        <i class="bi bi-currency-exchange"></i>
                        <span>Conversões</span>
                    </a>
                </li>
                @if ($user->canManageFinance())
                    <li class="nav-item">
                        <a href="{{ route('invoices.create') }}" class="nav-link {{ request()->routeIs('invoices.create') ? 'active' : '' }}">
                            <i class="bi bi-plus-square"></i>
                            <span>Nova fatura</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('conversions.create') }}" class="nav-link {{ request()->routeIs('conversions.create') ? 'active' : '' }}">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Nova conversão</span>
                        </a>
                    </li>
                @endif
            </ul>

            <div class="nav-header">Sistema</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                        <i class="bi bi-bell"></i>
                        <span>Notificações</span>
                        @if ($unread > 0)
                            <span class="badge">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </a>
                </li>
                @if ($user->canManageFinance())
                    <li class="nav-item">
                        <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <i class="bi bi-sliders2"></i>
                            <span>Configurações</span>
                        </a>
                    </li>
                @endif
                @if ($user->isDeveloperAdmin())
                    <li class="nav-item">
                        <a href="{{ route('crons.index') }}" class="nav-link {{ request()->routeIs('crons.*') ? 'active' : '' }}">
                            <i class="bi bi-clock-history"></i>
                            <span>Central de cron</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="bi bi-people"></i>
                            <span>Usuários e acessos</span>
                        </a>
                    </li>
                @endif
                <li class="nav-item">
                    <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                        <i class="bi bi-person-gear"></i>
                        <span>Meu perfil</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="fw-semibold text-white-50">KDKHost Soluções</div>
        <div>CNPJ {{ config('finance.developer.cnpj') }}</div>
        <div class="mt-2">Desenvolvido por George Marcelo</div>
        <div class="mt-1 opacity-75">v1.5.1 · Laravel {{ Illuminate\Foundation\Application::VERSION }}</div>
    </div>
</aside>
