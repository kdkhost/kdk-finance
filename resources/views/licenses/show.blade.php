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
@section('eyebrow', 'Licença · '.$license->provider)
@section('subtitle', 'Dados de cobrança, renovação e faturas geradas para este serviço.')

@section('actions')
    <a href="{{ route('licenses.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('licenses.edit', $license) }}" class="btn btn-primary">
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
                        <div class="fw-semibold">Faturas da licença</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Documentos gerados pelo sistema para este serviço</div>
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
                                            <p class="mb-0">Use o botão ao lado para gerar a próxima fatura desta licença.</p>
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
                            <form method="POST" action="{{ route('licenses.invoice', $license) }}" data-confirm="Gerar fatura de {{ $license->product }} no valor de {{ $billing($license->totalOriginal()) }}? O próximo vencimento avançará um ciclo." data-confirm-button="Gerar fatura">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-receipt me-1"></i> Gerar fatura deste ciclo
                                </button>
                            </form>
                        @else
                            <div class="text-muted-soft" style="font-size:.82rem;">Somente licenças ativas podem gerar faturas. Edite a situação para reativar.</div>
                        @endif

                        <form method="POST" action="{{ route('licenses.destroy', $license) }}" data-confirm="Remover a licença {{ $license->name }}? As faturas já geradas serão preservadas." data-confirm-button="Remover licença" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-secondary w-100 text-danger">
                                <i class="bi bi-trash3 me-1"></i> Remover licença
                            </button>
                        </form>
                    @else
                        <div class="text-muted-soft" style="font-size:.82rem;">Seu perfil permite apenas consulta. Ações de cobrança estão disponíveis para gestores financeiros.</div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Dados do serviço</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Fornecedor</span>
                        <span class="fw-semibold">{{ $license->provider }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Produto</span>
                        <span class="fw-semibold">{{ $license->product }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Valor na loja</span>
                        <span class="fw-semibold">{{ $amount($license->unit_price) }}</span>
                    </div>
                    @if ($license->usesPanelPrice())
                        <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                            <span class="text-muted-soft">Cobrado no painel</span>
                            <span class="fw-semibold text-danger">{{ $billing($license->billingUnitPrice()) }}</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Moeda da cobrança</span>
                        <span class="fw-semibold">{{ $license->billingCurrency() }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Renovação automática</span>
                        <span class="fw-semibold">{{ $license->auto_renew ? 'Sim' : 'Não' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Chave</span>
                        <span class="fw-semibold mono" style="font-size:.74rem;">{{ $license->maskedKey() }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Última fatura</span>
                        <span class="fw-semibold">{{ $license->lastInvoice?->external_reference ?? '—' }}</span>
                    </div>

                    <a href="https://licenseboy.com" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mt-3">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir LicenseBoy
                    </a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Painel LicenseBoy</span>
                    @if ($license->panel_synced_at)
                        <span class="badge text-bg-light border">Lido {{ $license->panel_synced_at->format('d/m/Y H:i') }}</span>
                    @else
                        <span class="badge text-bg-light border">Sem leitura</span>
                    @endif
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    @if ($license->usesPanelPrice())
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Valor no painel</span>
                            <span class="fw-semibold">{{ $billing($license->billingUnitPrice()) }} / {{ $license->billingCycleLabel() }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Total do ciclo</span>
                            <span class="fw-semibold tabular">{{ $billing($license->totalOriginal()) }}</span>
                        </div>
                        @if ($license->catalogItem)
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted-soft">Preço na loja</span>
                                <span class="fw-semibold">{{ $license->catalogItem->priceLabel() }} / {{ $license->catalogItem->cycleLabel() }}</span>
                            </div>
                        @endif

                        @if ($panelPct !== null && $panelPct > 0)
                            <div class="hint-banner mt-2" style="font-size:.74rem;">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    O painel cobra <strong>{{ number_format(abs($panelDiff), 2, ',', '.') }} {{ $license->billingCurrency() }}</strong>
                                    ({{ number_format($panelPct, 2, ',', '.') }}%) a mais que a loja por unidade.
                                    @if ($license->billingCurrency() === $license->currency && $extraMonthly > 0)
                                        Isso representa <strong>{{ $billing($extraMonthly) }}/mês</strong>
                                        ({{ $billing($extraMonthly * 12) }}/ano) a mais.
                                    @endif
                                </div>
                            </div>
                        @elseif ($panelPct !== null && $panelPct < 0)
                            <div class="hint-banner mt-2" style="font-size:.74rem;">
                                <i class="bi bi-check2-circle"></i>
                                <div>
                                    O painel cobra <strong>{{ number_format(abs($panelDiff), 2, ',', '.') }} {{ $license->billingCurrency() }}</strong>
                                    ({{ number_format(abs($panelPct), 2, ',', '.') }}%) a menos que a loja por unidade.
                                </div>
                            </div>
                        @elseif ($panelPct === 0.0)
                            <div class="text-muted-soft mt-2" style="font-size:.74rem;">O valor do painel é igual ao preço da loja.</div>
                        @endif
                    @else
                        <p class="text-muted-soft mb-2">
                            Nenhum valor do painel registrado. A cobrança usa o preço da loja. Sincronize com a conta da LicenseBoy
                            ou informe o valor manualmente.
                        </p>
                        @if (auth()->user()->canManageFinance())
                            <form method="POST" action="{{ route('licenses.panel.sync') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-repeat me-1"></i> Sincronizar painel
                                </button>
                            </form>
                        @endif
                    @endif

                    <p class="text-muted-soft mt-3 mb-0" style="font-size:.74rem;">
                        O painel de cliente da LicenseBoy não permite editar o valor recorrente — somente o suporte altera.
                        Este sistema registra o valor real cobrado e o usa nas faturas.
                    </p>

                    @if (auth()->user()->canManageFinance())
                        <a href="{{ route('licenses.edit', $license) }}" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-pencil me-1"></i> Ajustar valor do painel
                        </a>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Catálogo LicenseBoy</span>
                    @if ($license->catalogItem)
                        <span class="badge text-bg-light border">Vinculado</span>
                    @endif
                </div>
                <div class="card-body" style="font-size:.8rem;">
                    @if ($license->catalogItem)
                        <div class="d-flex justify-content-between gap-3 py-1">
                            <span class="text-muted-soft">Produto na loja</span>
                            <span class="fw-semibold text-end">{{ $license->catalogItem->name }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Preço da loja</span>
                            <span class="fw-semibold">{{ $license->catalogItem->priceLabel() }} / {{ $license->catalogItem->cycleLabel() }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Disponibilidade</span>
                            <span class="fw-semibold">{{ $license->catalogItem->availabilityLabel() }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Última leitura</span>
                            <span class="fw-semibold">{{ $license->catalogItem->synced_at?->format('d/m/Y H:i') ?? '—' }}</span>
                        </div>

                        @if ((float) $license->catalogItem->price !== (float) $license->unit_price && ! $license->usesPanelPrice())
                            <div class="hint-banner mt-2" style="font-size:.74rem;">
                                <i class="bi bi-arrow-repeat"></i>
                                <div>O valor salvo difere do catálogo. A próxima sincronização de preços ajusta automaticamente.</div>
                            </div>
                        @elseif ($license->usesPanelPrice())
                            <div class="text-muted-soft mt-2" style="font-size:.74rem;">
                                O preço da loja é mantido como referência; a cobrança usa o valor real do painel.
                            </div>
                        @endif

                        <a href="{{ route('licenses.prices') }}" class="btn btn-outline-secondary w-100 mt-3">
                            <i class="bi bi-tags me-1"></i> Ver catálogo de preços
                        </a>
                    @else
                        <p class="text-muted-soft mb-2">Esta licença usa um preço manual e não acompanha a loja.</p>
                        @if (auth()->user()->canManageFinance())
                            <a href="{{ route('licenses.edit', $license) }}" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-link-45deg me-1"></i> Vincular ao catálogo
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Cotação aplicada</div>
                <div class="card-body" style="font-size:.8rem;">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted-soft">{{ $license->currency }} → BRL</span>
                        <span class="fw-semibold tabular">{{ number_format($rate, 4, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted-soft">Projeção anual</span>
                        <span class="fw-semibold tabular">{{ $money($monthlyBrl * 12) }}</span>
                    </div>
                    <p class="text-muted-soft mt-2 mb-0" style="font-size:.74rem;">Cotação AwesomeAPI com fallback para a última conhecida.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
