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

    <ul class="nav nav-pills gap-2 mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'taxes' ? 'active' : '' }}" href="{{ route('settings.index', ['tab' => 'taxes']) }}">
                <i class="bi bi-percent me-1"></i> Câmbio e tributos
            </a>
        </li>
        @if ($user->isDeveloperAdmin())
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'ovh' ? 'active' : '' }}" href="{{ route('settings.index', ['tab' => 'ovh']) }}">
                    <i class="bi bi-cloud me-1"></i> OVHcloud
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'rules' ? 'active' : '' }}" href="{{ route('settings.index', ['tab' => 'rules']) }}">
                    <i class="bi bi-sliders me-1"></i> Regras
                </a>
            </li>
        @endif
    </ul>

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
                        <div class="text-muted-soft" style="font-size:.72rem;">
                            Fonte: {{ $rates['USD']['source'] }} · atualizado em {{ \Illuminate\Support\Carbon::parse($rates['USD']['updated_at'])->format('d/m/Y H:i') }}.
                            Cache de 15 minutos com fallback para a última cotação conhecida.
                        </div>
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
                                        <input type="password" class="form-control mono" id="ovh_application_key" name="ovh_application_key" placeholder="{{ $masked['ovh_application_key'] }}" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ovh_application_secret">Application Secret (AS)</label>
                                        <input type="password" class="form-control mono" id="ovh_application_secret" name="ovh_application_secret" placeholder="{{ $masked['ovh_application_secret'] }}" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ovh_consumer_key">Consumer Key (CK)</label>
                                        <input type="password" class="form-control mono" id="ovh_consumer_key" name="ovh_consumer_key" placeholder="{{ $masked['ovh_consumer_key'] }}" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>

                            <div class="settings-section">
                                <label class="form-label" for="ovh_v2_bearer_token">Token Bearer da API v2 (opcional)</label>
                                <textarea class="form-control mono" id="ovh_v2_bearer_token" name="ovh_v2_bearer_token" rows="3" placeholder="{{ $masked['ovh_v2_bearer_token'] }}"></textarea>
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
                                <button type="button" class="btn btn-outline-secondary" id="ovh-test">
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
                                        <input type="number" min="1" max="365" class="form-control tabular" id="ovh_import_days" name="ovh_import_days" value="{{ \App\Models\AppSetting::get('ovh_import_days', '60') }}" required>
                                        <span class="input-group-text">dias</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="ovh_import_limit">Máximo de faturas</label>
                                    <input type="number" min="1" max="100" class="form-control tabular" id="ovh_import_limit" name="ovh_import_limit" value="{{ \App\Models\AppSetting::get('ovh_import_limit', '25') }}" required>
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

    @push('scripts')
        <script>
            document.getElementById('ovh-test')?.addEventListener('click', async function () {
                const button = this;
                const original = button.innerHTML;
                const token = document.querySelector('meta[name="csrf-token"]').content;

                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testando...';

                try {
                    const response = await fetch(@json(route('settings.ovh.test')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();

                    if (response.ok) {
                        window.toastr.success(data.message, 'Conexão validada');
                    } else {
                        window.toastr.error(data.message || 'Falha ao validar as credenciais.', 'Falha na conexão');
                    }
                } catch (error) {
                    window.toastr.error('Não foi possível contatar o servidor.', 'Falha na conexão');
                } finally {
                    button.disabled = false;
                    button.innerHTML = original;
                }
            });
        </script>
    @endpush
@endsection
