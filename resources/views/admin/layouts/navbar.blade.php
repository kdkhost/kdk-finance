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
    $unreadCount = $user->unreadNotifications()->count();
    $latestNotifications = $user->unreadNotifications()->limit(4)->get();
@endphp
<nav class="app-header navbar navbar-expand">
    <div class="container-fluid">
        <ul class="navbar-nav align-items-center">
            <li class="nav-item">
                <a class="nav-link btn-icon" data-lte-toggle="sidebar" href="#" role="button" aria-label="Alternar menu">
                    <i class="bi bi-list"></i>
                </a>
            </li>
            <li class="nav-item d-none d-md-inline-flex align-items-center me-2">
                <span class="live-indicator" data-live-indicator>
                    <span class="live-dot"></span>
                    <span data-live-label>{{ config('broadcasting.default') === 'reverb' ? 'Tempo real ativo' : 'Atualiza em segundo plano' }}</span>
                </span>
            </li>
            <li class="nav-item d-inline-flex align-items-center">
                <span class="live-clock" title="Horário de São Paulo (America/Sao_Paulo)" aria-label="Horário de São Paulo">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span class="live-clock-date d-none d-lg-inline" data-live-clock-date>{{ now()->format('d/m/Y') }}</span>
                    <time class="live-clock-time tabular" data-live-clock-time datetime="{{ now()->toIso8601String() }}">{{ now()->format('H:i:s') }}</time>
                    <span class="live-clock-zone d-none d-xl-inline">São Paulo</span>
                </span>
            </li>
        </ul>

        <ul class="navbar-nav ms-auto align-items-center gap-1 navbar-quick">
            @if ($user->canManageFinance())
                <li class="nav-item d-none d-sm-block">
                    <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Nova fatura
                    </a>
                </li>
            @endif

            <li class="nav-item">
                <button type="button" class="btn-icon" data-theme-toggle title="Alternar tema" aria-label="Alternar tema">
                    <i class="bi bi-moon-stars-fill"></i>
                </button>
            </li>

            <li class="nav-item dropdown notification-bell">
                <a class="btn-icon" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Central de notificações">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot" data-live="unread-dot" @if ($unreadCount === 0) hidden @endif>{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-2" style="width: 340px;">
                    <div class="d-flex align-items-center justify-content-between px-2 pb-2">
                        <span class="dropdown-header p-0">Notificações</span>
                        <span class="badge text-bg-light tabular" data-live="unread-count" @if ($unreadCount === 0) hidden @endif>{{ $unreadCount }}</span>
                    </div>

                    @forelse ($latestNotifications as $notification)
                        <a class="notification-item is-unread" href="{{ data_get($notification->data, 'url', route('notifications.index')) }}">
                            <span class="icon"><i class="bi bi-graph-up-arrow"></i></span>
                            <span>
                                <span class="title d-block">{{ data_get($notification->data, 'title', 'Atualização') }}</span>
                                <span class="meta d-block">{{ \Illuminate\Support\Str::limit(data_get($notification->data, 'message', ''), 64) }}</span>
                                <span class="meta d-block">{{ $notification->created_at?->diffForHumans() }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="px-2 py-3 text-center text-muted-soft" style="font-size: .8rem;">
                            <i class="bi bi-check2-circle d-block mb-1" style="font-size: 1.2rem;"></i>
                            Nenhuma notificação pendente.
                        </div>
                    @endforelse

                    <div class="d-flex gap-1 pt-1">
                        <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm flex-fill">Ver todas</a>
                        @if ($unreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" class="flex-fill">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm w-100">Marcar lidas</button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>

            <li class="nav-item dropdown">
                <a class="user-chip dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ $user->initials() }}</span>
                    <span class="d-none d-lg-inline text-start" style="line-height:1.1;">
                        <span class="d-block" style="font-size:.78rem;font-weight:700;">{{ $user->name }}</span>
                        <span class="d-block text-muted-soft" style="font-size:.64rem;text-transform:uppercase;letter-spacing:.08em;">{{ $user->roleLabel() }}</span>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="bi bi-person-gear me-2"></i> Meu perfil
                        </a>
                    </li>
                    @if ($user->canManageFinance())
                        <li>
                            <a class="dropdown-item" href="{{ route('settings.index') }}">
                                <i class="bi bi-sliders2 me-2"></i> Configurações
                            </a>
                        </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Sair
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
