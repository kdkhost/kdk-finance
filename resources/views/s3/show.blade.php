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

@section('title', $license->name)
@section('eyebrow', 'Armazenamento S3 · '.$license->provider)
@section('subtitle', 'Dados de cobrança, renovação e faturas geradas para este serviço IDrive e2.')

@section('actions')
    <a href="{{ route('s3.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('s3.edit', $license) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
    @endif
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $amount = fn ($value) => $license->currencySymbol().' '.number_format((float) $value, 2, ',', '.');
        $billing = fn ($value) => $license->billingCurrencySymbol().' '.number_format((float) $value, 2, ',', '.');
        $panelDiff = $license->panelDifference();
        $panelPct = $license->panelDivergencePercent();
        $extraMonthly = $license->monthlyEquivalent() - $license->storeMonthlyEquivalent();
    @endphp

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <span class="{{ $license->statusBadgeClass() }}">{{ $license->statusLabel() }}</span>
                            <span class="currency-chip ms-1">{{ $license->product }}</span>
                            <h2 class="mt-3 mb-1" style="font-size:1.25rem;">{{ $license->name }}</h2>
                            <div class="text-muted-soft" style="font-size:.8rem;">
                                {{ $license->provider }}
                                @if ($license->server_name)
                                    · <span class="mono">{{ $license->server_name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="stat-label">Custo mensal</div>
                            <div class="amount-hero mt-1">{{ $money($monthlyBrl) }}</div>
                            <div class="text-muted-soft mt-1" style="font-size:.74rem;">
                                {{ $billing($license->monthlyEquivalent()) }} × {{ number_format($rate, 4, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    <div class="kpi-inline mt-4 pt-3 border-top">
                        <div class="kpi">
                            <div class="kpi-label">Valor do ciclo</div>
                            <div class="kpi-value">{{ $billing($license->totalOriginal()) }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Ciclo</div>
                            <div class="kpi-value">{{ $license->billingCycleLabel() }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Quantidade</div>
                            <div class="kpi-value">{{ $license->quantity }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Próximo vencimento</div>
                            <div class="kpi-value {{ $license->isDueSoon() ? 'text-danger' : '' }}">
                                {{ $license->next_due_date?->format('d/m/Y') ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Faturas do serviço S3</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Documentos gerados ou sincronizados para este armazenamento</div>
                    </div>
                    <span class="badge text-bg-light border">{{ $license->invoices->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Referência</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th class="text-end">Total (BRL)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($license->invoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="cell-title d-block">{{ $invoice->external_reference }}</a>
                                        <span class="cell-sub">{{ $invoice->currencySymbol() }} {{ number_format((float) $invoice->amount_original, 2, ',', '.') }}</span>
                                    </td>
                                    <td class="tabular">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                                    <td><span class="{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span></td>
                                    <td class="text-end tabular fw-semibold">{{ $money($invoice->total_brl) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state py-4">
                                            <div class="icon"><i class="bi bi-receipt"></i></div>
                                            <h3>Nenhuma fatura gerada</h3>
                                            <p class="mb-0">Use o botão ao lado para gerar a próxima fatura deste serviço S3.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($license->notes)
                <div class="card">
                    <div class="card-header">Observações</div>
                    <div class="card-body">
                        {!! \App\Support\RichText::render($license->notes, 'rich-text mb-0 text-muted-soft') !!}
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Ações</div>
                <div class="card-body d-grid gap-2">
                    @if (auth()->user()->canManageFinance())
                        @if ($license->status === \App\Models\LicenseService::STATUS_ACTIVE)
                            <form method="POST" action="{{ route('s3.invoice', $license) }}" data-confirm="Gerar fatura de {{ $license->name }} no valor de {{ $billing($license->totalOriginal()) }}? O próximo vencimento avançará um ciclo." data-confirm-button="Gerar fatura">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-receipt me-1"></i> Gerar fatura deste ciclo
                                </button>
                            </form>
                        @else
                            <div class="text-muted-soft" style="font-size:.82rem;">Somente serviços ativos podem gerar faturas.</div>
                        @endif

                        <form method="POST" action="{{ route('s3.destroy', $license) }}" data-confirm="Remover o serviço S3 {{ $license->name }}? As faturas já geradas serão preservadas." data-confirm-button="Remover serviço" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-secondary w-100 text-danger">
                                <i class="bi bi-trash3 me-1"></i> Remover serviço S3
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Dados do armazenamento S3</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Fornecedor</span>
                        <span class="fw-semibold">{{ $license->provider }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Plano</span>
                        <span class="fw-semibold">{{ $license->product }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Valor de referência</span>
                        <span class="fw-semibold">{{ $amount($license->unit_price) }}</span>
                    </div>
                    @if ($license->usesPanelPrice())
                        <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                            <span class="text-muted-soft">Cobrado no console</span>
                            <span class="fw-semibold text-danger">{{ $billing($license->billingUnitPrice()) }}</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Renovação automática</span>
                        <span class="fw-semibold">{{ $license->auto_renew ? 'Sim' : 'Não' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Access Key</span>
                        <span class="fw-semibold mono" style="font-size:.74rem;">{{ $license->maskedKey() }}</span>
                    </div>

                    <a href="https://console.idrivee2.com/signin" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir Console IDrive e2
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Console IDrive e2</span>
                    @if ($license->panel_synced_at)
                        <span class="badge text-bg-light border">Lido {{ $license->panel_synced_at->format('d/m/Y H:i') }}</span>
                    @else
                        <span class="badge text-bg-light border">Sem leitura</span>
                    @endif
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    @if ($auth = auth()->user()->canManageFinance())
                        <form method="POST" action="{{ route('s3.panel.sync') }}" data-idrivee2-sync-form>
                            @csrf
                            <input type="hidden" name="otp_code" value="">
                            <button type="submit" class="btn btn-outline-secondary w-100" data-idrivee2-sync-btn>
                                <i class="bi bi-arrow-repeat me-1"></i> Sincronizar com IDrive e2
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
