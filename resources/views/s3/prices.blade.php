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
@extends('admin.layouts.app')

@section('title', 'Planos IDrive e2 (S3)')
@section('eyebrow', 'Armazenamento S3')
@section('subtitle', 'Tabela de planos de armazenamento S3 compatível da IDrive e2 (mensal, anual e promocional). Use um item para cadastrar ou vincular um serviço.')

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('s3.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Serviços S3
        </a>
        @if (auth()->user()->isDeveloperAdmin())
            <form method="POST" action="{{ route('s3.prices.sync') }}" data-confirm="Atualizar a tabela de planos do IDrive e2 agora?" data-confirm-button="Atualizar planos">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-arrow-repeat me-1"></i> Atualizar tabela S3
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <div class="stat-grid mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Planos no catálogo S3</span>
                    <span class="stat-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $total }}</div>
                <div class="stat-meta mt-2">{{ $groups->count() }} modalidades disponíveis</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Última sincronização</span>
                    <span class="stat-icon is-blue"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="stat-value mt-2" style="font-size:1.15rem;">
                    {{ $lastSyncedAt ? $lastSyncedAt->format('d/m/Y H:i') : 'Agora' }}
                </div>
                <div class="stat-meta mt-2">Tabela oficial em USD</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Serviços S3 vinculados</span>
                    <span class="stat-icon is-amber"><i class="bi bi-link-45deg"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $linked }}</div>
                <div class="stat-meta mt-2">Acompanham o valor de tabela</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Cotação USD hoje</span>
                    <span class="stat-icon is-blue"><i class="bi bi-currency-dollar"></i></span>
                </div>
                <div class="stat-value mt-2">R$ {{ number_format((float) ($rates['USD']['rate'] ?? 0), 4, ',', '.') }}</div>
                <div class="stat-meta mt-2">Usada na conversão das faturas S3</div>
            </div>
        </div>
    </div>

    @foreach ($groups as $group)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">{{ $group['label'] }}</div>
                    <div class="text-muted-soft" style="font-size:.76rem;">{{ $group['items']->count() }} planos · Egress gratuito até 3× o volume</div>
                </div>
                <a href="https://console.idrivee2.com/signin" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Console IDrive e2
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Plano S3</th>
                            <th class="text-end">Preço (USD)</th>
                            <th class="text-end">Equivalente (BRL)</th>
                            <th class="d-none d-md-table-cell">Ciclo</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['items'] as $item)
                            @php
                                $usdRate = (float) ($rates['USD']['rate'] ?? 0);
                                $brlPrice = round((float) $item->price * $usdRate, 2);
                            @endphp
                            <tr>
                                <td>
                                    <span class="cell-title d-block">{{ $item->name }}</span>
                                    <span class="cell-sub mono" style="font-size:.7rem;">{{ $item->slug }}</span>
                                </td>
                                <td class="text-end tabular fw-semibold">{{ $item->priceLabel() }}</td>
                                <td class="text-end tabular">R$ {{ number_format($brlPrice, 2, ',', '.') }}</td>
                                <td class="d-none d-md-table-cell"><span class="badge text-bg-light border">{{ $item->cycleLabel() }}</span></td>
                                <td class="text-end">
                                    @if (auth()->user()->isDeveloperAdmin())
                                        <a href="{{ route('s3.create', ['catalogo' => $item->id]) }}" class="btn btn-ghost btn-sm">
                                            <i class="bi bi-plus-circle me-1"></i> Usar este plano
                                        </a>
                                    @else
                                        <span class="text-muted-soft" style="font-size:.74rem;">Somente consulta</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
