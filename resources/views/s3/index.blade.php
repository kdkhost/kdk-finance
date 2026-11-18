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

@section('title', 'IDrive e2 (S3)')
@section('eyebrow', 'Armazenamento S3')
@section('subtitle', 'Serviços e buckets contratados no IDrive e2 (S3) com valores individuais, renovação e faturas vinculadas.')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        @if (auth()->user()->isDeveloperAdmin())
            <form method="POST" action="{{ route('s3.panel.sync') }}" data-idrivee2-sync-form>
                @csrf
                <input type="hidden" name="otp_code" value="">
                <button type="submit" class="btn btn-outline-secondary" data-idrivee2-sync-btn>
                    <i class="bi bi-arrow-repeat me-1"></i> Sincronizar painel S3
                </button>
            </form>
        @endif
        <a href="{{ route('s3.prices') }}" class="btn btn-outline-secondary">
            <i class="bi bi-tags me-1"></i> Planos S3
        </a>
        @if (auth()->user()->isDeveloperAdmin())
            <a href="{{ route('settings.index', ['tab' => 'idrivee2']) }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders2 me-1"></i> Credenciais S3
            </a>
            <a href="{{ route('s3.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Novo serviço S3
            </a>
        @endif
    </div>
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $amount = fn ($value, $currency) => ($currency === 'USD' ? 'US$' : '€').' '.number_format((float) $value, 2, ',', '.');
    @endphp

    @if (! $panel['configured'])
        <div class="hint-banner mb-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 30%, transparent);">
            <i class="bi bi-key"></i>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
                <span>
                    As credenciais do console <strong>IDrive e2 (S3)</strong> ainda não foram salvas no painel. Cadastre o login e a senha em Configurações para puxar todos os serviços e faturas.
                </span>
                @if (auth()->user()->isDeveloperAdmin())
                    <a href="{{ route('settings.index', ['tab' => 'idrivee2']) }}" class="btn btn-primary btn-sm">
                        Configurar IDrive e2
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-cloud-arrow-up"></i></div>
                    <h3>Nenhum serviço S3 cadastrado</h3>
                    <p>
                        Conecte sua conta do <strong>IDrive e2</strong> (<span class="mono">{{ $panel['email'] ?: 'console.idrivee2.com' }}</span>) clicando em “Sincronizar painel S3” (com suporte a 2FA) ou cadastre um plano de armazenamento S3 manualmente.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        @if (auth()->user()->isDeveloperAdmin())
                            <form method="POST" action="{{ route('s3.panel.sync') }}" data-idrivee2-sync-form>
                                @csrf
                                <input type="hidden" name="otp_code" value="">
                                <button type="submit" class="btn btn-outline-secondary btn-sm" data-idrivee2-sync-btn>
                                    <i class="bi bi-arrow-repeat me-1"></i> Sincronizar com IDrive e2
                                </button>
                            </form>
                            <a href="{{ route('s3.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i> Cadastrar serviço S3
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="stat-grid mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Serviços S3 ativos</span>
                        <span class="stat-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['active'] }}</div>
                    <div class="stat-meta mt-2">{{ $rows->count() }} cadastrados no total</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Custo mensal S3</span>
                        <span class="stat-icon is-blue"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['monthly_brl']) }}</div>
                    <div class="stat-meta mt-2">
                        Custo real do console, em BRL
                        @if ($totals['divergence_brl'] > 0)
                            <span class="d-block text-danger">Tabela: {{ $money($totals['store_monthly_brl']) }} · +{{ $money($totals['divergence_brl']) }}/mês</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Projeção anual</span>
                        <span class="stat-icon is-amber"><i class="bi bi-calendar3"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['yearly_brl']) }}</div>
                    <div class="stat-meta mt-2">Mensal × 12 na cotação atual</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Renovações próximas</span>
                        <span class="stat-icon is-red"><i class="bi bi-alarm"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['due_soon'] }}</div>
                    <div class="stat-meta mt-2">
                        @if ($totals['next_due'])
                            Próximo vencimento {{ $totals['next_due']->format('d/m/Y') }}
                        @else
                            Nenhum vencimento cadastrado
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">Planos e Buckets S3 contratados (IDrive e2)</div>
                    <div class="text-muted-soft" style="font-size:.76rem;">
                        {{ $totals['invoices'] }} faturas vinculadas · valores na moeda original e em BRL
                        @if ($panel['synced_at'])
                            · sincronizado em {{ \Illuminate\Support\Carbon::parse($panel['synced_at'])->format('d/m/Y H:i') }}
                        @endif
                    </div>
                </div>
                <a href="https://console.idrivee2.com/signin" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Console IDrive e2
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 licenses-table">
                    <thead>
                        <tr>
                            <th>Serviço S3 / Plano</th>
                            <th class="d-none d-lg-table-cell">Endpoint / Região</th>
                            <th class="d-none d-md-table-cell">Ciclo</th>
                            <th class="d-none d-md-table-cell text-end">Valor do ciclo</th>
                            <th class="d-none d-sm-table-cell text-end">Mensal (BRL)</th>
                            <th class="d-none d-md-table-cell">Próximo vencimento</th>
                            <th class="d-none d-sm-table-cell">Situação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $license = $row['license']; @endphp
                            <tr>
                                <td class="license-title-cell">
                                    <a href="{{ route('s3.show', $license) }}" class="cell-title d-block">{{ $license->name }}</a>
                                    <span class="cell-sub">{{ $license->product }} · {{ $license->provider }}</span>

                                    <div class="license-mobile-meta d-md-none">
                                        <span><i class="bi bi-arrow-repeat me-1"></i>{{ $license->billingCycleLabel() }}</span>
                                        <span>
                                            <i class="bi bi-calendar3 me-1"></i>
                                            {{ $license->next_due_date?->format('d/m/Y') ?? 'Sem vencimento' }}
                                        </span>
                                        <span class="d-sm-none fw-semibold text-body">
                                            {{ $money($row['monthly_brl']) }}/mês
                                        </span>
                                        @if ($license->usesPanelPrice())
                                            <span>
                                                <i class="bi bi-cloud-check me-1"></i>
                                                Console {{ $amount($license->billingUnitPrice(), $license->billingCurrency()) }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="mono d-none d-lg-table-cell" style="font-size:.78rem;">{{ $license->server_name ?: 'console.idrivee2.com' }}</td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge text-bg-light border">{{ $license->billingCycleLabel() }}</span>
                                    @if ($license->quantity > 1)
                                        <span class="cell-sub d-block">{{ $license->quantity }} TB / unid.</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell text-end tabular">
                                    {{ $amount($license->totalOriginal(), $license->billingCurrency()) }}
                                    @if ($license->usesPanelPrice())
                                        <span class="cell-sub d-block">
                                            Console {{ $amount($license->panel_price, $license->panel_currency ?: $license->currency) }}
                                            @if ($license->panelDivergencePercent() !== null && $license->panelDivergencePercent() > 0 && $license->catalogItem)
                                                <span class="text-danger">· tabela {{ $amount($license->catalogItem->price, $license->catalogItem->currency) }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="d-none d-sm-table-cell text-end tabular fw-semibold">
                                    {{ $money($row['monthly_brl']) }}
                                    <span class="cell-sub d-block">{{ $amount($license->monthlyEquivalent(), $license->billingCurrency()) }}/mês</span>
                                </td>
                                <td class="d-none d-md-table-cell tabular">
                                    @if ($license->next_due_date)
                                        <span class="{{ $license->isDueSoon() ? 'text-danger fw-semibold' : '' }}">{{ $license->next_due_date->format('d/m/Y') }}</span>
                                        @if ($license->isDueSoon())
                                            <span class="cell-sub d-block text-danger">Renova em breve</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="d-none d-sm-table-cell"><span class="{{ $license->statusBadgeClass() }}">{{ $license->statusLabel() }}</span></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('s3.show', $license) }}" class="btn btn-ghost btn-sm" title="Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if (auth()->user()->isDeveloperAdmin())
                                            <a href="{{ route('s3.edit', $license) }}" class="btn btn-ghost btn-sm" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @if ($license->status === \App\Models\LicenseService::STATUS_ACTIVE)
                                                <form method="POST" action="{{ route('s3.invoice', $license) }}" data-confirm="Gerar fatura de {{ $license->name }} no valor de {{ $amount($license->totalOriginal(), $license->billingCurrency()) }}? O próximo vencimento avançará um ciclo." data-confirm-button="Gerar fatura">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost btn-sm" title="Gerar fatura">
                                                        <i class="bi bi-receipt"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hint-banner mt-3">
            <i class="bi bi-info-circle"></i>
            <div>
                Os serviços do <strong>IDrive e2 (S3)</strong> seguem a mesma gestão financeira do LicenseBoy: você controla o valor de tabela (ou promo de 1º ano) versus o valor recorrente cobrado no console, gera as faturas convertidas em BRL com spread, IOF e tributos de importação, e recebe alertas antecipados antes do vencimento.
            </div>
        </div>
    @endif
@endsection
