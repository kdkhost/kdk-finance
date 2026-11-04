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

@section('title', $conversion->description)
@section('eyebrow', 'Conversão '.$conversion->pairLabel())
@section('subtitle', 'Detalhamento do câmbio, das tarifas do cartão e conferência com a fatura.')

@section('actions')
    <a href="{{ route('conversions.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('conversions.edit', $conversion) }}" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Editar
        </a>
    @endif
@endsection

@section('content')
    @php
        $fromMoney = fn ($value) => $conversion->fromSymbol().' '.number_format((float) $value, 2, ',', '.');
        $toMoney = fn ($value) => $conversion->toSymbol().' '.number_format((float) $value, 2, ',', '.');
        $difference = $conversion->statementDifference();
        $currentRate = (float) ($quote['rate'] ?? 0);
        $savedRate = (float) $conversion->market_rate;
        $rateDrift = $savedRate > 0 ? (($currentRate - $savedRate) / $savedRate) * 100 : 0;
    @endphp

    @if ($conversion->rate_stale)
        <div class="hint-banner mb-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
            <i class="bi bi-wifi-off"></i>
            <div>No momento do cálculo a API de câmbio estava indisponível; foi usada a última cotação conhecida. Recalcule quando a conexão voltar.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <span class="currency-chip">{{ $conversion->pairLabel() }}</span>
                            @if ($conversion->isCalibrated())
                                <span class="badge text-bg-light border ms-1"><i class="bi bi-patch-check-fill text-success me-1"></i> conferida</span>
                            @endif
                            <h2 class="mt-3 mb-1" style="font-size:1.25rem;">{{ $conversion->description }}</h2>
                            <div class="text-muted-soft" style="font-size:.8rem;">
                                Criada em {{ $conversion->created_at?->format('d/m/Y H:i') }}
                                @if ($conversion->creator)
                                    por {{ $conversion->creator->name }}
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="stat-label">Você precisa de</div>
                            <div class="amount-hero mt-1">{{ $toMoney($conversion->total_to) }}</div>
                            <div class="text-muted-soft mt-1" style="font-size:.74rem;">
                                para pagar {{ $fromMoney($conversion->amount_from) }}
                            </div>
                        </div>
                    </div>

                    <div class="kpi-inline mt-4 pt-3 border-top">
                        <div class="kpi">
                            <div class="kpi-label">Cotação de mercado</div>
                            <div class="kpi-value">{{ number_format($savedRate, 6, ',', '.') }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Cotação efetiva</div>
                            <div class="kpi-value">{{ number_format((float) $conversion->effective_rate, 6, ',', '.') }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Custo total</div>
                            <div class="kpi-value">{{ number_format($conversion->costPercent(), 3, ',', '.') }}%</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Equivalente em BRL</div>
                            <div class="kpi-value">R$ {{ number_format((float) $conversion->total_brl, 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Composição do valor</div>
                <div class="card-body">
                    <table class="breakdown">
                        <tr>
                            <td>Valor de origem</td>
                            <td class="value">{{ $fromMoney($conversion->amount_from) }}</td>
                        </tr>
                        <tr>
                            <td>Convertido pela cotação {{ number_format($savedRate, 6, ',', '.') }}</td>
                            <td class="value">{{ $toMoney($conversion->base_to) }}</td>
                        </tr>
                        <tr>
                            <td>Spread <span class="text-muted-soft">{{ number_format((float) $conversion->spread_percent, 3, ',', '.') }}%</span></td>
                            <td class="value">{{ $toMoney($conversion->spread_amount) }}</td>
                        </tr>
                        <tr>
                            <td>IOF <span class="text-muted-soft">{{ number_format((float) $conversion->iof_percent, 3, ',', '.') }}%</span></td>
                            <td class="value">{{ $toMoney($conversion->iof_amount) }}</td>
                        </tr>
                        <tr>
                            <td>Tarifa fixa</td>
                            <td class="value">{{ $toMoney($conversion->fixed_fee) }}</td>
                        </tr>
                        <tr>
                            <td>Margem de segurança <span class="text-muted-soft">{{ number_format((float) $conversion->buffer_percent, 3, ',', '.') }}%</span></td>
                            <td class="value">{{ $toMoney($conversion->buffer_amount) }}</td>
                        </tr>
                        <tr>
                            <td>Total de tarifas</td>
                            <td class="value">{{ $toMoney($conversion->fees_total) }}</td>
                        </tr>
                        <tr class="total">
                            <td>Total a pagar em {{ $conversion->to_currency }}</td>
                            <td class="value">{{ $toMoney($conversion->total_to) }}</td>
                        </tr>
                    </table>

                    <div class="text-muted-soft mt-2" style="font-size:.76rem;">
                        Referência em reais: {{ $toMoney($conversion->total_to) }} × {{ number_format((float) $conversion->to_brl_rate, 4, ',', '.') }} = <strong>R$ {{ number_format((float) $conversion->total_brl, 2, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Conferência com a fatura do cartão</span>
                    @if ($conversion->isCalibrated())
                        <span class="badge text-bg-light border">{{ $conversion->statement_checked_at?->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($conversion->isCalibrated())
                        @php
                            $tone = $difference > 0 ? 'var(--kdk-red)' : 'var(--kdk-emerald, #15803d)';
                        @endphp
                        <div class="kpi-inline mb-3">
                            <div class="kpi">
                                <div class="kpi-label">Cobrado na fatura</div>
                                <div class="kpi-value">{{ $toMoney($conversion->statement_amount) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Calculado aqui</div>
                                <div class="kpi-value">{{ $toMoney($conversion->total_to) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Diferença</div>
                                <div class="kpi-value" style="color: {{ $tone }};">{{ $toMoney($difference) }}</div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Custo real do cartão</div>
                                <div class="kpi-value">{{ number_format((float) $conversion->statement_cost_percent, 3, ',', '.') }}%</div>
                            </div>
                        </div>
                        <div class="hint-banner mb-3">
                            <i class="bi bi-lightbulb"></i>
                            <div>
                                @if ($difference > 0)
                                    O cartão cobrou mais do que o cálculo. O custo real ficou em
                                    <strong>{{ number_format((float) $conversion->statement_cost_percent, 3, ',', '.') }}%</strong>
                                    sobre a cotação de mercado — vale rever o spread e o IOF em Configurações › Conversão.
                                @elseif ($difference < 0)
                                    O cartão cobrou menos do que o cálculo. Seus percentuais estão com folga; você pode reduzi-los com segurança.
                                @else
                                    Cálculo exato: nenhuma diferença em relação à fatura do cartão.
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-muted-soft" style="font-size:.84rem;">
                            Depois que a fatura do cartão fechar, informe o valor cobrado. O sistema mostra a diferença e o custo real
                            para você calibrar o spread e o IOF — sem surpresas no mês seguinte.
                        </p>
                    @endif

                    @if (auth()->user()->canManageFinance())
                        <form method="POST" action="{{ route('conversions.calibrate', $conversion) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-6 col-lg-5">
                                <label class="form-label" for="statement_amount">Valor cobrado na fatura ({{ $conversion->to_currency }})</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $conversion->toSymbol() }}</span>
                                    <input type="number" step="0.01" min="0" class="form-control tabular @error('statement_amount') is-invalid @enderror" id="statement_amount" name="statement_amount" value="{{ old('statement_amount', $conversion->statement_amount) }}" required>
                                </div>
                                @error('statement_amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <button type="submit" class="btn btn-navy w-100"><i class="bi bi-check2-circle me-1"></i> Conferir fatura</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Ações</div>
                <div class="card-body d-grid gap-2">
                    @if (auth()->user()->canManageFinance())
                        <form method="POST" action="{{ route('conversions.recalculate', $conversion) }}" data-confirm="Recalcular com a cotação atual? O valor gravado será atualizado." data-confirm-button="Recalcular">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100"><i class="bi bi-calculator me-1"></i> Recalcular com a cotação atual</button>
                        </form>
                        <a href="{{ route('conversions.edit', $conversion) }}" class="btn btn-outline-secondary w-100"><i class="bi bi-pencil me-1"></i> Editar valor e tarifas</a>
                        <form method="POST" action="{{ route('conversions.destroy', $conversion) }}" data-confirm="Excluir esta conversão? Esta ação não pode ser desfeita." data-confirm-button="Excluir" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-secondary w-100 text-danger"><i class="bi bi-trash me-1"></i> Excluir conversão</button>
                        </form>
                    @else
                        <div class="text-muted-soft" style="font-size:.82rem;">Seu perfil permite apenas consulta das conversões.</div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Câmbio</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Cotação gravada</span>
                        <span class="fw-semibold">{{ number_format($savedRate, 6, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Cotação agora</span>
                        <span class="fw-semibold">{{ number_format($currentRate, 6, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Variação desde o cálculo</span>
                        <span class="fw-semibold {{ $rateDrift > 0 ? 'text-danger' : ($rateDrift < 0 ? 'text-success' : '') }}">
                            {{ $rateDrift > 0 ? '+' : '' }}{{ number_format($rateDrift, 3, ',', '.') }}%
                        </span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Origem</span>
                        <span class="fw-semibold text-end">{{ $conversion->rate_source ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Cotação de</span>
                        <span class="fw-semibold">{{ $conversion->rate_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Atualizado em</span>
                        <span class="fw-semibold">{{ $conversion->updated_at?->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>

            @if ($conversion->notes)
                <div class="card mt-3">
                    <div class="card-header">Observações</div>
                    <div class="card-body">
                        <p class="mb-0" style="font-size:.84rem; white-space:pre-line;">{{ $conversion->notes }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
