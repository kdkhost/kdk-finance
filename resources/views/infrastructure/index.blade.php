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

@section('title', 'Serviços OVHcloud')
@section('eyebrow', 'Infraestrutura')
@section('subtitle', 'Todos os serviços contratados, valores mensais individuais e blocos de IP da conta OVHcloud.')

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $euro = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    @endphp

    @if ($services->isEmpty() && $ips->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-hdd-network"></i></div>
                    <h3>Nenhum serviço importado ainda</h3>
                    <p>Rode a sincronização para trazer os serviços contratados, os IPs e os valores individuais de cada fatura.</p>
                    <code class="mono d-inline-block p-2 rounded" style="background: var(--kdk-surface-muted);">php artisan finance:sync-invoices --import --fresh</code>
                </div>
            </div>
        </div>
    @else
        <div class="stat-grid mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Serviços contratados</span>
                        <span class="stat-icon"><i class="bi bi-hdd-stack"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['services'] }}</div>
                    <div class="stat-meta mt-2">Todos os serviços ativos da conta</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Blocos de IP</span>
                        <span class="stat-icon is-blue"><i class="bi bi-diagram-3"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['ips'] }}</div>
                    <div class="stat-meta mt-2">IPv4 e IPv6 com DNS reverso</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Custo mensal (EUR)</span>
                        <span class="stat-icon is-amber"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $euro($totals['monthly_eur']) }}</div>
                    <div class="stat-meta mt-2">Somatório da última fatura de cada serviço</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Custo mensal (BRL)</span>
                        <span class="stat-icon is-red"><i class="bi bi-currency-exchange"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['monthly_brl']) }}</div>
                    <div class="stat-meta mt-2">Cotação EUR {{ number_format($eurRate, 4, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Serviços contratados</div>
                            <div class="text-muted-soft" style="font-size:.76rem;">Valor mensal extraído da fatura mais recente de cada serviço</div>
                        </div>
                        <span class="badge text-bg-light border">{{ $services->count() }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th class="text-end">Mensal (EUR)</th>
                                    <th class="text-end">Mensal (BRL)</th>
                                    <th>Renovação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($services as $row)
                                    @php $service = $row['service']; @endphp
                                    <tr>
                                        <td>
                                            <span class="cell-title d-block">{{ $service->label() }}</span>
                                            <span class="cell-sub mono">{{ $service->name }}</span>
                                            @if ($service->last_bill_id)
                                                <span class="cell-sub d-block">Última fatura: {{ $service->last_bill_id }}</span>
                                            @endif
                                        </td>
                                        <td><span class="badge text-bg-light border">{{ $service->typeLabel() }}</span></td>
                                        <td>
                                            <span class="{{ $service->state === 'ok' || $service->state === 'active' ? 'badge-status badge-status--paid' : 'badge-status badge-status--pending' }}">
                                                {{ $service->stateLabel() }}
                                            </span>
                                        </td>
                                        <td class="text-end tabular">{{ $service->priceLabel('EUR') }}</td>
                                        <td class="text-end tabular fw-semibold">
                                            {{ $row['monthly_brl'] !== null ? $money($row['monthly_brl']) : '—' }}
                                        </td>
                                        <td class="tabular">{{ $service->renewalLabel() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="fw-semibold">Endereços IP</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Blocos roteados para os seus serviços</div>
                    </div>
                    <div class="card-body">
                        @foreach ($ips as $ip)
                            <div class="py-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="mono fw-semibold" style="font-size:.82rem;">{{ $ip->block }}</span>
                                    <span class="badge text-bg-light border">{{ $ip->versionLabel() }} · {{ $ip->typeLabel() }}</span>
                                </div>
                                <div class="text-muted-soft" style="font-size:.74rem;">
                                    {{ $ip->routed_to ?: 'Sem serviço vinculado' }}
                                    @if ($ip->primaryReverse())
                                        · {{ $ip->primaryReverse() }}
                                    @endif
                                </div>
                                @if ($ip->is_additional || $ip->campus || $ip->rir)
                                    <div class="text-muted-soft" style="font-size:.72rem;">
                                        {{ collect([$ip->campus, $ip->rir, $ip->is_additional ? 'Adicional' : null])->filter()->implode(' · ') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @if ($ips->isEmpty())
                            <p class="text-muted-soft mb-0" style="font-size:.8rem;">Nenhum bloco de IP importado.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="hint-banner mt-3">
            <i class="bi bi-clock-history"></i>
            <div>
                Valores individuais calculados a partir das linhas de cada fatura. Última sincronização:
                <strong>{{ $lastSync ? \Illuminate\Support\Carbon::parse($lastSync)->format('d/m/Y H:i') : '—' }}</strong>.
                Para atualizar, rode <span class="mono">php artisan finance:sync-invoices --import --fresh</span>.
            </div>
        </div>
    @endif
@endsection
