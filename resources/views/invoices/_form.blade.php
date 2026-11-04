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
@php
    $locked = (bool) ($invoice->rate_locked ?? false);
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $taxesData = [
        'spread' => $taxes['spread'] ?? 0,
        'iof' => $taxes['iof'] ?? 0,
        'icms' => $taxes['icms'] ?? 0,
        'outros' => $taxes['outros'] ?? 0,
    ];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Dados da fatura</span>
                    @if ($locked)
                        <span class="badge text-bg-light border"><i class="bi bi-lock-fill me-1"></i> Câmbio travado</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="description">Descrição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description', $invoice->description) }}" maxlength="255" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="external_reference">Referência OVH</label>
                            <input type="text" class="form-control @error('external_reference') is-invalid @enderror" id="external_reference" name="external_reference" value="{{ old('external_reference', $invoice->external_reference) }}" maxlength="100" placeholder="PT123456">
                            @error('external_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="currency">Moeda <span class="text-danger">*</span></label>
                            <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency" @disabled($locked)>
                                <option value="EUR" @selected(old('currency', $invoice->currency) === 'EUR')>EUR · Euro</option>
                                <option value="USD" @selected(old('currency', $invoice->currency) === 'USD')>USD · Dólar</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="amount_original">Valor original <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control tabular @error('amount_original') is-invalid @enderror" id="amount_original" name="amount_original" value="{{ old('amount_original', $invoice->amount_original) }}" required @readonly($locked)>
                            @error('amount_original')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="due_date">Vencimento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('due_date') is-invalid @enderror" id="due_date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?? now()->addDays(7)->format('Y-m-d')) }}" required>
                            @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="exchange_rate">Cotação do dia</label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control tabular @error('exchange_rate') is-invalid @enderror" id="exchange_rate" name="exchange_rate" value="{{ old('exchange_rate', $invoice->exchange_rate ?: '') }}" placeholder="{{ number_format((float) ($rates[$invoice->currency ?: 'EUR']['rate'] ?? 0), 4, ',', '.') }}" @readonly($locked)>
                            <div class="form-text">Em branco: usa a cotação atual ({{ $rates['USD']['stale'] ?? false ? 'última conhecida' : 'AwesomeAPI' }}).</div>
                            @error('exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                @foreach (\App\Models\Invoice::statuses() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $invoice->status ?: 'pending') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Câmbio</label>
                            <div class="surface-muted px-3 py-2 d-flex align-items-center gap-2" style="min-height:42px;">
                                <i class="bi {{ $locked ? 'bi-lock-fill' : 'bi-unlock' }}"></i>
                                <span style="font-size:.8rem;">{{ $locked ? 'Travado — não acompanha o mercado' : 'Acompanha a cotação a cada 15 minutos' }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($locked)
                        <div class="hint-banner mt-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
                            <i class="bi bi-lock"></i>
                            <div>Esta fatura está com o câmbio travado (normalmente após o pagamento). Moeda, valor e cotação não serão recalculados. Destrave na tela de detalhes se precisar editar.</div>
                        </div>
                    @endif
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end gap-2 py-3">
                    <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar fatura</button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <span>Prévia do cálculo</span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="stat-label">Total em BRL</span>
                        <span class="amount-hero" data-calc="total">R$ 0,00</span>
                    </div>
                    <div class="text-muted-soft mb-3" style="font-size:.74rem;">
                        Cotação usada: <strong data-calc="rate">—</strong>
                    </div>

                    <table class="breakdown">
                        <tr>
                            <td>Valor convertido</td>
                            <td class="value" data-calc="base">R$ 0,00</td>
                        </tr>                        <tr>
                            <td>Spread <span class="text-muted-soft" data-calc="spread-percent">0,00%</span></td>
                            <td class="value" data-calc="spread">R$ 0,00</td>
                        </tr>
                        <tr>
                            <td>IOF <span class="text-muted-soft" data-calc="iof-percent">0,00%</span></td>
                            <td class="value" data-calc="iof">R$ 0,00</td>
                        </tr>
                        <tr>
                            <td>ICMS <span class="text-muted-soft" data-calc="icms-percent">0,00%</span></td>
                            <td class="value" data-calc="icms">R$ 0,00</td>
                        </tr>
                        <tr>
                            <td>Outros tributos <span class="text-muted-soft" data-calc="others-percent">0,00%</span></td>
                            <td class="value" data-calc="others">R$ 0,00</td>
                        </tr>
                        <tr class="total">
                            <td>Total</td>
                            <td class="value" data-calc="total">R$ 0,00</td>
                        </tr>
                    </table>

                    <div class="text-muted-soft mt-2" style="font-size:.74rem;">
                        VET: <strong data-calc="vet">R$ 0,00</strong> por <span data-calc="currency-code">EUR</span>
                        — total em reais dividido pelo valor original, já com tributos.
                    </div>

                    <div class="hint-banner mt-3">
                        <i class="bi bi-info-circle"></i>
                        <div>Os percentuais vêm de Configurações › Impostos. A prévia é apenas estimativa; o valor final é recalculado no servidor ao salvar.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script type="application/json" id="invoice-calc-data">{!! json_encode([
    'rates' => [
        'EUR' => ['rate' => (float) ($rates['EUR']['rate'] ?? 0)],
        'USD' => ['rate' => (float) ($rates['USD']['rate'] ?? 0)],
    ],
    'taxes' => $taxesData,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
