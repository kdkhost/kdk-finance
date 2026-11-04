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

@section('title', 'Licenças')
@section('eyebrow', 'Licenças')
@section('subtitle', 'Serviços contratados da LicenseBoy com valores individuais, renovação e faturas vinculadas.')

@section('actions')
    <div class="d-flex flex-wrap gap-2">
        @if (auth()->user()->canManageFinance())
            <form method="POST" action="{{ route('licenses.panel.sync') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-repeat me-1"></i> Sincronizar painel
                </button>
            </form>
        @endif
        <a href="{{ route('licenses.prices') }}" class="btn btn-outline-secondary">
            <i class="bi bi-tags me-1"></i> Preços da loja
        </a>
        @if (auth()->user()->canManageFinance())
            <a href="{{ route('licenses.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nova licença
            </a>
        @endif
    </div>
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $amount = fn ($value, $currency) => ($currency === 'USD' ? 'US$' : '€').' '.number_format((float) $value, 2, ',', '.');
    @endphp

    @if ($rows->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-key"></i></div>
                    <h3>Nenhuma licença cadastrada</h3>
                    <p>Cadastre cada serviço contratado para controlar valores e gerar as faturas aqui no sistema. O catálogo com os preços atuais da loja está disponível em “Preços da loja”.</p>
                    @if (auth()->user()->canManageFinance())
                        <a href="{{ route('licenses.create') }}" class="btn btn-primary btn-sm">Cadastrar primeira licença</a>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="stat-grid mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Licenças ativas</span>
                        <span class="stat-icon"><i class="bi bi-key"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['active'] }}</div>
                    <div class="stat-meta mt-2">{{ $rows->count() }} cadastradas no total</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Custo mensal</span>
                        <span class="stat-icon is-blue"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['monthly_brl']) }}</div>
                    <div class="stat-meta mt-2">
                        Custo real do painel, em BRL
                        @if ($totals['divergence_brl'] > 0)
                            <span class="d-block text-danger">Loja: {{ $money($totals['store_monthly_brl']) }} · +{{ $money($totals['divergence_brl']) }}/mês</span>
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
                    <div class="fw-semibold">Serviços de licença</div>
                    <div class="text-muted-soft" style="font-size:.76rem;">{{ $totals['invoices'] }} faturas vinculadas · valores na moeda original e em BRL</div>
                </div>
                <a href="https://licenseboy.com" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> LicenseBoy
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 licenses-table">
                    <thead>
                        <tr>
                            <th>Licença</th>
                            <th class="d-none d-lg-table-cell">Servidor</th>
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
                                    <a href="{{ route('licenses.show', $license) }}" class="cell-title d-block">{{ $license->name }}</a>
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
                                                <i class="bi bi-shop me-1"></i>
                                                Painel {{ $amount($license->billingUnitPrice(), $license->billingCurrency()) }}
                                                @if ($license->panelDivergencePercent() !== null && $license->panelDivergencePercent() > 0 && $license->catalogItem)
                                                    <span class="text-danger">· loja {{ $amount($license->catalogItem->price, $license->catalogItem->currency) }}</span>
                                                @endif
                                            </span>
                                        @endif
                                        <span class="d-sm-none">
                                            {{ $amount($license->totalOriginal(), $license->billingCurrency()) }} no ciclo
                                            · <span class="{{ $license->statusBadgeClass() }}">{{ $license->statusLabel() }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="mono d-none d-lg-table-cell" style="font-size:.78rem;">{{ $license->server_name ?: '—' }}</td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge text-bg-light border">{{ $license->billingCycleLabel() }}</span>
                                    @if ($license->quantity > 1)
                                        <span class="cell-sub d-block">{{ $license->quantity }} unidades</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell text-end tabular">
                                    {{ $amount($license->totalOriginal(), $license->billingCurrency()) }}
                                    @if ($license->quantity > 1)
                                        <span class="cell-sub d-block">{{ $amount($license->billingUnitPrice(), $license->billingCurrency()) }} cada</span>
                                    @endif
                                    @if ($license->usesPanelPrice())
                                        <span class="cell-sub d-block">
                                            Painel {{ $amount($license->panel_price, $license->panel_currency ?: $license->currency) }}
                                            @if ($license->panelDivergencePercent() !== null && $license->panelDivergencePercent() > 0)
                                                <span class="text-danger">· loja {{ $amount($license->catalogItem->price, $license->catalogItem->currency) }}</span>
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
                                        <a href="{{ route('licenses.show', $license) }}" class="btn btn-ghost btn-sm" title="Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if (auth()->user()->canManageFinance())
                                            <a href="{{ route('licenses.edit', $license) }}" class="btn btn-ghost btn-sm" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @if ($license->status === \App\Models\LicenseService::STATUS_ACTIVE)
                                                <form method="POST" action="{{ route('licenses.invoice', $license) }}" data-confirm="Gerar fatura de {{ $license->product }} no valor de {{ $amount($license->totalOriginal(), $license->billingCurrency()) }}? O próximo vencimento avançará um ciclo." data-confirm-button="Gerar fatura">
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
                O preço da loja é a referência pública da LicenseBoy, mas o valor realmente cobrado pode ser maior — o painel de
                cliente não permite editar o valor recorrente. Registre o valor real em cada licença (ou use “Sincronizar painel”)
                e o sistema passa a calcular faturas por ele, mostrando a diferença em relação à loja. Ao gerar a fatura, aplica-se
                spread, IOF, ICMS e demais tributos pela cotação atual.
            </div>
        </div>
    @endif
@endsection
