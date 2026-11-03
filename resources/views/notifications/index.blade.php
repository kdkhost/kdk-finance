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
@extends('admin.layouts.app')

@section('title', 'Central de notificações')
@section('eyebrow', 'Alertas')
@section('subtitle', 'Variações cambiais, faturas vencidas e eventos de sincronização com a OVHcloud.')

@section('actions')
    @if ($notifications->total() > 0)
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i> Marcar todas como lidas
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Histórico</span>
            <span class="text-muted-soft" style="font-size:.76rem;">{{ $notifications->total() }} registro(s)</span>
        </div>
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $isUnread = $notification->read_at === null;
                    $icon = data_get($data, 'icon') === 'trending-down' ? 'bi-graph-down-arrow' : 'bi-graph-up-arrow';
                @endphp
                <div class="notification-item {{ $isUnread ? 'is-unread' : '' }} d-flex align-items-start gap-3 py-3 px-3">
                    <span class="icon mt-1"><i class="bi {{ $icon }}"></i></span>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <span class="title">{{ data_get($data, 'title', 'Atualização do sistema') }}</span>
                            <span class="meta">{{ $notification->created_at?->format('d/m/Y H:i') }} · {{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="meta mb-2">{{ data_get($data, 'message', '') }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            @if (data_get($data, 'url'))
                                <a href="{{ data_get($data, 'url') }}" class="btn btn-ghost btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> Abrir fatura
                                </a>
                            @endif
                            @if ($isUnread)
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">
                                        <i class="bi bi-check2 me-1"></i> Marcar como lida
                                    </button>
                                </form>
                            @else
                                <span class="badge text-bg-light border align-self-center">Lida</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-bell-slash"></i></div>
                    <h3>Nenhuma notificação por aqui</h3>
                    <p>Os alertas de variação cambial acima do limiar configurado aparecerão nesta lista.</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Voltar ao painel</a>
                </div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="card-body border-top">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
