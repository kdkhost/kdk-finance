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

@section('title', 'Painel Geral')
@section('eyebrow', 'Dashboard Executiva')
@section('subtitle', 'Cotações em tempo real, conciliação de faturas, infraestrutura OVHcloud e serviços em nuvem S3.')

@section('actions')
    <div class="d-flex flex-wrap align-items-center gap-2">
        @if (auth()->user()->isDeveloperAdmin())
            <form method="POST" action="{{ route('settings.rates.refresh') }}" data-ajax-refresh-rates class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary" data-refresh-rates-btn title="Atualizar cotações sem recarregar a página">
                    <i class="bi bi-arrow-repeat me-1"></i> Atualizar Cotações
                </button>
            </form>
        @endif
        @if (auth()->user()->canManageFinance())
            <a href="{{ route('invoices.export') }}" class="btn btn-outline-secondary" title="Exportar faturas em CSV/XLSX">
                <i class="bi bi-download me-1"></i> Exportar
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary" title="Cadastrar nova fatura">
                <i class="bi bi-plus-lg me-1"></i> Nova Fatura
            </a>
        @endif
    </div>
@endsection

@section('content')
    @php
        $rateStale = collect($rates)->contains(fn ($rate) => $rate['stale'] ?? false);
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $euro = fn ($value) => '€ '.number_format((float) $value, 2, ',', '.');
        $amount = fn ($value, $currency) => ($currency === 'USD' ? 'US$' : ($currency === 'EUR' ? '€' : 'R$')).' '.number_format((float) $value, 2, ',', '.');
        $totalMonthlyCloud = (float) ($infrastructure['monthly_brl'] ?? 0) + (float) ($licenseServices->sum('monthly_brl') ?? 0);
        $totalServicesCount = (int) ($infrastructure['services'] ?? 0) + (int) ($licenseServices->count() ?? 0);
    @endphp

    {{-- Aviso se as cotações estiverem usando fallback offline --}}
    @if ($rateStale)
        <div class="hint-banner mb-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 30%, transparent);">
            <i class="bi bi-wifi-off fs-5"></i>
            <div>
                <strong>Atenção:</strong> A AwesomeAPI está instável no momento. O sistema está utilizando as últimas cotações conhecidas gravadas em cache
                ({{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('d/m/Y H:i') }}). Valores convertidos podem ter variações.
            </div>
        </div>
    @endif

    {{-- 1. Hero Card: Boas-vindas Executiva e Resumo do Dia --}}
    <div class="card mb-3 dashboard-hero-card">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="hero-brand-icon">
                        <i class="bi bi-speedometer2"></i>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h5 fw-bold mb-0">Olá, {{ auth()->user()->name }}</h2>
                            <span class="badge text-bg-light border px-2 py-1" style="font-size: .68rem;">
                                <i class="bi bi-shield-check text-primary me-1"></i>{{ auth()->user()->roleLabel() }}
                            </span>
                        </div>
                        <p class="text-muted-soft mb-0" style="font-size: .82rem;">
                            {{ now()->translatedFormat('l, d \d\e F \d\e Y') }} · Central de Conciliação Financeira Multimoedas & Nuvem
                        </p>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="live-clock d-inline-flex px-3 py-2 border rounded-pill bg-body shadow-xs">
                        <i class="bi bi-clock-history text-primary me-1"></i>
                        <span class="fw-semibold tabular" data-live="rates-at">{{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('H:i') }}</span>
                        <span class="text-muted-soft ms-1" style="font-size: .72rem;">· Câmbio ao vivo</span>
                    </div>

                    @if ($totals['overdue_count'] > 0)
                        <a href="{{ route('invoices.index', ['status' => 'pending']) }}" class="badge text-bg-danger px-3 py-2 text-decoration-none rounded-pill d-inline-flex align-items-center gap-1 shadow-xs animate-pulse">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>{{ $totals['overdue_count'] }} fatura(s) vencida(s)</span>
                        </a>
                    @else
                        <span class="badge text-bg-success px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1 shadow-xs">
                            <i class="bi bi-check2-circle"></i>
                            <span>Faturas em dia</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 2. KPI Cards: Métricas Financeiras Principais --}}
    <div class="stat-grid mb-3">
        {{-- Card 1: Faturas Pendentes --}}
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Pendente a Pagar</span>
                    <span class="stat-icon is-amber"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="stat-value mt-2 text-warning-emphasis" data-live="pending-total">{{ $money($totals['pending_total']) }}</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="stat-meta"><span data-live="pending-count">{{ $totals['pending_count'] }}</span> fatura(s) em aberto</span>
                    @if ($totals['overdue_count'] > 0)
                        <span class="stat-delta is-up"><i class="bi bi-exclamation-triangle"></i> <span data-live="overdue-count">{{ $totals['overdue_count'] }}</span> vencida(s)</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 2: Faturas Vencidas --}}
        <div class="card stat-card {{ $totals['overdue_count'] > 0 ? 'border-danger-subtle' : '' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Vencidas</span>
                    <span class="stat-icon is-red"><i class="bi bi-exclamation-octagon"></i></span>
                </div>
                <div class="stat-value mt-2 {{ $totals['overdue_count'] > 0 ? 'text-danger' : '' }}" data-live="overdue-total">{{ $money($totals['overdue_total']) }}</div>
                <div class="stat-meta mt-2">
                    @if ($totals['overdue_count'] > 0)
                        <span class="text-danger fw-semibold">{{ $totals['overdue_count'] }} fora do prazo</span>
                    @else
                        Nenhuma fatura fora do prazo
                    @endif
                    @if ($nextDueDate)
                        · próx. {{ $nextDueDate->format('d/m') }}
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 3: Pago no Mês --}}
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Pago no Mês</span>
                    <span class="stat-icon is-blue"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="stat-value mt-2 text-primary" data-live="paid-month-total">{{ $money($totals['paid_month_total']) }}</div>
                <div class="stat-meta mt-2">
                    <span class="fw-semibold">{{ $totals['paid_month_count'] }}</span> quitadas em {{ now()->translatedFormat('F') }}
                </div>
            </div>
        </div>

        {{-- Card 4: Câmbio Comercial ao Vivo --}}
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-label">Câmbio Comercial</span>
                    <span class="stat-icon"><i class="bi bi-currency-exchange"></i></span>
                </div>
                @foreach (['USD', 'EUR'] as $currency)
                    @php $rate = $rates[$currency]; @endphp
                    <div class="rate-line">
                        <span class="rate-pair d-flex align-items-center gap-1">
                            <span class="badge text-bg-light border px-1" style="font-size: .68rem;">{{ $currency }}</span>
                            <span class="text-muted-soft" style="font-size: .72rem;">{{ config('finance.currencies.'.$currency.'.symbol') }}</span>
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
                <div class="stat-meta mt-2 d-flex justify-content-between align-items-center">
                    <span>{{ $rateStale ? 'Cache' : 'AwesomeAPI' }} · <span data-live="rates-at">{{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('H:i') }}</span></span>
                    <a href="{{ route('conversions.index') }}" class="text-decoration-none" style="font-size:.72rem;">Conversor <i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Resumo Executivo da Nuvem (TCO Mensal) --}}
    <div class="card mb-3 surface-cloud-banner">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-4 col-md-6 border-end-lg">
                    <div class="d-flex align-items-center gap-3">
                        <div class="cloud-stat-icon">
                            <i class="bi bi-cloud-check-fill text-primary"></i>
                        </div>
                        <div>
                            <span class="text-muted-soft text-uppercase fw-bold" style="font-size: .68rem; letter-spacing: .08em;">Custo Mensal da Nuvem (TCO)</span>
                            <div class="h5 fw-bold mb-0 text-primary tabular">{{ $money($totalMonthlyCloud) }}</div>
                            <span class="text-muted-soft" style="font-size: .72rem;">{{ $totalServicesCount }} serviços e instâncias ativas</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 border-end-lg">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-server text-secondary"></i>
                        <div>
                            <span class="text-muted-soft text-uppercase fw-bold" style="font-size: .66rem;">OVHcloud (Servidores & IPs)</span>
                            <div class="fw-bold tabular" style="font-size: .92rem;">{{ $euro($infrastructure['monthly_eur']) }} <small class="text-muted-soft fw-normal">({{ $money($infrastructure['monthly_brl']) }})</small></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 border-end-lg">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-key text-secondary"></i>
                        <div>
                            <span class="text-muted-soft text-uppercase fw-bold" style="font-size: .66rem;">Licenças & IDrive e2 S3</span>
                            <div class="fw-bold tabular" style="font-size: .92rem;">{{ $money($licenseServices->sum('monthly_brl')) }}</div>
                            <small class="text-muted-soft" style="font-size: .7rem;">LicenseBoy & Console S3</small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6 text-lg-end">
                    <div class="d-flex flex-lg-column justify-content-between align-items-lg-end">
                        <span class="text-muted-soft text-uppercase fw-bold" style="font-size: .66rem;">Tributos Pendentes</span>
                        <span class="fw-bold tabular text-warning-emphasis" data-live="taxes-pending">{{ $money($totals['taxes_pending']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Gráficos Analíticos: Evolução Financeira & Composição --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Evolução Financeira Semestral</div>
                        <div class="text-muted-soft" style="font-size: .76rem;">Histórico de valores pagos e pendentes em BRL nos últimos 6 meses</div>
                    </div>
                    <span class="badge text-bg-light border">BRL · Consolidado</span>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Composição da Carteira</div>
                        <div class="text-muted-soft" style="font-size: .76rem;">{{ array_sum($statusDistribution) }} faturas registradas</div>
                    </div>
                    <span class="badge text-bg-light border">Status</span>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="chart-shell flex-grow-1" style="min-height: 200px;">
                        <canvas id="chart-status" aria-label="Distribuição de faturas por status" role="img"></canvas>
                    </div>
                    <div class="surface-muted p-3 mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted-soft" style="font-size: .74rem;">Encargos & Tributos em Aberto</span>
                            <strong class="tabular" data-live="taxes-pending">{{ $money($totals['taxes_pending']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted-soft" style="font-size: .72rem;">IOF + Spread + ICMS + PIS/COFINS</span>
                            <a href="{{ route('invoices.index', ['status' => 'pending']) }}" class="fw-semibold text-decoration-none" style="font-size: .74rem;">
                                Ver faturas <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Seções em Abas Executivas: Faturas, Licenças/S3 e Infraestrutura OVH --}}
    <div class="card dashboard-tabs-card mb-3">
        <div class="card-header border-bottom p-2 p-md-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <ul class="nav nav-pills kdk-tabs dashboard-pill-tabs flex-nowrap overflow-x-auto" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-invoices-btn" data-bs-toggle="pill" data-bs-target="#tab-invoices" type="button" role="tab" aria-controls="tab-invoices" aria-selected="true">
                            <i class="bi bi-receipt me-1"></i> Faturas & Vencimentos
                            <span class="badge rounded-pill ms-1 {{ $totals['overdue_count'] > 0 ? 'text-bg-danger' : 'text-bg-secondary' }}">
                                {{ $recentInvoices->count() }}
                            </span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-licenses-btn" data-bs-toggle="pill" data-bs-target="#tab-licenses" type="button" role="tab" aria-controls="tab-licenses" aria-selected="false">
                            <i class="bi bi-key me-1"></i> Licenças & S3 Storage
                            <span class="badge rounded-pill ms-1 text-bg-secondary">
                                {{ $licenseServices->count() }}
                            </span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-infra-btn" data-bs-toggle="pill" data-bs-target="#tab-infra" type="button" role="tab" aria-controls="tab-infra" aria-selected="false">
                            <i class="bi bi-hdd-network me-1"></i> Infraestrutura OVHcloud
                            <span class="badge rounded-pill ms-1 text-bg-secondary">
                                {{ $infrastructure['services'] }}
                            </span>
                        </button>
                    </li>
                </ul>

                <div class="d-none d-lg-flex align-items-center gap-2">
                    <span class="text-muted-soft" style="font-size: .76rem;">Alternância rápida sem refresh</span>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="dashboardTabsContent">

                {{-- ==================== ABA 1: FATURAS & VENCIMENTOS ==================== --}}
                <div class="tab-pane fade show active p-3 p-md-4" id="tab-invoices" role="tabpanel" aria-labelledby="tab-invoices-btn">
                    <div class="row g-3">
                        {{-- Tabela de Faturas Recentes --}}
                        <div class="col-xl-8">
                            <div class="card border h-100 shadow-none">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">Faturas Recentes</div>
                                        <div class="text-muted-soft" style="font-size: .74rem;">Atualização automática quando o câmbio oscila</div>
                                    </div>
                                    <a href="{{ route('invoices.index') }}" class="btn btn-ghost btn-sm">Ver todas <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 invoices-table">
                                        <thead>
                                            <tr>
                                                <th>Fatura / Descrição</th>
                                                <th>Status</th>
                                                <th>Vencimento</th>
                                                <th class="text-end">Total (BRL)</th>
                                                <th class="text-end">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($recentInvoices as $invoice)
                                                <tr data-invoice-row="{{ $invoice->id }}">
                                                    <td class="invoice-title-cell">
                                                        <a href="{{ route('invoices.show', $invoice) }}" class="cell-title d-block text-decoration-none">
                                                            {{ \Illuminate\Support\Str::limit($invoice->description, 38) }}
                                                        </a>
                                                        <span class="cell-sub">
                                                            {{ $invoice->external_reference ?: 'Manual' }} ·
                                                            {{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}
                                                        </span>
                                                        <div class="invoice-mobile-meta d-md-none">
                                                            <span class="{{ $invoice->statusBadgeClass() }} d-inline-block">{{ $invoice->statusLabel() }}</span>
                                                            <span class="tabular {{ $invoice->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                                                                Vence em {{ $invoice->due_date?->format('d/m/Y') }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <span class="{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span>
                                                    </td>
                                                    <td class="tabular d-none d-md-table-cell {{ $invoice->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                                                        {{ $invoice->due_date?->format('d/m/Y') }}
                                                    </td>
                                                    <td class="text-end tabular fw-semibold" data-invoice-total="{{ $invoice->id }}">
                                                        {{ $money($invoice->total_brl) }}
                                                        @if ($invoice->rate_locked)
                                                            <i class="bi bi-lock-fill ms-1 text-muted-soft" title="Câmbio travado" style="font-size: .7rem;"></i>
                                                        @endif
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost btn-sm" title="Visualizar detalhes">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5">
                                                        <div class="empty-state py-4 text-center">
                                                            <div class="icon fs-3 text-muted-soft mb-2"><i class="bi bi-receipt"></i></div>
                                                            <h5 class="h6 fw-semibold">Nenhuma fatura cadastrada</h5>
                                                            <p class="text-muted-soft mb-2" style="font-size: .8rem;">Importe da OVHcloud ou cadastre a primeira manualmente.</p>
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

                        {{-- Card Lateral: Vencimentos Críticos & Próximos --}}
                        <div class="col-xl-4">
                            <div class="card border h-100 shadow-none">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">Alertas de Vencimento</div>
                                        <div class="text-muted-soft" style="font-size: .72rem;">Janela de {{ $dueWindowDays ?? 7 }} dias e atrasadas</div>
                                    </div>
                                    <span class="badge text-bg-light border">{{ ($upcomingInvoices ?? $overdueInvoices)->count() }}</span>
                                </div>
                                <div class="card-body p-3">
                                    @forelse (($upcomingInvoices ?? $overdueInvoices) as $invoice)
                                        @php
                                            $daysDiff = $invoice->due_date ? (int) round(now()->startOfDay()->diffInDays($invoice->due_date->copy()->startOfDay(), false)) : 0;
                                            $isOverdue = $daysDiff < 0;
                                            $isToday = $daysDiff === 0;
                                        @endphp
                                        <a href="{{ route('invoices.show', $invoice) }}" class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom text-reset text-decoration-none hover-highlight rounded px-2">
                                            <div>
                                                <span class="d-block fw-semibold text-truncate" style="font-size: .84rem; max-width: 190px;">
                                                    {{ $invoice->description }}
                                                </span>
                                                <span style="font-size: .72rem;">
                                                    @if ($isOverdue)
                                                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Venceu há {{ abs($daysDiff) }}d ({{ $invoice->due_date?->format('d/m') }})</span>
                                                    @elseif ($isToday)
                                                        <span class="text-warning-emphasis fw-semibold"><i class="bi bi-clock me-1"></i>Vence HOJE!</span>
                                                    @else
                                                        <span class="text-muted-soft">Vence em {{ $daysDiff }}d · {{ $invoice->due_date?->format('d/m') }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                            <span class="tabular fw-semibold {{ $isOverdue ? 'text-danger' : ($isToday ? 'text-warning-emphasis' : '') }}" style="font-size: .82rem;" data-invoice-total="{{ $invoice->id }}">
                                                {{ $money($invoice->total_brl) }}
                                            </span>
                                        </a>
                                    @empty
                                        <div class="empty-state py-4 text-center">
                                            <div class="icon fs-3 text-success mb-2"><i class="bi bi-shield-check"></i></div>
                                            <h5 class="h6 fw-semibold">Tudo em dia</h5>
                                            <p class="text-muted-soft mb-0" style="font-size: .78rem;">Nenhuma fatura pendente com vencimento próximo ou vencida.</p>
                                        </div>
                                    @endforelse

                                    <div class="surface-muted p-3 mt-3 rounded">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi bi-bell text-primary"></i>
                                            <span class="fw-semibold" style="font-size: .78rem;">Notificações automáticas ativas</span>
                                        </div>
                                        <p class="mb-0 text-muted-soft" style="font-size: .72rem; line-height: 1.45;">
                                            Disparadas por e-mail e na barra do painel na criação, {{ $dueWindowDays ?? 7 }} dias antes, no dia do vencimento e em risco de suspensão.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ==================== ABA 2: LICENÇAS & S3 STORAGE ==================== --}}
                <div class="tab-pane fade p-3 p-md-4" id="tab-licenses" role="tabpanel" aria-labelledby="tab-licenses-btn">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">Assinaturas de Software & Armazenamento em Nuvem S3</div>
                            <div class="text-muted-soft" style="font-size: .76rem;">
                                Gestão comparativa de valor de tabela vs. valor real cobrado no console (LicenseBoy & IDrive e2)
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('s3.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-cloud-arrow-up me-1 text-primary"></i> Console IDrive e2 (S3)
                            </a>
                            <a href="{{ route('licenses.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-key me-1 text-secondary"></i> Painel LicenseBoy
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0 licenses-table">
                            <thead>
                                <tr>
                                    <th>Serviço / Produto</th>
                                    <th>Provedor</th>
                                    <th class="d-none d-md-table-cell">Ciclo</th>
                                    <th class="text-end d-none d-sm-table-cell">Cobrado no Console</th>
                                    <th class="text-end">Mensal (BRL)</th>
                                    <th>Próximo Vencimento</th>
                                    <th class="d-none d-sm-table-cell">Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($licenseServices as $item)
                                    @php
                                        $lic = $item['service'];
                                        $isS3 = str_contains(mb_strtolower((string) $lic->provider), 'idrive');
                                        $showRoute = $isS3 ? route('s3.show', $lic) : route('licenses.show', $lic);
                                    @endphp
                                    <tr>
                                        <td class="license-title-cell">
                                            <a href="{{ $showRoute }}" class="cell-title d-block text-decoration-none">{{ $lic->name }}</a>
                                            <span class="cell-sub">
                                                {{ $lic->product }}
                                                @if ($lic->server_name)
                                                    · <span class="mono">{{ $lic->server_name }}</span>
                                                @endif
                                            </span>
                                            <div class="license-mobile-meta d-md-none">
                                                <span class="badge text-bg-light border">{{ $lic->provider }}</span>
                                                <span>{{ $lic->billingCycleLabel() }}</span>
                                                @if ($lic->usesPanelPrice())
                                                    <span class="text-danger fw-semibold">
                                                        Cobrado: {{ $lic->billingCurrencySymbol() }} {{ number_format($lic->billingUnitPrice(), 2, ',', '.') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $isS3 ? 'text-bg-primary-subtle border-primary-subtle text-primary' : 'text-bg-light border' }}">
                                                <i class="bi {{ $isS3 ? 'bi-cloud-arrow-up' : 'bi-key' }} me-1"></i>{{ $lic->provider }}
                                            </span>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <span class="badge text-bg-light border">{{ $lic->billingCycleLabel() }}</span>
                                        </td>
                                        <td class="text-end tabular d-none d-sm-table-cell">
                                            {{ $lic->billingCurrencySymbol() }} {{ number_format($lic->totalOriginal(), 2, ',', '.') }}
                                            @if ($lic->usesPanelPrice())
                                                <span class="cell-sub d-block">
                                                    Console {{ $lic->billingCurrencySymbol() }} {{ number_format($lic->billingUnitPrice(), 2, ',', '.') }}
                                                    @if ($lic->panelDivergencePercent() !== null && $lic->panelDivergencePercent() > 0 && $lic->catalogItem)
                                                        <span class="text-danger fw-semibold">· tabela {{ $lic->currencySymbol() }} {{ number_format($lic->catalogItem->price, 2, ',', '.') }}</span>
                                                    @endif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end tabular fw-semibold">{{ $money($item['monthly_brl']) }}</td>
                                        <td class="tabular">
                                            @if ($lic->next_due_date)
                                                <span class="{{ $lic->isDueSoon() ? 'text-danger fw-semibold' : '' }}">{{ $lic->next_due_date->format('d/m/Y') }}</span>
                                                @if ($lic->isDueSoon())
                                                    <span class="cell-sub d-block text-danger">Renova em breve</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="d-none d-sm-table-cell">
                                            <span class="{{ $lic->statusBadgeClass() }}">{{ $lic->statusLabel() }}</span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ $showRoute }}" class="btn btn-ghost btn-sm" title="Ver detalhes">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state py-4 text-center">
                                                <div class="icon fs-3 text-muted-soft mb-2"><i class="bi bi-key"></i></div>
                                                <h5 class="h6 fw-semibold">Nenhuma licença ou armazenamento S3 cadastrado</h5>
                                                <p class="text-muted-soft mb-2" style="font-size: .8rem;">Cadastre licenças cPanel/WHM ou conecte o console IDrive e2 S3.</p>
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="{{ route('s3.create') }}" class="btn btn-primary btn-sm">Novo S3 Storage</a>
                                                    <a href="{{ route('licenses.create') }}" class="btn btn-outline-secondary btn-sm">Nova Licença</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($licenseServices->isNotEmpty())
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="4" class="fw-semibold">Custo Mensal Estimado (Licenças + S3)</td>
                                        <td class="text-end tabular fw-bold text-primary">{{ $money($licenseServices->sum('monthly_brl')) }}</td>
                                        <td colspan="3" class="text-muted-soft" style="font-size: .74rem;">Convertido com cotações e tributos vigentes</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>

                {{-- ==================== ABA 3: INFRAESTRUTURA OVHCLOUD ==================== --}}
                <div class="tab-pane fade p-3 p-md-4" id="tab-infra" role="tabpanel" aria-labelledby="tab-infra-btn">
                    <div class="row g-3">
                        {{-- Servidores & Serviços Dedicados/VPS --}}
                        <div class="col-xl-8">
                            <div class="card border h-100 shadow-none">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">Serviços e Servidores OVHcloud</div>
                                        <div class="text-muted-soft" style="font-size: .74rem;">
                                            {{ $infrastructure['services'] }} ativo(s) · Custo mensal: {{ $euro($infrastructure['monthly_eur']) }} ({{ $money($infrastructure['monthly_brl']) }})
                                        </div>
                                    </div>
                                    <a href="{{ route('infrastructure.index') }}" class="btn btn-ghost btn-sm">Gerenciar infra <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 infra-services-table">
                                        <thead>
                                            <tr>
                                                <th>Servidor / Identificador</th>
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
                                                            <span class="badge text-bg-light border">{{ $service->typeLabel() }}</span>
                                                            <span>Renova em {{ $service->renewalLabel() }}</span>
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
                                                        <div class="empty-state py-4 text-center">
                                                            <div class="icon fs-3 text-muted-soft mb-2"><i class="bi bi-hdd-stack"></i></div>
                                                            <h5 class="h6 fw-semibold">Nenhum servidor OVHcloud importado</h5>
                                                            <p class="text-muted-soft mb-2" style="font-size: .8rem;">Configure as credenciais AK/AS/CK da API OVHcloud em Configurações.</p>
                                                            @if (auth()->user()->isDeveloperAdmin())
                                                                <a href="{{ route('settings.index', ['tab' => 'ovh']) }}" class="btn btn-primary btn-sm">Configurar OVHcloud</a>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if ($services->isNotEmpty())
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td colspan="3" class="fw-semibold">Total Mensal OVHcloud</td>
                                                    <td class="text-end tabular fw-semibold d-none d-sm-table-cell">{{ $euro($infrastructure['monthly_eur']) }}</td>
                                                    <td class="text-end tabular fw-bold text-primary">{{ $money($infrastructure['monthly_brl']) }}</td>
                                                    <td class="text-muted-soft d-none d-md-table-cell" style="font-size: .74rem;">EUR {{ number_format($eurRate, 4, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- Endereços IP --}}
                        <div class="col-xl-4">
                            <div class="card border h-100 shadow-none">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold">Blocos de IPs Alocados</div>
                                        <div class="text-muted-soft" style="font-size: .72rem;">IPv4 e IPv6 adicionais</div>
                                    </div>
                                    <span class="badge text-bg-light border">{{ $infrastructure['ips'] }} bloco(s)</span>
                                </div>
                                <div class="card-body p-3">
                                    @forelse ($ips as $ip)
                                        <div class="py-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <span class="mono fw-semibold" style="font-size: .82rem;">{{ $ip->block }}</span>
                                                <span class="badge text-bg-light border">{{ $ip->versionLabel() }} · {{ $ip->typeLabel() }}</span>
                                            </div>
                                            <div class="text-muted-soft mt-1" style="font-size: .72rem;">
                                                {{ $ip->routed_to ?: 'Sem servidor vinculado' }}
                                                @if ($ip->primaryReverse())
                                                    · <span class="mono">{{ $ip->primaryReverse() }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="empty-state py-4 text-center">
                                            <div class="icon fs-3 text-muted-soft mb-2"><i class="bi bi-diagram-3"></i></div>
                                            <h5 class="h6 fw-semibold">Nenhum IP registrado</h5>
                                            <p class="text-muted-soft mb-0" style="font-size: .78rem;">Sincronize com a OVHcloud para listar os blocos de IP.</p>
                                        </div>
                                    @endforelse

                                    <a href="{{ route('infrastructure.index') }}" class="btn btn-outline-secondary w-100 mt-3 btn-sm">
                                        <i class="bi bi-hdd-network me-1"></i> Abrir Mapa de Infraestrutura
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Dados Serializados em JSON para Inicialização do Chart.js --}}
    <script type="application/json" id="dashboard-data">{!! json_encode([
        'series' => $series,
        'distribution' => $statusDistribution,
        'totals' => $totals,
        'rates' => $rates,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

    @push('styles')
        <style>
            .dashboard-hero-card {
                background: linear-gradient(135deg, color-mix(in srgb, var(--kdk-card) 98%, transparent), color-mix(in srgb, var(--kdk-card-muted) 90%, transparent));
                border: 1px solid var(--kdk-border);
            }
            .hero-brand-icon {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: linear-gradient(135deg, var(--kdk-teal-soft), color-mix(in srgb, var(--kdk-teal) 22%, transparent));
                color: var(--kdk-teal-600);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.4rem;
                flex-shrink: 0;
            }
            .surface-cloud-banner {
                background: var(--kdk-card-muted);
                border: 1px solid var(--kdk-border);
                border-radius: var(--kdk-radius);
            }
            .cloud-stat-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                background: var(--kdk-card);
                border: 1px solid var(--kdk-border);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                flex-shrink: 0;
            }
            @media (min-width: 992px) {
                .border-end-lg {
                    border-right: 1px solid var(--kdk-border);
                }
            }
            .dashboard-tabs-card .card-header {
                background: var(--kdk-card-muted);
            }
            .dashboard-pill-tabs .nav-link {
                border-radius: 10px;
                font-size: .84rem;
                font-weight: 600;
                padding: 8px 14px;
                transition: all 180ms ease;
            }
            .dashboard-pill-tabs .nav-link.active {
                background: var(--kdk-teal);
                color: #04211a !important;
                box-shadow: 0 4px 12px -4px rgba(15, 157, 118, 0.6);
            }
            .dashboard-pill-tabs .nav-link.active .badge {
                background-color: rgba(4, 33, 26, 0.25) !important;
                color: #04211a !important;
            }
            .hover-highlight:hover {
                background-color: var(--kdk-card-muted);
            }
            .shadow-xs {
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            @keyframes pulse-subtle {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.88; transform: scale(1.02); }
            }
            .animate-pulse {
                animation: pulse-subtle 2.4s infinite ease-in-out;
            }
        </style>
    @endpush

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            (function () {
                // 1. Memoriza a aba selecionada no localStorage para navegação persistente e fluida
                const storageKey = 'kdk_active_dashboard_tab';
                const tabButtons = document.querySelectorAll('#dashboardTabs button[data-bs-toggle="pill"]');
                
                if (tabButtons.length > 0) {
                    const savedTabId = localStorage.getItem(storageKey);
                    if (savedTabId) {
                        const targetBtn = document.querySelector(`#dashboardTabs button[data-bs-target="${savedTabId}"]`);
                        if (targetBtn && window.bootstrap && window.bootstrap.Tab) {
                            const tab = new window.bootstrap.Tab(targetBtn);
                            tab.show();
                        }
                    }

                    tabButtons.forEach(btn => {
                        btn.addEventListener('shown.bs.tab', (event) => {
                            const target = event.target.getAttribute('data-bs-target');
                            if (target) {
                                localStorage.setItem(storageKey, target);
                            }
                        });
                    });
                }

                // 2. Atualização de Cotações AJAX sem reload de página
                const form = document.querySelector('form[data-ajax-refresh-rates]');
                if (!form) {
                    return;
                }

                const decimal = (value, digits = 2) => Number(value || 0).toLocaleString('pt-BR', {
                    minimumFractionDigits: digits,
                    maximumFractionDigits: digits,
                });

                const shortTime = new Intl.DateTimeFormat('pt-BR', {
                    timeZone: 'America/Sao_Paulo',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                });

                form.addEventListener('submit', async (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }
                    event.preventDefault();

                    const button = form.querySelector('[data-refresh-rates-btn]') || form.querySelector('button[type="submit"]');
                    const originalHtml = button ? button.innerHTML : '';
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    if (button) {
                        button.disabled = true;
                        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Atualizando...';
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        const data = await response.json();

                        if (data && data.rates) {
                            Object.entries(data.rates).forEach(([currency, rate]) => {
                                document.querySelectorAll(`[data-live="rate-${currency}"]`).forEach((el) => {
                                    el.textContent = decimal(rate.rate, 4);
                                });
                                document.querySelectorAll(`[data-live="rate-${currency}-pct"]`).forEach((el) => {
                                    el.textContent = `${decimal(Math.abs(rate.pct_change || 0), 2)}%`;
                                });
                                document.querySelectorAll(`[data-live="rate-${currency}-change"]`).forEach((el) => {
                                    const up = Number(rate.pct_change || 0) >= 0;
                                    el.classList.toggle('is-up', up);
                                    el.classList.toggle('is-down', !up);
                                    const icon = el.querySelector('i');
                                    if (icon) {
                                        icon.className = up ? 'bi bi-arrow-up-right' : 'bi bi-arrow-down-right';
                                    }
                                });
                            });

                            const latest = Object.values(data.rates).find((r) => r.updated_at);
                            if (latest && latest.updated_at) {
                                document.querySelectorAll('[data-live="rates-at"]').forEach((el) => {
                                    el.textContent = shortTime.format(new Date(latest.updated_at));
                                });
                            }
                        }

                        // Dispara evento global para o live.js recalcular snapshots e tabelas dinâmicas
                        document.dispatchEvent(new CustomEvent('finance:refresh-snapshot'));

                        if (window.toastr) {
                            if (data.stale) {
                                window.toastr.warning(data.message || 'API indisponível: mantidas as últimas cotações conhecidas.', 'Atenção');
                            } else if (response.ok) {
                                window.toastr.success(data.message || 'Cotações atualizadas em tempo real.', 'Tudo Certo');
                            } else {
                                window.toastr.error(data.message || 'Não foi possível atualizar as cotações.', 'Erro');
                            }
                        }
                    } catch (error) {
                        if (window.toastr) {
                            window.toastr.error('Falha de conexão com o servidor ao atualizar cotações.', 'Falha na Atualização');
                        }
                    } finally {
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = originalHtml;
                        }
                    }
                });
            })();
        </script>
    @endpush
@endsection
