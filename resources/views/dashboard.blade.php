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

@section('title', 'Visão geral')
@section('eyebrow', 'Dashboard')
@section('subtitle', 'Cotações, tributos e conciliação das faturas internacionais em um só lugar · '.now()->translatedFormat('d \d\e F \d\e Y'))

@section('actions')
    @if (auth()->user()->isDeveloperAdmin())
        <form method="POST" action="{{ route('settings.rates.refresh') }}" data-ajax-refresh-rates>
            @csrf
            <button type="submit" class="btn btn-outline-secondary" data-refresh-rates-btn>
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
                    <div>
                        <div class="fw-semibold">Faturas em aberto · Vencimento próximo</div>
                        <div class="text-muted-soft" style="font-size:.72rem;">Vencidas, vencendo hoje e próximos {{ $dueWindowDays ?? 7 }} dias</div>
                    </div>
                    <span class="badge text-bg-light border">{{ ($upcomingInvoices ?? $overdueInvoices)->count() }}</span>
                </div>
                <div class="card-body">
                    @forelse (($upcomingInvoices ?? $overdueInvoices) as $invoice)
                        @php
                            $daysDiff = $invoice->due_date ? (int) round(now()->startOfDay()->diffInDays($invoice->due_date->copy()->startOfDay(), false)) : 0;
                            $isOverdue = $daysDiff < 0;
                            $isToday = $daysDiff === 0;
                        @endphp
                        <a href="{{ route('invoices.show', $invoice) }}" class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom text-reset">
                            <span>
                                <span class="d-block fw-semibold" style="font-size:.84rem;">{{ \Illuminate\Support\Str::limit($invoice->description, 34) }}</span>
                                <span class="text-muted-soft" style="font-size:.72rem;">
                                    @if ($isOverdue)
                                        <span class="text-danger fw-semibold">Venceu há {{ abs($daysDiff) }} dia(s) ({{ $invoice->due_date?->format('d/m/Y') }})</span>
                                    @elseif ($isToday)
                                        <span class="text-warning fw-semibold">Vence HOJE ({{ $invoice->due_date?->format('d/m/Y') }})</span>
                                    @else
                                        Vence em {{ $daysDiff }} dia(s) · {{ $invoice->due_date?->format('d/m/Y') }}
                                    @endif
                                </span>
                            </span>
                            <span class="tabular fw-semibold {{ $isOverdue ? 'text-danger' : ($isToday ? 'text-warning' : '') }}" style="font-size:.82rem;" data-invoice-total="{{ $invoice->id }}">{{ $money($invoice->total_brl) }}</span>
                        </a>
                    @empty
                        <div class="empty-state py-4">
                            <div class="icon"><i class="bi bi-shield-check"></i></div>
                            <h3>Tudo em dia</h3>
                            <p class="mb-0">Nenhuma fatura em aberto com vencimento próximo ou em atraso.</p>
                        </div>
                    @endforelse

                    <div class="surface-muted p-3 mt-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-bell text-primary"></i>
                            <span class="fw-semibold" style="font-size:.8rem;">Alertas automáticos ativos</span>
                        </div>
                        <p class="mb-0 text-muted-soft" style="font-size:.74rem;line-height:1.5;">
                            Notificações enviadas na criação da fatura, {{ $dueWindowDays ?? 7 }} dia(s) antes do vencimento, no dia do vencimento e em risco de suspensão.
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

    @if (isset($licenseServices) && $licenseServices->isNotEmpty())
        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold">Licenças (LicenseBoy) & Armazenamento S3 (IDrive e2)</div>
                            <div class="text-muted-soft" style="font-size:.76rem;">
                                {{ $licenseServices->count() }} serviço(s) registrado(s) · custo mensal estimado {{ $money($licenseServices->sum('monthly_brl')) }}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('s3.index') }}" class="btn btn-ghost btn-sm">
                                <i class="bi bi-cloud-arrow-up me-1"></i> IDrive e2 (S3)
                            </a>
                            <a href="{{ route('licenses.index') }}" class="btn btn-ghost btn-sm">
                                <i class="bi bi-key me-1"></i> Licenças <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Serviço / Produto</th>
                                    <th class="d-none d-md-table-cell">Fornecedor</th>
                                    <th class="d-none d-md-table-cell">Ciclo</th>
                                    <th class="text-end d-none d-sm-table-cell">Valor do ciclo</th>
                                    <th class="text-end">Mensal (BRL)</th>
                                    <th>Próximo vencimento</th>
                                    <th class="d-none d-sm-table-cell">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($licenseServices as $item)
                                    @php
                                        $lic = $item['service'];
                                        $isS3 = str_contains(mb_strtolower((string) $lic->provider), 'idrive');
                                        $showRoute = $isS3 ? route('s3.show', $lic) : route('licenses.show', $lic);
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ $showRoute }}" class="cell-title d-block">{{ $lic->name }}</a>
                                            <span class="cell-sub">{{ $lic->product }} @if ($lic->server_name) · <span class="mono">{{ $lic->server_name }}</span> @endif</span>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <span class="badge text-bg-light border">
                                                <i class="bi {{ $isS3 ? 'bi-cloud-arrow-up' : 'bi-key' }} me-1"></i>{{ $lic->provider }}
                                            </span>
                                        </td>
                                        <td class="d-none d-md-table-cell">{{ $lic->billingCycleLabel() }}</td>
                                        <td class="text-end tabular d-none d-sm-table-cell">{{ $lic->billingCurrencySymbol() }} {{ number_format($lic->totalOriginal(), 2, ',', '.') }}</td>
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
                                        <td class="d-none d-sm-table-cell"><span class="{{ $lic->statusBadgeClass() }}">{{ $lic->statusLabel() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script type="application/json" id="dashboard-data">{!! json_encode([
        'series' => $series,
        'distribution' => $statusDistribution,
        'totals' => $totals,
        'rates' => $rates,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            (function () {
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

                        // Dispara o evento para o live.js atualizar todos os totais e faturas sem refresh
                        document.dispatchEvent(new CustomEvent('finance:refresh-snapshot'));

                        if (window.toastr) {
                            if (data.stale) {
                                window.toastr.warning(data.message || 'API indisponível: mantidas as últimas cotações conhecidas.', 'Atenção');
                            } else if (response.ok) {
                                window.toastr.success(data.message || 'Cotações atualizadas com sucesso.', 'Tudo certo');
                            } else {
                                window.toastr.error(data.message || 'Não foi possível atualizar as cotações.', 'Erro');
                            }
                        }
                    } catch (error) {
                        if (window.toastr) {
                            window.toastr.error('Não foi possível contatar o servidor para atualizar as cotações.', 'Falha na atualização');
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
