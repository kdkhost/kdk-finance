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

@section('title', 'Visão geral')
@section('eyebrow', 'Dashboard')
@section('subtitle', 'Cotações, tributos e conciliação das faturas internacionais em um só lugar · '.now()->translatedFormat('d \d\e F \d\e Y'))

@section('actions')
    @if (auth()->user()->isDeveloperAdmin())
        <form method="POST" action="{{ route('settings.rates.refresh') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-repeat me-1"></i> Atualizar cotações
            </button>
        </form>
    @endif
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('invoices.export') }}" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Exportar
        </a>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova fatura
        </a>
    @endif
@endsection

@section('content')
    @php
        $rateStale = collect($rates)->contains(fn ($rate) => $rate['stale'] ?? false);
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $euro = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
    @endphp

    @if ($rateStale)
        <div class="hint-banner mb-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 30%, transparent);">
            <i class="bi bi-wifi-off"></i>
            <div>
                A AwesomeAPI está indisponível neste momento. O sistema está usando a última cotação conhecida
                ({{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('d/m/Y H:i') }}). Os valores podem estar desatualizados.
            </div>
        </div>
    @endif

    <div class="stat-grid mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Pendente</span>
                    <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="stat-value mt-2" data-live="pending-total">{{ $money($totals['pending_total']) }}</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="stat-meta"><span data-live="pending-count">{{ $totals['pending_count'] }}</span> faturas em aberto</span>
                    @if ($totals['overdue_count'] > 0)
                        <span class="stat-delta is-up"><i class="bi bi-exclamation-triangle"></i> <span data-live="overdue-count">{{ $totals['overdue_count'] }}</span> vencidas</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Pago no mês</span>
                    <span class="stat-icon is-blue"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="stat-value mt-2" data-live="paid-month-total">{{ $money($totals['paid_month_total']) }}</div>
                <div class="stat-meta mt-2">{{ $totals['paid_month_count'] }} faturas quitadas em {{ now()->translatedFormat('F') }}</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Vencidas</span>
                    <span class="stat-icon is-red"><i class="bi bi-exclamation-octagon"></i></span>
                </div>
                <div class="stat-value mt-2" data-live="overdue-total">{{ $money($totals['overdue_total']) }}</div>
                <div class="stat-meta mt-2">
                    {{ $totals['overdue_count'] }} faturas fora do prazo
                    @if ($nextDueDate)
                        · próximo vencimento {{ $nextDueDate->format('d/m') }}
                    @endif
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-label">Cotações</span>
                    <span class="stat-icon is-amber"><i class="bi bi-currency-exchange"></i></span>
                </div>
                @foreach (['USD', 'EUR'] as $currency)
                    @php $rate = $rates[$currency]; @endphp
                    <div class="rate-line">
                        <span class="rate-pair">
                            {{ $currency }} <span class="text-muted-soft" style="font-size:.72rem;">{{ config('finance.currencies.'.$currency.'.symbol') }}</span>
                        </span>
                        <span class="text-end">
                            <span class="rate-value" data-live="rate-{{ $currency }}">{{ number_format($rate['rate'], 4, ',', '.') }}</span>
                            <span class="stat-delta {{ $rate['pct_change'] >= 0 ? 'is-up' : 'is-down' }} ms-1" data-live="rate-{{ $currency }}-change">
                                <i class="bi {{ $rate['pct_change'] >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                                <span data-live="rate-{{ $currency }}-pct">{{ number_format(abs($rate['pct_change']), 2, ',', '.') }}%</span>
                            </span>
                        </span>
                    </div>
                @endforeach
                <div class="stat-meta mt-2">
                    {{ $rateStale ? 'Última cotação conhecida' : 'AwesomeAPI' }} ·
                    <span data-live="rates-at">{{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Evolução financeira</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Somatório em BRL nos últimos 6 meses</div>
                    </div>
                    <span class="badge text-bg-light border">BRL</span>
                </div>
                <div class="card-body">
                    <div class="chart-shell">
                        <canvas id="chart-monthly" aria-label="Gráfico de valores pagos e pendentes" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div class="fw-semibold">Composição da carteira</div>
                    <div class="text-muted-soft" style="font-size:.76rem;">{{ array_sum($statusDistribution) }} faturas registradas</div>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="chart-shell flex-grow-1" style="min-height: 200px;">
                        <canvas id="chart-status" aria-label="Distribuição de faturas por status" role="img"></canvas>
                    </div>
                    <div class="surface-muted p-3 mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted-soft" style="font-size:.74rem;">Tributos em faturas pendentes</span>
                            <strong class="tabular" data-live="taxes-pending">{{ $money($totals['taxes_pending']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted-soft" style="font-size:.74rem;">Spread + IOF + ICMS + outros</span>
                            <a href="{{ route('invoices.index', ['status' => 'pending']) }}" class="fw-semibold" style="font-size:.74rem;">Ver faturas</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Faturas recentes</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Atualização em tempo real quando o câmbio varia</div>
                    </div>
                    <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">Ver todas <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fatura</th>
                                <th>Status</th>
                                <th>Vencimento</th>
                                <th class="text-end">Total (BRL)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                <tr data-invoice-row="{{ $invoice->id }}">
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="cell-title d-block">{{ \Illuminate\Support\Str::limit($invoice->description, 44) }}</a>
                                        <span class="cell-sub">
                                            {{ $invoice->external_reference ?: 'Manual' }} ·
                                            {{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}
                                        </span>
                                    </td>
                                    <td><span class="{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                                    <td class="tabular {{ $invoice->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                                    <td class="text-end tabular fw-semibold" data-invoice-total="{{ $invoice->id }}">
                                        {{ $money($invoice->total_brl) }}
                                        @if ($invoice->rate_locked)
                                            <i class="bi bi-lock-fill ms-1 text-muted-soft" title="Câmbio travado" style="font-size:.7rem;"></i>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <div class="icon"><i class="bi bi-receipt"></i></div>
                                            <h3>Nenhuma fatura ainda</h3>
                                            <p>Importe as faturas da OVHcloud pelo comando de sincronização ou cadastre a primeira manualmente.</p>
                                            @if (auth()->user()->canManageFinance())
                                                <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">Cadastrar fatura</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">Atenção imediata</div>
                    <span class="badge text-bg-light border">{{ $overdueInvoices->count() }}</span>
                </div>
                <div class="card-body">
                    @forelse ($overdueInvoices as $invoice)
                        <a href="{{ route('invoices.show', $invoice) }}" class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom text-reset">
                            <span>
                                <span class="d-block fw-semibold" style="font-size:.84rem;">{{ \Illuminate\Support\Str::limit($invoice->description, 34) }}</span>
                                <span class="text-muted-soft" style="font-size:.72rem;">Venceu em {{ $invoice->due_date?->format('d/m/Y') }}</span>
                            </span>
                            <span class="tabular fw-semibold text-danger" style="font-size:.82rem;">{{ $money($invoice->total_brl) }}</span>
                        </a>
                    @empty
                        <div class="empty-state py-4">
                            <div class="icon"><i class="bi bi-shield-check"></i></div>
                            <h3>Nada vencido</h3>
                            <p class="mb-0">Todas as faturas pendentes estão dentro do prazo.</p>
                        </div>
                    @endforelse

                    <div class="surface-muted p-3 mt-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-info-circle text-primary"></i>
                            <span class="fw-semibold" style="font-size:.8rem;">Como o cálculo funciona</span>
                        </div>
                        <p class="mb-0 text-muted-soft" style="font-size:.74rem;line-height:1.5;">
                            Valor original × cotação + spread + IOF + ICMS + outros tributos. Faturas pagas têm o câmbio travado automaticamente.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Serviços contratados e valores individuais</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">
                            {{ $infrastructure['services'] }} serviços · custo mensal {{ $euro($infrastructure['monthly_eur']) }}
                            ({{ $money($infrastructure['monthly_brl']) }})
                        </div>
                    </div>
                    <a href="{{ route('infrastructure.index') }}" class="btn btn-ghost btn-sm">Ver infraestrutura <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 infra-services-table">
                        <thead>
                            <tr>
                                <th>Serviço</th>
                                <th class="d-none d-md-table-cell">Tipo</th>
                                <th class="d-none d-md-table-cell">Estado</th>
                                <th class="text-end d-none d-sm-table-cell">Mensal (EUR)</th>
                                <th class="text-end">Mensal (BRL)</th>
                                <th class="d-none d-md-table-cell">Renovação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $row)
                                @php $service = $row['service']; @endphp
                                <tr>
                                    <td class="infra-service-cell">
                                        <span class="cell-title d-block">{{ $service->label() }}</span>
                                        <span class="cell-sub mono">{{ $service->name }}</span>
                                        <div class="service-mobile-meta d-md-none">
                                            <span class="d-flex flex-wrap gap-1">
                                                <span class="badge text-bg-light border">{{ $service->typeLabel() }}</span>
                                                <span class="{{ $service->state === 'ok' || $service->state === 'active' ? 'badge-status badge-status--paid' : 'badge-status badge-status--pending' }}">
                                                    {{ $service->stateLabel() }}
                                                </span>
                                            </span>
                                            <span>Renova em {{ $service->renewalLabel() }}</span>
                                            <span class="d-sm-none fw-semibold text-body">{{ $service->priceLabel('EUR') }}</span>
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell"><span class="badge text-bg-light border">{{ $service->typeLabel() }}</span></td>
                                    <td class="d-none d-md-table-cell">
                                        <span class="{{ $service->state === 'ok' || $service->state === 'active' ? 'badge-status badge-status--paid' : 'badge-status badge-status--pending' }}">
                                            {{ $service->stateLabel() }}
                                        </span>
                                    </td>
                                    <td class="text-end tabular d-none d-sm-table-cell">{{ $service->priceLabel('EUR') }}</td>
                                    <td class="text-end tabular fw-semibold">{{ $row['monthly_brl'] !== null ? $money($row['monthly_brl']) : '—' }}</td>
                                    <td class="tabular d-none d-md-table-cell">{{ $service->renewalLabel() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <div class="icon"><i class="bi bi-hdd-stack"></i></div>
                                            <h3>Nenhum serviço sincronizado</h3>
                                            <p>Rode a sincronização da OVHcloud para importar os serviços contratados e os valores individuais.</p>
                                            @if (auth()->user()->isDeveloperAdmin())
                                                <a href="{{ route('settings.index') }}" class="btn btn-primary btn-sm">Abrir configurações</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($services->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="fw-semibold">Total mensal</td>
                                    <td class="text-end tabular fw-semibold d-none d-sm-table-cell">{{ $euro($infrastructure['monthly_eur']) }}</td>
                                    <td class="text-end tabular fw-semibold">{{ $money($infrastructure['monthly_brl']) }}</td>
                                    <td class="text-muted-soft d-none d-md-table-cell" style="font-size:.74rem;">Cotação EUR {{ number_format($eurRate, 4, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">Endereços IP</div>
                    <span class="badge text-bg-light border">{{ $infrastructure['ips'] }}</span>
                </div>
                <div class="card-body">
                    @forelse ($ips as $ip)
                        <div class="py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="mono fw-semibold" style="font-size:.8rem;">{{ $ip->block }}</span>
                                <span class="badge text-bg-light border">{{ $ip->versionLabel() }} · {{ $ip->typeLabel() }}</span>
                            </div>
                            <div class="text-muted-soft" style="font-size:.72rem;">
                                {{ $ip->routed_to ?: 'Sem serviço vinculado' }}
                                @if ($ip->primaryReverse())
                                    · {{ $ip->primaryReverse() }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state py-4">
                            <div class="icon"><i class="bi bi-diagram-3"></i></div>
                            <h3>Nenhum IP importado</h3>
                            <p class="mb-0">A sincronização da OVHcloud também traz os blocos de IP.</p>
                        </div>
                    @endforelse

                    <a href="{{ route('infrastructure.index') }}" class="btn btn-outline-secondary w-100 mt-3">
                        <i class="bi bi-hdd-network me-1"></i> Abrir infraestrutura completa
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="dashboard-data">{!! json_encode([
        'series' => $series,
        'distribution' => $statusDistribution,
        'totals' => $totals,
        'rates' => $rates,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
