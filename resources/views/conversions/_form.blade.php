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
@php
    $currencyOptions = \App\Models\CurrencyConversion::currencies();
    $from = old('from_currency', $conversion->from_currency ?: 'EUR');
    $to = old('to_currency', $conversion->to_currency ?: 'USD');
    $quote = $quote ?? [];
    $defaults = $defaults ?? [];
    $formPair = $from.'-'.$to;
@endphp

<form method="POST" action="{{ $action }}" id="conversion-form">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">Dados da conversão</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="description">Descrição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description', $conversion->description) }}" maxlength="150" placeholder="Ex.: Servidor OVH de novembro" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="from_currency">De <span class="text-danger">*</span></label>
                            <select class="form-select @error('from_currency') is-invalid @enderror" id="from_currency" name="from_currency" required>
                                @foreach ($currencyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($from === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('from_currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2 d-flex align-items-end justify-content-center">
                            <button type="button" class="btn btn-outline-secondary w-100" id="swap-currencies" title="Inverter moedas" aria-label="Inverter moedas">
                                <i class="bi bi-arrow-left-right"></i>
                            </button>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="to_currency">Para <span class="text-danger">*</span></label>
                            <select class="form-select @error('to_currency') is-invalid @enderror" id="to_currency" name="to_currency" required>
                                @foreach ($currencyOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($to === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('to_currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="amount_from">Valor em <span data-calc="from-code">{{ $from }}</span> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" data-calc="from-symbol">{{ config('finance.currencies.'.$from.'.symbol') }}</span>
                                <input type="number" step="0.01" min="0.01" class="form-control tabular @error('amount_from') is-invalid @enderror" id="amount_from" name="amount_from" value="{{ old('amount_from', (float) $conversion->amount_from > 0 ? $conversion->amount_from : '') }}" placeholder="0,00" required>
                            </div>
                            @error('amount_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="market_rate">Cotação de mercado <span data-calc="pair-label" class="text-muted-soft fw-normal">({{ $formPair }})</span></label>
                            <div class="input-group">
                                <input type="number" step="0.000001" min="0.000001" class="form-control tabular @error('market_rate') is-invalid @enderror" id="market_rate" name="market_rate" value="{{ old('market_rate', $conversion->market_rate ?: number_format((float) ($quote['rate'] ?? 0), 6, '.', '')) }}" placeholder="{{ number_format((float) ($quote['rate'] ?? 0), 6, ',', '.') }}">
                                <button type="button" class="btn btn-outline-secondary" id="refresh-quote" title="Buscar cotação atual">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <span data-calc="quote-source">{{ $quote['source'] ?? '—' }}</span>
                                · atualizado <span data-calc="quote-at">{{ isset($quote['updated_at']) ? \Illuminate\Support\Carbon::parse($quote['updated_at'])->format('d/m/Y H:i') : '—' }}</span>
                            </div>
                            @error('market_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="provider_rate">
                                Cotação do provedor
                                <span class="text-muted-soft fw-normal">(opcional — PayPal, banco, emissor)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                <input type="number" step="0.000001" min="0.000001" class="form-control tabular @error('provider_rate') is-invalid @enderror" id="provider_rate" name="provider_rate" value="{{ old('provider_rate', $conversion->providerRateRaw()) }}" placeholder="1 {{ $from }} = ? {{ $to }}">
                            </div>
                            <div class="form-text">
                                Copie a taxa do recibo do provedor na direção
                                <strong data-calc="from-code">{{ $from }}</strong> → <strong data-calc="to-code">{{ $to }}</strong>.
                                Se o recibo mostrar a direção inversa (ex.: <em>1 USD = 0,8498 EUR</em> num pagamento em euro),
                                pode informar assim: o sistema converte e o total continua batendo com o recibo.
                            </div>
                            <div class="form-text d-none" data-calc="provider-note"></div>
                            @error('provider_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    @if (! empty($quote['stale']))
                        <div class="hint-banner mt-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
                            <i class="bi bi-wifi-off"></i>
                            <div>A API de câmbio não respondeu agora. A cotação exibida é a última conhecida; confira antes de salvar.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Tarifas do cartão internacional</span>
                    <span class="badge text-bg-light border">% ou valor fixo</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="spread_percent">Spread bancário (%)</label>
                            <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('spread_percent') is-invalid @enderror" id="spread_percent" name="spread_percent" value="{{ old('spread_percent', $conversion->spread_percent ?? ($defaults['spread_percent'] ?? 0)) }}">
                            @error('spread_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="iof_percent">IOF (%)</label>
                            <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('iof_percent') is-invalid @enderror" id="iof_percent" name="iof_percent" value="{{ old('iof_percent', $conversion->iof_percent ?? ($defaults['iof_percent'] ?? 0)) }}">
                            @error('iof_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="fixed_fee">Tarifa fixa (<span data-calc="to-code">{{ $to }}</span>)</label>
                            <input type="number" step="0.01" min="0" class="form-control tabular @error('fixed_fee') is-invalid @enderror" id="fixed_fee" name="fixed_fee" value="{{ old('fixed_fee', $conversion->fixed_fee ?? ($defaults['fixed_fee'] ?? 0)) }}">
                            @error('fixed_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="buffer_percent">Margem de segurança (%)</label>
                            <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('buffer_percent') is-invalid @enderror" id="buffer_percent" name="buffer_percent" value="{{ old('buffer_percent', $conversion->buffer_percent ?? ($defaults['buffer_percent'] ?? 0)) }}">
                            @error('buffer_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="hint-banner mt-3">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            IOF de <strong>3,5%</strong> em 2026 incide no cartão internacional <em>ou</em> no aporte da conta global.
                            Se o saldo em dólar já foi abastecido, deixe o IOF em <strong>0%</strong> aqui para não contar o imposto duas vezes.
                            Os padrões são editáveis em Configurações › Conversão.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Observações</div>
                <div class="card-body">
                    <label class="form-label visually-hidden" for="notes">Observações</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="500" placeholder="Banco emissor, bandeira, final do cartão, competência...">{{ old('notes', $conversion->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
                    <a href="{{ $conversion->exists ? route('conversions.show', $conversion) : route('conversions.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar conversão</button>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card position-sticky" style="top: 88px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Prévia do cálculo</span>
                    <span class="currency-chip" data-calc="pair-chip">{{ $formPair }}</span>
                </div>
                <div class="card-body">
                    <div class="stat-label">Você precisa de</div>
                    <div class="amount-hero mt-1" data-calc="total">—</div>
                    <div class="text-muted-soft mt-1" style="font-size:.74rem;">
                        para pagar <strong data-calc="amount-label">—</strong>
                    </div>

                    <table class="breakdown mt-3">
                        <tr>
                            <td>Valor pela cotação de mercado</td>
                            <td class="value" data-calc="base">—</td>
                        </tr>
                        <tr>
                            <td>Spread <span class="text-muted-soft" data-calc="spread-percent">0,00%</span></td>
                            <td class="value" data-calc="spread">—</td>
                        </tr>
                        <tr>
                            <td>IOF <span class="text-muted-soft" data-calc="iof-percent">0,00%</span></td>
                            <td class="value" data-calc="iof">—</td>
                        </tr>
                        <tr>
                            <td>Tarifa fixa</td>
                            <td class="value" data-calc="fixed">—</td>
                        </tr>
                        <tr>
                            <td>Margem de segurança <span class="text-muted-soft" data-calc="buffer-percent">0,00%</span></td>
                            <td class="value" data-calc="buffer">—</td>
                        </tr>
                        <tr>
                            <td>Total de tarifas</td>
                            <td class="value" data-calc="fees">—</td>
                        </tr>
                        <tr class="total">
                            <td>Total a pagar</td>
                            <td class="value" data-calc="total">—</td>
                        </tr>
                    </table>

                    <div class="kpi-inline mt-3 pt-3 border-top">
                        <div class="kpi">
                            <div class="kpi-label">VET · R$ por <span data-calc="from-code">{{ $from }}</span></div>
                            <div class="kpi-value" data-calc="vet">—</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Cotação efetiva</div>
                            <div class="kpi-value" data-calc="effective">—</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Custo sobre o mercado</div>
                            <div class="kpi-value" data-calc="cost">—</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Equivalente em BRL</div>
                            <div class="kpi-value" data-calc="brl">—</div>
                        </div>
                    </div>

                    <div class="form-text mt-2">
                        VET (Valor Efetivo Total): quanto custa, em reais, cada unidade da moeda de origem já com câmbio,
                        spread, IOF, tarifa e margem. É a taxa efetiva que o app do banco mostra.
                    </div>

                    <div class="hint-banner mt-3">
                        <i class="bi bi-calculator"></i>
                        <div>A prévia é estimativa. Ao salvar, o servidor refaz a conta com a cotação registrada e grava a origem e o horário do câmbio.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="conversion-calc-data">{!! json_encode([
    'pair' => $formPair,
    'rate' => (float) ($quote['rate'] ?? 0),
    'toBrlRate' => (float) ($quote['to_brl_rate'] ?? 0),
    'source' => $quote['source'] ?? '',
    'updatedAt' => $quote['updated_at'] ?? '',
    'defaults' => [
        'spread_percent' => (float) ($defaults['spread_percent'] ?? 0),
        'iof_percent' => (float) ($defaults['iof_percent'] ?? 0),
        'fixed_fee' => (float) ($defaults['fixed_fee'] ?? 0),
        'buffer_percent' => (float) ($defaults['buffer_percent'] ?? 0),
    ],
    'symbols' => [
        'EUR' => (string) config('finance.currencies.EUR.symbol'),
        'USD' => (string) config('finance.currencies.USD.symbol'),
    ],
    'quoteUrl' => route('conversions.quote'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
