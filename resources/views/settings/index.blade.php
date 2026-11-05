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

@section('title', 'Configurações')
@section('eyebrow', 'Sistema')
@section('subtitle', 'Câmbio, tributos, credenciais OVHcloud e regras de sincronização.')

@section('content')
    @php
        $activeTab = request('tab', 'taxes');
        $user = auth()->user();
        $taxValues = [
            'spread_percent' => \App\Models\AppSetting::get('spread_percent', config('finance.taxes.spread_percent')),
            'iof_percent' => \App\Models\AppSetting::get('iof_percent', config('finance.taxes.iof_percent')),
            'icms_percent' => \App\Models\AppSetting::get('icms_percent', config('finance.taxes.icms_percent')),
            'other_taxes_percent' => \App\Models\AppSetting::get('other_taxes_percent', config('finance.taxes.other_taxes_percent')),
        ];
    @endphp

    @php
        $tabs = array_values(array_filter([
            ['key' => 'taxes', 'label' => 'Câmbio e tributos', 'icon' => 'bi-percent'],
            ['key' => 'conversion', 'label' => 'Conversão e cartão', 'icon' => 'bi-currency-exchange'],
            $user->isDeveloperAdmin() ? ['key' => 'ovh', 'label' => 'OVHcloud', 'icon' => 'bi-cloud'] : null,
            $user->isDeveloperAdmin() ? ['key' => 'licenseboy', 'label' => 'LicenseBoy', 'icon' => 'bi-key'] : null,
            $user->isDeveloperAdmin() ? ['key' => 'rules', 'label' => 'Regras', 'icon' => 'bi-sliders'] : null,
            $user->isDeveloperAdmin() ? ['key' => 'mail', 'label' => 'E-mail', 'icon' => 'bi-envelope-at'] : null,
            $user->isDeveloperAdmin() ? ['key' => 'security', 'label' => 'Segurança', 'icon' => 'bi-shield-check'] : null,
        ]));
    @endphp

    <ul class="nav nav-pills kdk-tabs mb-3" data-settings-tabs aria-label="Seções das configurações">
        @foreach ($tabs as $tab)
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === $tab['key'] ? 'active' : '' }}" href="{{ route('settings.index', ['tab' => $tab['key']]) }}" data-settings-tab="{{ $tab['key'] }}" aria-controls="settings-panel" @if ($activeTab === $tab['key']) aria-current="page" @endif>
                    <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div id="settings-panel" tabindex="-1" aria-live="polite">
    @if ($activeTab === 'taxes')
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Percentuais aplicados</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.taxes') }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                @foreach ([
                                    'spread_percent' => 'Spread cambial',
                                    'iof_percent' => 'IOF',
                                    'icms_percent' => 'ICMS',
                                    'other_taxes_percent' => 'Outros tributos',
                                ] as $field => $label)
                                    <div class="col-sm-6">
                                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" max="100" class="form-control tabular @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $taxValues[$field]) }}" required>
                                            <span class="input-group-text">%</span>
                                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="hint-banner mt-3">
                                <i class="bi bi-calculator"></i>
                                <div>
                                    Fórmula: <span class="mono">valor × cotação</span> + spread sobre a base; IOF, ICMS e outros incidem sobre a base com spread.
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar percentuais</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Cotações atuais</span>
                        @if ($user->isDeveloperAdmin())
                            <form method="POST" action="{{ route('settings.rates.refresh') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-repeat me-1"></i> Atualizar</button>
                            </form>
                        @endif
                    </div>
                    <div class="card-body">
                        @foreach (['USD', 'EUR'] as $currency)
                            @php $rate = $rates[$currency]; @endphp
                            <div class="surface-muted p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="currency-chip">{{ config('finance.currencies.'.$currency.'.symbol') }} {{ $currency }}</span>
                                        <div class="text-muted-soft mt-1" style="font-size:.72rem;">{{ config('finance.currencies.'.$currency.'.label') }}</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-5 fw-bold tabular">R$ {{ number_format($rate['rate'], 4, ',', '.') }}</div>
                                        <span class="stat-delta {{ $rate['pct_change'] >= 0 ? 'is-up' : 'is-down' }}">
                                            <i class="bi {{ $rate['pct_change'] >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                                            {{ number_format(abs($rate['pct_change']), 2, ',', '.') }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @php
                            $rateSource = (string) $rates['USD']['source'];
                            $rateSourceBase = str_replace(' (última conhecida)', '', $rateSource);
                            $rateSourceLabels = [
                                'awesomeapi' => 'AwesomeAPI',
                                'open.er-api' => 'ExchangeRate-API',
                                'frankfurter' => 'Frankfurter (BCE)',
                            ];
                            $rateSourceLabel = $rateSourceLabels[$rateSourceBase] ?? $rateSourceBase;
                            if (str_contains($rateSource, 'última conhecida')) {
                                $rateSourceLabel .= ' (última conhecida)';
                            }
                            $usingAlternative = in_array($rateSourceBase, ['open.er-api', 'frankfurter'], true);
                        @endphp
                        <div class="text-muted-soft" style="font-size:.72rem;">
                            Fonte: {{ $rateSourceLabel }} · atualizado em {{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('d/m/Y H:i') }}.
                            Cache de 15 minutos com fallback para a última cotação conhecida.
                            @if ($usingAlternative)
                                <span class="d-block mt-1">A AwesomeAPI está limitada no momento; usando o provedor alternativo automaticamente.</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'conversion')
        @php
            $conversionValues = [
                'conversion_spread_percent' => \App\Models\AppSetting::get('conversion_spread_percent', config('finance.conversion.spread_percent')),
                'conversion_iof_percent' => \App\Models\AppSetting::get('conversion_iof_percent', config('finance.conversion.iof_percent')),
                'conversion_fixed_fee' => \App\Models\AppSetting::get('conversion_fixed_fee', config('finance.conversion.fixed_fee')),
                'conversion_buffer_percent' => \App\Models\AppSetting::get('conversion_buffer_percent', config('finance.conversion.buffer_percent')),
            ];
        @endphp

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Tarifas padrão das conversões</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.conversion') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="conversion_spread_percent">Spread bancário</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('conversion_spread_percent') is-invalid @enderror" id="conversion_spread_percent" name="conversion_spread_percent" value="{{ old('conversion_spread_percent', $conversionValues['conversion_spread_percent']) }}" required>
                                        <span class="input-group-text">%</span>
                                        @error('conversion_spread_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">Margem que o banco aplica sobre a cotação de mercado.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="conversion_iof_percent">IOF</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('conversion_iof_percent') is-invalid @enderror" id="conversion_iof_percent" name="conversion_iof_percent" value="{{ old('conversion_iof_percent', $conversionValues['conversion_iof_percent']) }}" required>
                                        <span class="input-group-text">%</span>
                                        @error('conversion_iof_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">O app do banco mostra 3,50%. Use 0% apenas se o saldo em dólar já foi abastecido com IOF.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="conversion_fixed_fee">Tarifa fixa por operação</label>
                                    <div class="input-group">
                                        <span class="input-group-text">US$</span>
                                        <input type="number" step="0.01" min="0" class="form-control tabular @error('conversion_fixed_fee') is-invalid @enderror" id="conversion_fixed_fee" name="conversion_fixed_fee" value="{{ old('conversion_fixed_fee', $conversionValues['conversion_fixed_fee']) }}" required>
                                        @error('conversion_fixed_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">Cobrança fixa do banco/cartão, se existir.</div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="conversion_buffer_percent">Margem de segurança</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" min="0" max="100" class="form-control tabular @error('conversion_buffer_percent') is-invalid @enderror" id="conversion_buffer_percent" name="conversion_buffer_percent" value="{{ old('conversion_buffer_percent', $conversionValues['conversion_buffer_percent']) }}" required>
                                        <span class="input-group-text">%</span>
                                        @error('conversion_buffer_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="form-text">Folga para absorver oscilações até o pagamento.</div>
                                </div>
                            </div>

                            <div class="hint-banner mt-3">
                                <i class="bi bi-calculator"></i>
                                <div>
                                    Fórmula: <span class="mono">valor × cotação</span> + spread; IOF incide sobre a base com spread;
                                    tarifa fixa entra depois; a margem incide sobre tudo. Cada etapa é arredondada em 2 casas.
                                </div>
                            </div>

                            <div class="hint-banner mt-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>
                                    Em 2026 o IOF de <strong>3,5%</strong> incide no cartão internacional <em>ou</em> no aporte da conta global —
                                    não nos dois. Se você abastece a conta em dólar e depois converte, o IOF já foi pago no aporte:
                                    mantenha 0% aqui para não contar duas vezes.
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar tarifas</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Cotação EUR → USD</span>
                        <span class="badge {{ ! empty($pair['stale']) ? 'text-bg-warning' : 'text-bg-success' }}">
                            {{ ! empty($pair['stale']) ? 'Última conhecida' : 'Ao vivo' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="surface-muted p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="currency-chip">€ EUR → US$ USD</span>
                                    <div class="text-muted-soft mt-1" style="font-size:.72rem;">1 euro compra</div>
                                </div>
                                <div class="text-end">
                                    <div class="fs-5 fw-bold tabular">{{ number_format((float) ($pair['rate'] ?? 0), 6, ',', '.') }}</div>
                                    <span class="text-muted-soft" style="font-size:.72rem;">dólares</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-muted-soft mt-3" style="font-size:.72rem;">
                            Fonte: {{ $pair['source'] ?? '—' }} · atualizado em
                            {{ isset($pair['updated_at']) ? \Illuminate\Support\Carbon::parse($pair['updated_at'])->format('d/m/Y H:i') : '—' }}.
                            Pares diretos da AwesomeAPI (gratuita, sem chave) com cache de 5 minutos e fallback pelo BRL.
                        </div>
                        <a href="{{ route('conversions.create') }}" class="btn btn-outline-secondary w-100 mt-3">
                            <i class="bi bi-plus-lg me-1"></i> Calcular uma conversão
                        </a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Como o sistema calcula</div>
                    <div class="card-body" style="font-size:.82rem;line-height:1.6;">
                        <p class="text-muted-soft mb-2">Exemplo com € 100,00 e as tarifas acima:</p>
                        <table class="breakdown">
                            <tr>
                                <td>Valor na cotação de mercado</td>
                                <td class="value">€ 100 × cotação</td>
                            </tr>
                            <tr>
                                <td>+ Spread</td>
                                <td class="value">sobre a base</td>
                            </tr>
                            <tr>
                                <td>+ IOF</td>
                                <td class="value">sobre base + spread</td>
                            </tr>
                            <tr>
                                <td>+ Tarifa fixa</td>
                                <td class="value">valor por operação</td>
                            </tr>
                            <tr>
                                <td>+ Margem</td>
                                <td class="value">sobre o subtotal</td>
                            </tr>
                            <tr class="total">
                                <td>Total a pagar</td>
                                <td class="value">em USD</td>
                            </tr>
                            <tr>
                                <td>VET (Valor Efetivo Total)</td>
                                <td class="value">total em R$ ÷ valor em €</td>
                            </tr>
                        </table>
                        <p class="text-muted-soft mt-3 mb-0">
                            O <strong>VET</strong> não é uma taxa para cadastrar: é o resultado. Ele mostra quantos reais
                            custou cada unidade da moeda de origem já com câmbio, spread, IOF, tarifa e margem — a mesma
                            taxa efetiva que o app do banco exibe. O sistema calcula e mostra o VET em cada conversão.
                        </p>
                        <p class="text-muted-soft mt-3 mb-0">
                            Depois do pagamento, use <strong>Conferir fatura</strong> na tela da conversão para comparar
                            o valor real cobrado e descobrir o custo efetivo do seu cartão.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'ovh' && $user->isDeveloperAdmin())
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Credenciais OVHcloud</span>
                        <span class="badge {{ $ovhConfigured ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $ovhConfigured ? 'Configurado' : 'Pendente' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="hint-banner mb-3">
                            <i class="bi bi-shield-lock"></i>
                            <div>As chaves são gravadas criptografadas na tabela <span class="mono">app_settings</span> (AES-256 via APP_KEY). Campos em branco preservam o valor atual.</div>
                        </div>

                        <form method="POST" action="{{ route('settings.ovh') }}" autocomplete="off">
                            @csrf
                            @method('PUT')

                            <div class="settings-section">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label" for="ovh_endpoint">Endpoint</label>
                                        <select class="form-select" id="ovh_endpoint" name="ovh_endpoint">
                                            @foreach (['ovh-eu' => 'ovh-eu · Europa', 'ovh-ca' => 'ovh-ca · Canadá', 'ovh-us' => 'ovh-us · Estados Unidos'] as $value => $label)
                                                <option value="{{ $value }}" @selected(\App\Models\AppSetting::get('ovh_endpoint', 'ovh-eu') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="ovh_application_key">Application Key (AK)</label>
                                        <input type="password" class="form-control mono" id="ovh_application_key" name="ovh_application_key" placeholder="{{ $masked['ovh_application_key'] === '—' ? 'Informe a Application Key' : 'Chave salva · '.$masked['ovh_application_key'] }}" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ovh_application_secret">Application Secret (AS)</label>
                                        <input type="password" class="form-control mono" id="ovh_application_secret" name="ovh_application_secret" placeholder="{{ $masked['ovh_application_secret'] === '—' ? 'Informe a Application Secret' : 'Chave salva · '.$masked['ovh_application_secret'] }}" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ovh_consumer_key">Consumer Key (CK)</label>
                                        <input type="password" class="form-control mono" id="ovh_consumer_key" name="ovh_consumer_key" placeholder="{{ $masked['ovh_consumer_key'] === '—' ? 'Informe a Consumer Key' : 'Chave salva · '.$masked['ovh_consumer_key'] }}" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>

                            <div class="settings-section">
                                <label class="form-label" for="ovh_v2_bearer_token">Token Bearer da API v2 (opcional)</label>
                                <textarea class="form-control mono" id="ovh_v2_bearer_token" name="ovh_v2_bearer_token" rows="3" data-editor="plain" placeholder="{{ $masked['ovh_v2_bearer_token'] }}"></textarea>
                                <div class="form-text">
                                    A API v2 (<span class="mono">api.eu.ovhcloud.com/v2</span>) usa OAuth2 e tokens de curta duração, gerados no
                                    <a href="https://api.eu.ovhcloud.com/console/" target="_blank" rel="noopener">console da OVHcloud</a>.
                                    A conciliação de faturas usa a API v1 com AK/AS/CK.
                                </div>
                            </div>

                            <div class="settings-section">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ovh_auto_import" name="ovh_auto_import" value="1" @checked(\App\Models\AppSetting::get('ovh_auto_import', '1') === '1')>
                                    <label class="form-check-label" for="ovh_auto_import" style="font-size:.84rem;">Importar faturas recentes automaticamente na sincronização</label>
                                </div>
                                <div class="form-check form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="clear_credentials" name="clear_credentials" value="1">
                                    <label class="form-check-label text-danger" for="clear_credentials" style="font-size:.8rem;">Limpar todas as credenciais salvas</label>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-outline-secondary" id="ovh-test" data-ovh-test data-url="{{ route('settings.ovh.test') }}">
                                    <i class="bi bi-plug me-1"></i> Testar conexão
                                </button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar credenciais</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Como obter as chaves</div>
                    <div class="card-body" style="font-size:.82rem;line-height:1.6;">
                        <p class="text-muted-soft">A API legada usa três chaves (AK, AS e CK), criadas em:</p>
                        <a href="https://api.ovh.com/createToken/" target="_blank" rel="noopener" class="btn btn-outline-secondary w-100 mb-3">
                            <i class="bi bi-key me-1"></i> Gerar AK/AS/CK
                        </a>
                        <p class="text-muted-soft mb-1">Permissões recomendadas:</p>
                        <div class="mono surface-muted p-2" style="font-size:.72rem;">
                            GET /me/*<br>
                            GET /me/bill/*<br>
                            GET /service/*<br>
                            GET /service
                        </div>
                        <p class="text-muted-soft mt-3 mb-0">O token do endpoint v2 expira rápido; gere um novo quando necessário. Nunca armazene o token no código ou no Git.</p>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Diagnóstico</div>
                    <div class="card-body" style="font-size:.82rem;">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Credenciais</span>
                            <span class="fw-semibold">{{ $ovhConfigured ? 'Completas' : 'Incompletas' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Endpoint</span>
                            <span class="fw-semibold mono">{{ \App\Models\AppSetting::get('ovh_endpoint', 'ovh-eu') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Último token v2</span>
                            <span class="fw-semibold">{{ \App\Models\AppSetting::get('ovh_v2_bearer_token') ? 'Salvo' : 'Não informado' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Sincronização</span>
                            <span class="fw-semibold">A cada 15 min</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'licenseboy' && $user->isDeveloperAdmin())
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Painel de cliente da LicenseBoy</div>
                    <div class="card-body">
                        <div class="hint-banner mb-3">
                            <i class="bi bi-info-circle-fill"></i>
                            <span>
                                A loja mostra o preço de hoje, mas o painel cobra o valor recorrente do seu contrato — que pode ser maior em serviços antigos.
                                Ao sincronizar, o valor do painel passa a ser o custo real da licença e a diferença para a loja fica sinalizada.
                            </span>
                        </div>

                        <form method="POST" action="{{ route('settings.licenseboy') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="licenseboy_panel_email">E-mail do painel</label>
                                    <input type="email" class="form-control" id="licenseboy_panel_email" name="licenseboy_panel_email" value="{{ old('licenseboy_panel_email', $licenseboyEmail) }}" placeholder="seu@email.com" autocomplete="username">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="licenseboy_panel_password">Senha do painel</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="licenseboy_panel_password" name="licenseboy_panel_password" placeholder="{{ $licenseboyConfigured ? $masked['licenseboy_panel_password'] : 'Senha do painel' }}" autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="licenseboy_panel_password" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="form-text">Em branco preserva a senha atual. Gravada criptografada no banco.</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-lock me-1"></i> Salvar credenciais
                                </button>
                            </div>
                        </form>

                        @if ($licenseboyConfigured)
                            <hr class="my-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">Sincronizar valores reais</div>
                                    <div class="text-muted-soft" style="font-size:.8rem;">
                                        Lê os serviços ativos e grava o valor recorrente de cada licença.
                                        @if ($licenseboySyncedAt)
                                            Última leitura: {{ \Illuminate\Support\Carbon::parse($licenseboySyncedAt)->format('d/m/Y H:i') }}.
                                        @else
                                            Ainda não sincronizado.
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('licenses.panel.sync') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-repeat me-1"></i> Sincronizar painel
                                    </button>
                                </form>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('settings.licenseboy') }}" class="mt-4" data-confirm="As credenciais do painel serão removidas do banco. A sincronização para de funcionar até você informar novamente." data-confirm-title="Remover credenciais?" data-confirm-button="Remover" data-confirm-variant="danger">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="clear_licenseboy_credentials" value="1">
                            <button type="submit" class="btn btn-ghost btn-sm text-danger">
                                <i class="bi bi-trash3 me-1"></i> Limpar credenciais
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Como ler o preço</div>
                    <div class="card-body">
                        <div class="settings-section">
                            <div class="fw-semibold mb-1">Preço da loja</div>
                            <p class="text-muted-soft mb-0" style="font-size:.82rem;">Valor público de um pedido novo hoje. É o que aparece em <strong>Preços da loja</strong> e no vínculo do catálogo.</p>
                        </div>
                        <div class="settings-section">
                            <div class="fw-semibold mb-1">Preço do painel</div>
                            <p class="text-muted-soft mb-0" style="font-size:.82rem;">Valor recorrente do seu contrato. Em serviços antigos ele pode ficar acima da loja; o WHMCS não permite editar esse valor pelo painel do cliente — somente a LicenseBoy altera.</p>
                        </div>
                        <div class="settings-section">
                            <div class="fw-semibold mb-1">Qual vale no sistema</div>
                            <p class="text-muted-soft mb-0" style="font-size:.82rem;">O painel prevalece: é ele que debita do seu cartão. A loja serve de referência para negociar a redução com o suporte.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'rules' && $user->isDeveloperAdmin())
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">Regras de sincronização</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('settings.rules') }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="rate_variation_threshold">Limiar de alerta</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" class="form-control tabular" id="rate_variation_threshold" name="rate_variation_threshold" value="{{ \App\Models\AppSetting::get('rate_variation_threshold', '1.00') }}" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">Notifica quando a variação do total ultrapassar este percentual.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="ovh_import_days">Janela de importação</label>
                                    <div class="input-group">
                                        <input type="number" min="1" max="365" class="form-control tabular" id="ovh_import_days" name="ovh_import_days" value="{{ \App\Models\AppSetting::get('ovh_import_days', '365') }}" required>
                                        <span class="input-group-text">dias</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="ovh_import_limit">Máximo de faturas</label>
                                    <input type="number" min="1" max="100" class="form-control tabular" id="ovh_import_limit" name="ovh_import_limit" value="{{ \App\Models\AppSetting::get('ovh_import_limit', '100') }}" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="notify_email">E-mail de cópia dos alertas</label>
                                    <input type="email" class="form-control" id="notify_email" name="notify_email" value="{{ \App\Models\AppSetting::get('notify_email', 'contato@kdkhost.com.br') }}" placeholder="financeiro@kdkhost.com.br">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="ovh_auto_import" name="ovh_auto_import" value="1" @checked(\App\Models\AppSetting::get('ovh_auto_import', '1') === '1')>
                                        <label class="form-check-label" for="ovh_auto_import" style="font-size:.8rem;">Importar automaticamente</label>
                                    </div>
                                </div>
                            </div>

                            <div class="hint-banner mt-3">
                                <i class="bi bi-clock-history"></i>
                                <div>O comando <span class="mono">finance:sync-invoices</span> roda a cada 15 minutos pelo scheduler. Em produção configure <span class="mono">* * * * * php artisan schedule:run</span>.</div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar regras</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">Segurança e acessos</div>
                    <div class="card-body" style="font-size:.84rem;line-height:1.6;">
                        <p class="text-muted-soft">Os segredos são criptografados com a <span class="mono">APP_KEY</span> do servidor. Sem ela, não é possível descriptografar as credenciais.</p>
                        <p class="text-muted-soft">Nunca versione a <span class="mono">APP_KEY</span>, o arquivo <span class="mono">credentials.local.json</span> ou tokens v2.</p>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary w-100"><i class="bi bi-people me-1"></i> Gerenciar usuários e perfis</a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Configurações no banco</div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 320px;">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>Chave</th><th>Grupo</th><th>Valor</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($settings as $setting)
                                        <tr>
                                            <td class="mono" style="font-size:.72rem;">{{ $setting->key }}</td>
                                            <td class="text-muted-soft" style="font-size:.72rem;">{{ $setting->group }}</td>
                                            <td class="mono text-truncate" style="font-size:.72rem;max-width:120px;">
                                                {{ $setting->is_secret ? ($masked[$setting->key] ?? '••••••••') : ($setting->value ?: '—') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @endif

    @if ($activeTab === 'mail' && $user->isDeveloperAdmin())
        <div class="row g-3">
            <div class="col-xl-8">
                <form method="POST" action="{{ route('settings.mail') }}" autocomplete="off">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center gap-2">
                            <span>Servidor SMTP</span>
                            <span class="badge {{ $mailConfigured ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $mailConfigured ? 'Pronto para enviar' : 'Não configurado' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="hint-banner mb-3">
                                <i class="bi bi-shield-lock"></i>
                                <div>
                                    Tudo o que está aqui fica na tabela <span class="mono">app_settings</span>, com a senha criptografada pela
                                    <span class="mono">APP_KEY</span>. Nenhuma credencial é lida do <span class="mono">.env</span>.
                                    Campos de senha em branco preservam o valor atual.
                                </div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="mail_enabled" name="mail_enabled" value="1" @checked(old('mail_enabled', $mailSettings['mail_enabled']) === '1')>
                                <label class="form-check-label" for="mail_enabled" style="font-size:.86rem;">
                                    Ativar envio de e-mails por SMTP
                                    <span class="text-muted-soft d-block" style="font-size:.76rem;">Desligado, as mensagens vão apenas para o log — útil em homologação.</span>
                                </label>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label" for="mail_host">Servidor SMTP</label>
                                    <input type="text" class="form-control mono @error('mail_host') is-invalid @enderror" id="mail_host" name="mail_host" value="{{ old('mail_host', $mailSettings['mail_host']) }}" placeholder="mail.seudominio.com" inputmode="url">
                                    @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_port">Porta</label>
                                    <input type="number" min="1" max="65535" class="form-control tabular @error('mail_port') is-invalid @enderror" id="mail_port" name="mail_port" value="{{ old('mail_port', $mailSettings['mail_port']) }}" required>
                                    @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_encryption">Criptografia</label>
                                    <select class="form-select" id="mail_encryption" name="mail_encryption">
                                        @foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Sem criptografia'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('mail_encryption', $mailSettings['mail_encryption']) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_username">Usuário SMTP</label>
                                    <input type="text" class="form-control mono" id="mail_username" name="mail_username" value="{{ old('mail_username', $mailSettings['mail_username']) }}" autocomplete="off">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_password">Senha SMTP</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="mail_password" name="mail_password" placeholder="{{ $mailMaskedPassword === '—' ? 'Senha do servidor' : $mailMaskedPassword }}" autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="mail_password" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
                                    </div>
                                    <div class="form-text">Em branco preserva a senha gravada.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="mail_timeout">Tempo limite</label>
                                    <div class="input-group">
                                        <input type="number" min="3" max="120" class="form-control tabular" id="mail_timeout" name="mail_timeout" value="{{ old('mail_timeout', $mailSettings['mail_timeout']) }}" required>
                                        <span class="input-group-text">s</span>
                                    </div>
                                </div>
                                <div class="col-md-8 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="mail_auth" name="mail_auth" value="1" @checked(old('mail_auth', $mailSettings['mail_auth']) === '1')>
                                        <label class="form-check-label" for="mail_auth" style="font-size:.82rem;">O servidor exige autenticação (usuário e senha)</label>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="clear_mail_password" name="clear_mail_password" value="1">
                                        <label class="form-check-label text-danger" for="clear_mail_password" style="font-size:.78rem;">Limpar senha salva</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Remetente e respostas</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_from_address">E-mail de envio</label>
                                    <input type="email" class="form-control @error('mail_from_address') is-invalid @enderror" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', $mailSettings['mail_from_address']) }}" placeholder="alertas@seudominio.com">
                                    @error('mail_from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_from_name">Nome do remetente</label>
                                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', $mailSettings['mail_from_name']) }}" placeholder="KDK Finance">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_reply_to_address">Responder para (opcional)</label>
                                    <input type="email" class="form-control" id="mail_reply_to_address" name="mail_reply_to_address" value="{{ old('mail_reply_to_address', $mailSettings['mail_reply_to_address']) }}" placeholder="financeiro@seudominio.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="mail_reply_to_name">Nome da resposta</label>
                                    <input type="text" class="form-control" id="mail_reply_to_name" name="mail_reply_to_name" value="{{ old('mail_reply_to_name', $mailSettings['mail_reply_to_name']) }}" placeholder="Financeiro KDKHost">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Tema dos e-mails</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_brand">Marca exibida</label>
                                    <input type="text" class="form-control @error('theme_brand') is-invalid @enderror" id="theme_brand" name="theme_brand" value="{{ old('theme_brand', $mailTheme['theme_brand']) }}" required>
                                    @error('theme_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="theme_logo_enabled" name="theme_logo_enabled" value="1" @checked(old('theme_logo_enabled', $mailTheme['theme_logo_enabled']) === '1')>
                                        <label class="form-check-label" for="theme_logo_enabled" style="font-size:.82rem;">Mostrar selo com as iniciais da marca no cabeçalho</label>
                                    </div>
                                </div>

                                @foreach ([
                                    'theme_primary' => 'Cor principal (botões e links)',
                                    'theme_accent' => 'Cor de destaque (títulos)',
                                    'theme_background' => 'Fundo externo',
                                    'theme_text' => 'Texto do conteúdo',
                                    'theme_muted' => 'Texto secundário',
                                ] as $field => $label)
                                    <div class="col-6 col-md-4">
                                        <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                        <div class="input-group">
                                            <input type="color" class="form-control form-control-color" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $mailTheme[$field]) }}" title="{{ $label }}" required>
                                            <span class="input-group-text mono" style="font-size:.72rem;">{{ old($field, $mailTheme[$field]) }}</span>
                                        </div>
                                        @error($field)<div class="text-danger" style="font-size:.75rem;">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach

                                <div class="col-md-6">
                                    <label class="form-label" for="theme_footer">Rodapé</label>
                                    <textarea class="form-control" id="theme_footer" name="theme_footer" rows="3" maxlength="2500">{{ old('theme_footer', $mailTheme['theme_footer']) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_signature">Assinatura</label>
                                    <textarea class="form-control" id="theme_signature" name="theme_signature" rows="3" maxlength="1200">{{ old('theme_signature', $mailTheme['theme_signature']) }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_support_email">E-mail de suporte</label>
                                    <input type="email" class="form-control" id="theme_support_email" name="theme_support_email" value="{{ old('theme_support_email', $mailTheme['theme_support_email']) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="theme_support_url">Site de suporte</label>
                                    <input type="url" class="form-control" id="theme_support_url" name="theme_support_url" value="{{ old('theme_support_url', $mailTheme['theme_support_url']) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="theme_company">Dados da empresa no rodapé</label>
                                    <input type="text" class="form-control" id="theme_company" name="theme_company" value="{{ old('theme_company', $mailTheme['theme_company']) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Alertas automáticos</div>
                        <div class="card-body">
                            <label class="form-label" for="mail_alert_recipients">Destinatários dos alertas</label>
                            <input type="text" class="form-control @error('mail_alert_recipients') is-invalid @enderror" id="mail_alert_recipients" name="mail_alert_recipients" value="{{ old('mail_alert_recipients', $mailSettings['mail_alert_recipients']) }}" placeholder="financeiro@seudominio.com, socio@seudominio.com">
                            @error('mail_alert_recipients')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Separe por vírgula. Se ficar vazio, os alertas vão para todo desenvolvedor e gestor financeiro ativo.
                                As mensagens enviadas estão em <a href="{{ route('email-templates.index') }}">Modelos de e-mail</a>.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar SMTP e tema</button>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Diagnóstico</span>
                        <span class="badge {{ $mailConfigured ? 'text-bg-success' : 'text-bg-warning' }}">{{ $mailDriver }}</span>
                    </div>
                    <div class="card-body" style="font-size:.82rem;">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Situação</span>
                            <span class="fw-semibold">{{ $mailConfigured ? 'SMTP ativo' : 'E-mails apenas no log' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Servidor</span>
                            <span class="fw-semibold mono">{{ $mailSettings['mail_host'] !== '' ? $mailSettings['mail_host'].':'.$mailSettings['mail_port'] : '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Remetente</span>
                            <span class="fw-semibold text-truncate" style="max-width:150px;">{{ $mailSettings['mail_from_address'] !== '' ? $mailSettings['mail_from_address'] : '—' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Último teste</span>
                            <span class="fw-semibold">
                                @if ($mailSettings['mail_last_test_at'])
                                    {{ \Illuminate\Support\Carbon::parse($mailSettings['mail_last_test_at'])->format('d/m/Y H:i') }}
                                @else
                                    Nunca testado
                                @endif
                            </span>
                        </div>
                        @if ($mailSettings['mail_last_test_message'])
                            <div class="hint-banner mt-2" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
                                <i class="bi bi-info-circle"></i>
                                <div style="font-size:.78rem;">{{ $mailSettings['mail_last_test_message'] }}</div>
                            </div>
                        @endif

                        <hr class="my-3">

                        <form method="POST" action="{{ route('settings.mail.test') }}">
                            @csrf
                            <label class="form-label" for="test_email">Enviar teste para</label>
                            <input type="email" class="form-control mb-2" id="test_email" name="test_email" value="{{ old('test_email', auth()->user()->email) }}" required>
                            <button type="submit" class="btn btn-outline-secondary w-100" @disabled(! $mailConfigured)>
                                <i class="bi bi-send me-1"></i> Enviar teste
                            </button>
                            @if (! $mailConfigured)
                                <div class="form-text">Salve o SMTP ativo, com servidor e remetente, para liberar o teste.</div>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Modelos de e-mail</span>
                        <span class="badge text-bg-light border">8 modelos</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted-soft mb-3" style="font-size:.8rem;">
                            Textos de fatura a vencer, vencida, pagamento, licença, falha de cron, boas-vindas e teste.
                            Todos usam o tema acima e os destinatários dos alertas.
                        </p>
                        <a href="{{ route('email-templates.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-envelope-paper me-1"></i> Gerenciar modelos
                        </a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Prévia responsiva</span>
                        <a href="{{ route('settings.mail.preview') }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Abrir
                        </a>
                    </div>
                    <div class="card-body p-2">
                        <iframe src="{{ route('settings.mail.preview') }}" title="Prévia do e-mail" loading="lazy" style="width:100%;height:560px;border:1px solid var(--bs-border-color);border-radius:10px;background:#fff;"></iframe>
                        <div class="text-muted-soft mt-2" style="font-size:.72rem;">A prévia usa o tema já salvo. Salve novamente para refletir as alterações.</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeTab === 'security' && $user->isDeveloperAdmin())
        <div class="row g-3">
            <div class="col-xl-8">
                <form method="POST" action="{{ route('settings.security') }}">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Conexão e cabeçalhos</span>
                            <span class="badge {{ \App\Support\Settings\SecuritySettings::isHttpsConfigured() ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ \App\Support\Settings\SecuritySettings::isHttpsConfigured() ? 'HTTPS configurado' : 'HTTP' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="hint-banner mb-3">
                                <i class="bi bi-hdd-network"></i>
                                <div>
                                    Estas opções substituem o <span class="mono">.env</span> (<span class="mono">FORCE_HTTPS</span>,
                                    <span class="mono">TRUST_HOSTS</span>, <span class="mono">TRUSTED_PROXIES</span> e política de sessão).
                                    Valem na próxima requisição, sem reiniciar o PHP.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_force_https" name="security_force_https" value="1" @checked(old('security_force_https', $securitySettings['security_force_https']) === '1')>
                                        <label class="form-check-label" for="security_force_https" style="font-size:.84rem;">Forçar HTTPS (301)</label>
                                    </div>
                                    <div class="form-text">Em local/teste o redirecionamento é ignorado para não quebrar o servidor embutido.</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_headers_enabled" name="security_headers_enabled" value="1" @checked(old('security_headers_enabled', $securitySettings['security_headers_enabled']) === '1')>
                                        <label class="form-check-label" for="security_headers_enabled" style="font-size:.84rem;">Enviar cabeçalhos de proteção</label>
                                    </div>
                                    <div class="form-text">Nosniff, frame, referrer, permissions, COOP e CORP.</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_csp_enabled" name="security_csp_enabled" value="1" @checked(old('security_csp_enabled', $securitySettings['security_csp_enabled']) === '1')>
                                        <label class="form-check-label" for="security_csp_enabled" style="font-size:.84rem;">Content-Security-Policy com nonce</label>
                                    </div>
                                    <div class="form-text">Protege contra injeção de scripts no navegador.</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_hsts_enabled" name="security_hsts_enabled" value="1" @checked(old('security_hsts_enabled', $securitySettings['security_hsts_enabled']) === '1')>
                                        <label class="form-check-label" for="security_hsts_enabled" style="font-size:.84rem;">HSTS em conexões HTTPS</label>
                                    </div>
                                    <div class="form-text">Só é enviado quando a requisição já está em HTTPS.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="security_hsts_max_age">Validade do HSTS</label>
                                    <div class="input-group">
                                        <input type="number" min="1" max="730" class="form-control tabular" id="security_hsts_max_age" name="security_hsts_max_age" value="{{ old('security_hsts_max_age', $securitySettings['security_hsts_max_age']) }}" required>
                                        <span class="input-group-text">dias</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="security_frame_options">Enquadramento</label>
                                    <select class="form-select" id="security_frame_options" name="security_frame_options">
                                        <option value="SAMEORIGIN" @selected(old('security_frame_options', $securitySettings['security_frame_options']) === 'SAMEORIGIN')>SAMEORIGIN (só o próprio site)</option>
                                        <option value="DENY" @selected(old('security_frame_options', $securitySettings['security_frame_options']) === 'DENY')>DENY (bloqueia tudo)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_no_store" name="security_no_store" value="1" @checked(old('security_no_store', $securitySettings['security_no_store']) === '1')>
                                        <label class="form-check-label" for="security_no_store" style="font-size:.82rem;">No-store para usuários logados</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="settings-section mb-0">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="security_trust_hosts" name="security_trust_hosts" value="1" @checked(old('security_trust_hosts', $securitySettings['security_trust_hosts']) === '1')>
                                            <label class="form-check-label" for="security_trust_hosts" style="font-size:.84rem;">Aceitar apenas os hosts listados (anti Host header spoofing)</label>
                                        </div>
                                        <label class="form-label mt-3" for="security_allowed_hosts">Hosts permitidos</label>
                                        <textarea class="form-control mono" id="security_allowed_hosts" name="security_allowed_hosts" rows="2" data-editor="plain" placeholder="kdkhost.com.br, www.kdkhost.com.br, *.kdkhost.com.br">{{ old('security_allowed_hosts', $securitySettings['security_allowed_hosts']) }}</textarea>
                                        <div class="form-text">Separe por vírgula ou linha. Aceita curinga <span class="mono">*.dominio.com</span>. Vazio usa o host do APP_URL.</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="security_trusted_proxies">Proxies/CDN confiáveis</label>
                                    <textarea class="form-control mono" id="security_trusted_proxies" name="security_trusted_proxies" rows="2" data-editor="plain" placeholder="IP do Cloudflare ou do balanceador">{{ old('security_trusted_proxies', $securitySettings['security_trusted_proxies']) }}</textarea>
                                    <div class="form-text">Somente IPs/CIDRs que realmente terminam HTTPS. Vazio ignora cabeçalhos de proxy.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Sessão e login</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="security_session_lifetime">Duração da sessão</label>
                                    <div class="input-group">
                                        <input type="number" min="5" max="43200" class="form-control tabular" id="security_session_lifetime" name="security_session_lifetime" value="{{ old('security_session_lifetime', $securitySettings['security_session_lifetime']) }}" required>
                                        <span class="input-group-text">min</span>
                                    </div>
                                    <div class="form-text">Tempo ocioso até exigir login novamente.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="security_session_same_site">SameSite do cookie</label>
                                    <select class="form-select" id="security_session_same_site" name="security_session_same_site">
                                        @foreach (['lax' => 'Lax (recomendado)', 'strict' => 'Strict', 'none' => 'None (exige HTTPS)'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('security_session_same_site', $securitySettings['security_session_same_site']) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_session_secure" name="security_session_secure" value="1" @checked(old('security_session_secure', $securitySettings['security_session_secure']) === '1')>
                                        <label class="form-check-label" for="security_session_secure" style="font-size:.82rem;">Cookie Secure em HTTPS</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_session_encrypt" name="security_session_encrypt" value="1" @checked(old('security_session_encrypt', $securitySettings['security_session_encrypt']) === '1')>
                                        <label class="form-check-label" for="security_session_encrypt" style="font-size:.82rem;">Criptografar a sessão no banco</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="security_session_expire_on_close" name="security_session_expire_on_close" value="1" @checked(old('security_session_expire_on_close', $securitySettings['security_session_expire_on_close']) === '1')>
                                        <label class="form-check-label" for="security_session_expire_on_close" style="font-size:.82rem;">Encerrar ao fechar o navegador</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="security_login_max_attempts">Tentativas de login antes do bloqueio</label>
                                    <input type="number" min="3" max="50" class="form-control tabular" id="security_login_max_attempts" name="security_login_max_attempts" value="{{ old('security_login_max_attempts', $securitySettings['security_login_max_attempts']) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="security_login_decay_seconds">Duração do bloqueio</label>
                                    <div class="input-group">
                                        <input type="number" min="10" max="86400" class="form-control tabular" id="security_login_decay_seconds" name="security_login_decay_seconds" value="{{ old('security_login_decay_seconds', $securitySettings['security_login_decay_seconds']) }}" required>
                                        <span class="input-group-text">segundos</span>
                                    </div>
                                </div>
                            </div>

                            <div class="hint-banner mt-3" style="background: var(--kdk-amber-soft); border-color: color-mix(in srgb, var(--kdk-amber) 28%, transparent);">
                                <i class="bi bi-exclamation-triangle"></i>
                                <div>Ao ligar a criptografia da sessão, as sessões abertas deixam de ser legíveis e todos precisarão entrar novamente. O login atual continua válido até a próxima requisição.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Política de senhas</div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label" for="security_password_min">Tamanho mínimo</label>
                                    <div class="input-group">
                                        <input type="number" min="8" max="72" class="form-control tabular" id="security_password_min" name="security_password_min" value="{{ old('security_password_min', $securitySettings['security_password_min']) }}" required>
                                        <span class="input-group-text">caracteres</span>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="d-flex flex-wrap gap-3 mb-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="security_password_mixed_case" name="security_password_mixed_case" value="1" @checked(old('security_password_mixed_case', $securitySettings['security_password_mixed_case']) === '1')>
                                            <label class="form-check-label" for="security_password_mixed_case" style="font-size:.82rem;">Maiúscula e minúscula</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="security_password_numbers" name="security_password_numbers" value="1" @checked(old('security_password_numbers', $securitySettings['security_password_numbers']) === '1')>
                                            <label class="form-check-label" for="security_password_numbers" style="font-size:.82rem;">Número</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="security_password_symbols" name="security_password_symbols" value="1" @checked(old('security_password_symbols', $securitySettings['security_password_symbols']) === '1')>
                                            <label class="form-check-label" for="security_password_symbols" style="font-size:.82rem;">Símbolo</label>
                                        </div>
                                    </div>
                                    <div class="form-text">Vale para criação de usuários, troca no perfil e redefinições. Mudanças não afetam senhas já existentes.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i> Salvar segurança</button>
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">Estado atual</div>
                    <div class="card-body" style="font-size:.82rem;">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Endereço</span>
                            <span class="fw-semibold mono text-truncate" style="max-width:170px;">{{ config('app.url') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Requisição</span>
                            <span class="fw-semibold">{{ request()->isSecure() ? 'HTTPS' : 'HTTP' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">CSP</span>
                            <span class="fw-semibold">{{ $securitySettings['security_csp_enabled'] === '1' ? 'Ativa' : 'Desligada' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Hosts</span>
                            <span class="fw-semibold">{{ $securitySettings['security_trust_hosts'] === '1' ? 'Restritos' : 'Sem restrição' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Driver de sessão</span>
                            <span class="fw-semibold mono">{{ config('session.driver') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Sessões registradas</span>
                            <span class="fw-semibold">{{ $sessions->count() }}</span>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Sessões ativas</span>
                        <span class="badge text-bg-secondary">{{ $sessions->count() }}</span>
                    </div>
                    <div class="card-body">
                        @forelse ($sessions as $session)
                            <div class="surface-muted p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold" style="font-size:.84rem;">{{ $session['agent'] }}</div>
                                    @if ($session['current'])
                                        <span class="badge text-bg-success">Atual</span>
                                    @endif
                                </div>
                                <div class="text-muted-soft mt-1" style="font-size:.74rem;">
                                    IP {{ $session['ip'] }} · {{ $session['last_activity']->diffForHumans() }}
                                </div>
                                <div class="text-muted-soft" style="font-size:.72rem;">
                                    Último acesso em {{ $session['last_activity']->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @empty
                            <div class="text-muted-soft" style="font-size:.82rem;">Nenhuma sessão registrada no banco.</div>
                        @endforelse

                        <form method="POST" action="{{ route('settings.sessions.revoke') }}" class="mt-3" data-confirm="As outras sessões serão encerradas imediatamente. Quem estiver nelas precisará entrar novamente." data-confirm-title="Encerrar outras sessões?" data-confirm-button="Encerrar" data-confirm-variant="danger">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-box-arrow-right me-1"></i> Encerrar outras sessões
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">Boas práticas</div>
                    <div class="card-body text-muted-soft" style="font-size:.8rem;line-height:1.6;">
                        <p class="mb-2">Mantenha o HTTPS forçado, o HSTS ligado e o CSP ativo. Restrinja hosts apenas depois de conferir o domínio e o <span class="mono">www</span>.</p>
                        <p class="mb-0">Se o painel estiver atrás de Cloudflare, cadastre os IPs do proxy em <strong>Proxies confiáveis</strong> para o IP real aparecer nos registros.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
    </div>

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            // A troca acontece aqui, ainda durante a leitura do HTML: nenhum clique
            // chega a recarregar a página (e o menu lateral não se mexe).
            (function () {
                const tabs = document.querySelector('[data-settings-tabs]');
                const panel = document.getElementById('settings-panel');

                if (!tabs || !panel) {
                    return;
                }

                let pending = null;
                let requestId = 0;

                const paintTabs = (key) => {
                    tabs.querySelectorAll('[data-settings-tab]').forEach((tab) => {
                        const active = tab.dataset.settingsTab === key;
                        tab.classList.toggle('active', active);

                        if (active) {
                            tab.setAttribute('aria-current', 'page');
                        } else {
                            tab.removeAttribute('aria-current');
                        }
                    });
                };

                const loadTab = async (url, mode) => {
                    pending?.abort();
                    pending = new AbortController();
                    const id = ++requestId;

                    panel.setAttribute('aria-busy', 'true');
                    tabs.classList.add('is-loading');

                    try {
                        const response = await fetch(url, {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            signal: pending.signal,
                        });

                        if (!response.ok) {
                            throw new Error('Resposta ' + response.status);
                        }

                        // HTML da própria aplicação, renderizado pelo Blade: seguro para injetar.
                        const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const next = parsed.getElementById('settings-panel');

                        if (!next) {
                            throw new Error('Painel ausente na resposta');
                        }

                        panel.innerHTML = next.innerHTML;

                        const target = new URL(url, window.location.origin);
                        const key = target.searchParams.get('tab') || 'taxes';
                        paintTabs(key);

                        if (mode === 'push') {
                            window.history.pushState({ settingsTab: key }, '', target.href);
                        } else {
                            window.history.replaceState({ settingsTab: key }, '', target.href);
                        }

                        document.dispatchEvent(new CustomEvent('finance:settings-tab', { detail: { tab: key } }));
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        // Sem resposta utilizável, segue o caminho normal do link.
                        window.location.href = url;
                    } finally {
                        if (id === requestId) {
                            panel.removeAttribute('aria-busy');
                            tabs.classList.remove('is-loading');
                        }
                    }
                };

                tabs.addEventListener('click', (event) => {
                    const tab = event.target.closest('[data-settings-tab]');

                    if (!tab || event.defaultPrevented || event.button !== 0
                        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                        || tab.target === '_blank') {
                        return;
                    }

                    event.preventDefault();

                    if (tab.classList.contains('active')) {
                        return;
                    }

                    loadTab(tab.href, 'push');
                });

                window.addEventListener('popstate', () => {
                    loadTab(window.location.href, 'replace');
                });
            })();
        </script>
    @endpush
@endsection
