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

@section('title', $invoice->description)
@section('eyebrow', 'Fatura '.($invoice->external_reference ?: '#'.$invoice->id))
@section('subtitle', 'Detalhamento do câmbio, tributos e histórico de conciliação.')

@section('actions')
    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
    @endif
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $taxTotal = $invoice->taxesTotal();
        $vetBrl = (float) $invoice->amount_original > 0
            ? (float) $invoice->total_brl / (float) $invoice->amount_original
            : 0.0;
    @endphp

    @if ($invoice->isOverdue())
        <div class="hint-banner mb-3" style="background: var(--kdk-red-soft); border-color: color-mix(in srgb, var(--kdk-red) 30%, transparent);">
            <i class="bi bi-exclamation-octagon"></i>
            <div>Esta fatura venceu em {{ $invoice->due_date->format('d/m/Y') }} e continua pendente.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <span class="{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span>
                            @if ($invoice->rate_locked)
                                <span class="currency-chip ms-1"><i class="bi bi-lock-fill"></i> Câmbio travado</span>
                            @endif
                            @if ($invoice->licenseService)
                                <a href="{{ route('licenses.show', $invoice->licenseService) }}" class="currency-chip ms-1 text-decoration-none">
                                    <i class="bi bi-key"></i> {{ $invoice->licenseService->product }}
                                </a>
                            @endif
                            <h2 class="mt-3 mb-1" style="font-size:1.25rem;">{{ $invoice->description }}</h2>
                            <div class="text-muted-soft" style="font-size:.8rem;">
                                {{ $invoice->external_reference ? 'Referência '.$invoice->external_reference : 'Cadastro manual' }}
                                · Vence em {{ $invoice->due_date?->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="stat-label">Total em BRL</div>
                            <div class="amount-hero mt-1">{{ $money($invoice->total_brl) }}</div>
                            <div class="text-muted-soft mt-1" style="font-size:.74rem;">
                                {{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}
                                × {{ number_format((float) $invoice->exchange_rate, 4, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <div class="kpi-inline mt-4 pt-3 border-top">
                        <div class="kpi">
                            <div class="kpi-label">Valor original</div>
                            <div class="kpi-value">{{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Cotação aplicada</div>
                            <div class="kpi-value">{{ number_format((float) $invoice->exchange_rate, 4, ',', '.') }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Tributos</div>
                            <div class="kpi-value">{{ $money($taxTotal) }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">VET · R$ por {{ $invoice->currency }}</div>
                            <div class="kpi-value">R$ {{ number_format($vetBrl, 4, ',', '.') }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Cotação atual</div>
                            <div class="kpi-value">
                                {{ number_format((float) ($rates[$invoice->currency]['rate'] ?? 0), 4, ',', '.') }}
                                @if ($invoice->rate_locked)
                                    <i class="bi bi-lock-fill text-muted-soft ms-1" style="font-size:.7rem;"></i>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Composição do valor</div>
                <div class="card-body">
                    <table class="breakdown">
                        <tr>
                            <td>Valor original ({{ $invoice->currency }})</td>
                            <td class="value">{{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td>Conversão pela cotação {{ number_format((float) $invoice->exchange_rate, 4, ',', '.') }}</td>
                            <td class="value">{{ $money((float) $invoice->amount_original * (float) $invoice->exchange_rate) }}</td>
                        </tr>
                        <tr>
                            <td>Spread</td>
                            <td class="value">{{ $money($invoice->spread_amount) }}</td>
                        </tr>
                        <tr>
                            <td>IOF</td>
                            <td class="value">{{ $money($invoice->iof_amount) }}</td>
                        </tr>
                        <tr>
                            <td>ICMS</td>
                            <td class="value">{{ $money($invoice->icms_amount) }}</td>
                        </tr>
                        <tr>
                            <td>Outros tributos</td>
                            <td class="value">{{ $money($invoice->other_taxes_amount) }}</td>
                        </tr>
                        <tr class="total">
                            <td>Total em BRL</td>
                            <td class="value">{{ $money($invoice->total_brl) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($invoice->items->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Itens da fatura</div>
                            <div class="text-muted-soft" style="font-size:.76rem;">Valores individuais importados da OVHcloud</div>
                        </div>
                        <span class="badge text-bg-light border">{{ $invoice->items->count() }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Descrição</th>
                                    <th>Período</th>
                                    <th class="text-end">Qtd.</th>
                                    <th class="text-end">Unitário</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items->sortBy('description') as $item)
                                    @php
                                        $itemSymbol = $item->currency === 'BRL' ? 'R$' : ($item->currency === 'USD' ? 'US$' : '€');
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="cell-title d-block">{{ $item->description }}</span>
                                            @if ($item->domain)
                                                <span class="cell-sub mono">{{ $item->domain }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted-soft" style="font-size:.76rem;">{{ $item->periodLabel() }}</td>
                                        <td class="text-end tabular">{{ $item->quantityLabel() }}</td>
                                        <td class="text-end tabular">{{ $itemSymbol }} {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                                        <td class="text-end tabular fw-semibold">{{ $itemSymbol }} {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end fw-semibold">Total dos itens</td>
                                    <td class="text-end tabular fw-semibold">
                                        {{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->items->sum('total_price'), 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Histórico</div>
                <div class="card-body">
                    <ul class="timeline-list">
                        <li>
                            Fatura registrada
                            <span class="when">{{ $invoice->created_at?->format('d/m/Y H:i') }}</span>
                        </li>
                        @if ($invoice->calculated_at)
                            <li>
                                Valores calculados com a cotação {{ number_format((float) $invoice->exchange_rate, 4, ',', '.') }}
                                <span class="when">{{ $invoice->calculated_at->format('d/m/Y H:i') }}</span>
                            </li>
                        @endif
                        @if ($invoice->paid_at)
                            <li>
                                Pagamento confirmado — câmbio travado
                                <span class="when">{{ $invoice->paid_at->format('d/m/Y H:i') }}</span>
                            </li>
                        @endif
                        @if ($invoice->status === \App\Models\Invoice::STATUS_CANCELED)
                            <li>
                                Fatura cancelada
                                <span class="when">{{ $invoice->updated_at?->format('d/m/Y H:i') }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Ações</div>
                <div class="card-body d-grid gap-2">
                    @if (auth()->user()->canManageFinance())
                        @if ($invoice->status === \App\Models\Invoice::STATUS_PENDING)
                            <form method="POST" action="{{ route('invoices.pay', $invoice) }}" data-confirm="Confirmar o pagamento e travar a cotação desta fatura?" data-confirm-button="Marcar como paga">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i> Marcar como paga</button>
                            </form>

                            @if ($invoice->external_reference)
                                <form method="POST" action="{{ route('invoices.sync-payment', $invoice) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-cloud-check me-1"></i> Verificar pagamento na OVHcloud</button>
                                </form>
                            @endif
                        @endif

                        @if (! $invoice->rate_locked)
                            <form method="POST" action="{{ route('invoices.recalculate', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-calculator me-1"></i> Recalcular com a cotação atual</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('invoices.lock', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">
                                <i class="bi {{ $invoice->rate_locked ? 'bi-unlock' : 'bi-lock' }} me-1"></i>
                                {{ $invoice->rate_locked ? 'Destravar câmbio' : 'Travar câmbio' }}
                            </button>
                        </form>

                        @if ($invoice->status !== \App\Models\Invoice::STATUS_CANCELED)
                            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" data-confirm="Cancelar esta fatura? Ela deixa de compor os totais pendentes." data-confirm-button="Cancelar fatura" data-confirm-variant="danger">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100 text-danger"><i class="bi bi-x-circle me-1"></i> Cancelar fatura</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('invoices.reopen', $invoice) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir como pendente</button>
                            </form>
                        @endif
                    @else
                        <div class="text-muted-soft" style="font-size:.82rem;">Seu perfil permite apenas consulta. Ações de conciliação estão disponíveis para gestores financeiros.</div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Origem</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Referência</span>
                        <span class="fw-semibold">{{ $invoice->external_reference ?: '—' }}</span>
                    </div>
                    @if ($invoice->licenseService)
                        <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                            <span class="text-muted-soft">Licença</span>
                            <a href="{{ route('licenses.show', $invoice->licenseService) }}" class="fw-semibold">{{ $invoice->licenseService->name }}</a>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Moeda</span>
                        <span class="fw-semibold">{{ $invoice->currency }} · {{ config('finance.currencies.'.$invoice->currency.'.label') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Câmbio</span>
                        <span class="fw-semibold">{{ $invoice->rate_locked ? 'Travado' : 'Automático' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Atualizado</span>
                        <span class="fw-semibold">{{ $invoice->updated_at?->format('d/m/Y H:i') }}</span>
                    </div>

                    @if ($invoice->external_reference)
                        <a href="https://www.ovh.com/manager/#/billing/bills" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Abrir na OVHcloud
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
