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

@section('title', $license->exists ? 'Editar serviço S3' : 'Novo serviço S3')
@section('eyebrow', 'IDrive e2 (S3)')
@section('subtitle', $license->exists ? 'Atualize os dados de cobrança, endpoint e renovação do armazenamento S3.' : 'Cadastre um plano ou bucket contratado no IDrive e2 (S3) para controlar as faturas.')

@section('actions')
    <a href="{{ $license->exists ? route('s3.show', $license) : route('s3.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ $license->exists ? route('s3.update', $license) : route('s3.store') }}">
        @csrf
        @if ($license->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">Identificação do Serviço S3</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="license_catalog_item_id">Plano de referência (Tabela IDrive e2)</label>
                                <select class="form-select @error('license_catalog_item_id') is-invalid @enderror" id="license_catalog_item_id" name="license_catalog_item_id">
                                    <option value="">— Preço personalizado (sem vínculo com a tabela) —</option>
                                    @foreach ($catalogItems as $group)
                                        <optgroup label="{{ $group['label'] }}">
                                            @foreach ($group['items'] as $item)
                                                <option
                                                    value="{{ $item->id }}"
                                                    data-product="{{ $item->name }}"
                                                    data-name="{{ $item->name }}"
                                                    data-price="{{ number_format((float) $item->price, 2, '.', '') }}"
                                                    data-currency="{{ $item->currency }}"
                                                    data-cycle="{{ $item->billing_cycle }}"
                                                    @selected((string) old('license_catalog_item_id', $selectedCatalogId) === (string) $item->id)
                                                >{{ $item->name }} · {{ $item->priceLabel() }}/{{ $item->cycleLabel() }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('license_catalog_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">
                                    Selecione um plano de armazenamento S3 da tabela oficial ou mantenha manual. Veja a <a href="{{ route('s3.prices') }}">tabela completa de planos S3</a>.
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label" for="name">Identificação do serviço / Bucket</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $license->name) }}" required maxlength="150" placeholder="Ex.: IDrive e2 S3 · Backups KDKHost (1 TB)">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="status">Situação</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                    @foreach (\App\Models\LicenseService::statuses() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('status', $license->status) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="product">Plano / Capacidade</label>
                                <input type="text" class="form-control @error('product') is-invalid @enderror" id="product" name="product" value="{{ old('product', $license->product ?: 'IDrive e2 Cloud Storage S3') }}" required maxlength="80" placeholder="IDrive e2 S3 · 1 TB">
                                @error('product')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="server_name">Endpoint S3 / Região</label>
                                <input type="text" class="form-control mono @error('server_name') is-invalid @enderror" id="server_name" name="server_name" value="{{ old('server_name', $license->server_name) }}" maxlength="190" placeholder="s3.us-east-1.idrivee2.com">
                                @error('server_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="license_key">Access Key ID / Credencial do Bucket (opcional)</label>
                                <input type="password" class="form-control mono @error('license_key') is-invalid @enderror" id="license_key" name="license_key" value="{{ old('license_key') }}" maxlength="500" autocomplete="new-password" placeholder="{{ $license->license_key ? 'Chave salva · '.$license->maskedKey() : 'Opcional — criptografada com APP_KEY' }}">
                                @error('license_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Cobrança e Renovação</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="unit_price">Valor de referência (tabela)</label>
                                <input type="number" step="0.01" min="0" max="999999.99" class="form-control @error('unit_price') is-invalid @enderror" id="unit_price" name="unit_price" value="{{ old('unit_price', $license->unit_price) }}" required>
                                @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="currency">Moeda</label>
                                <select class="form-select @error('currency') is-invalid @enderror" id="currency" name="currency" required>
                                    @foreach (['USD', 'EUR'] as $currency)
                                        <option value="{{ $currency }}" @selected(old('currency', $license->currency ?? 'USD') === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="quantity">Quantidade</label>
                                <input type="number" min="1" max="1000" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity" value="{{ old('quantity', $license->quantity ?? 1) }}" required>
                                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="billing_cycle">Ciclo</label>
                                <select class="form-select @error('billing_cycle') is-invalid @enderror" id="billing_cycle" name="billing_cycle" required>
                                    @foreach (\App\Models\LicenseService::cycles() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('billing_cycle', $license->billing_cycle ?? 'monthly') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <div class="border-top pt-3 mt-1">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold">Valor real cobrado no console IDrive e2</div>
                                            <div class="text-muted-soft" style="font-size:.76rem;">
                                                Informe o valor efetivamente cobrado na assinatura do console IDrive e2 (inclusive caso tenha excedente de armazenamento ou renovação sem desconto de 1º ano).
                                            </div>
                                        </div>
                                        @if ($license->panel_synced_at)
                                            <span class="badge text-bg-light border">Lido em {{ $license->panel_synced_at->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="panel_price">Valor cobrado no console</label>
                                <input type="number" step="0.01" min="0" max="999999.99" class="form-control @error('panel_price') is-invalid @enderror" id="panel_price" name="panel_price" value="{{ old('panel_price', $license->panel_price) }}" placeholder="Opcional">
                                @error('panel_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="panel_currency">Moeda no console</label>
                                <select class="form-select @error('panel_currency') is-invalid @enderror" id="panel_currency" name="panel_currency">
                                    <option value="">Mesma ({{ $license->currency ?: 'USD' }})</option>
                                    @foreach (['USD', 'EUR'] as $currency)
                                        <option value="{{ $currency }}" @selected(old('panel_currency', $license->panel_currency) === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                                @error('panel_currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="panel_billing_cycle">Ciclo no console</label>
                                <select class="form-select @error('panel_billing_cycle') is-invalid @enderror" id="panel_billing_cycle" name="panel_billing_cycle">
                                    <option value="">Mesmo ({{ $license->cycleLabel() }})</option>
                                    @foreach (\App\Models\LicenseService::cycles() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('panel_billing_cycle', $license->panel_billing_cycle) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('panel_billing_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label class="form-label" for="next_due_date">Próximo vencimento</label>
                                <input type="date" class="form-control @error('next_due_date') is-invalid @enderror" id="next_due_date" name="next_due_date" value="{{ old('next_due_date', $license->next_due_date?->format('Y-m-d')) }}">
                                @error('next_due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="auto_renew" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="auto_renew" name="auto_renew" value="1" @checked(old('auto_renew', $license->auto_renew ?? true))>
                                    <label class="form-check-label" for="auto_renew" style="font-size:.84rem;">Renovação automática prevista no cartão</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="notes">Observações</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="6000" placeholder="Notas sobre buckets, política de retenção, região S3…">{{ old('notes', $license->notes) }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">Resumo da cobrança S3</div>
                    <div class="card-body" style="font-size:.84rem;line-height:1.6;">
                        <p class="text-muted-soft mb-2">O sistema calcula o equivalente mensal dividindo o valor do plano pela quantidade de meses do ciclo (mensal ou anual).</p>
                        <p class="text-muted-soft mb-3">Quando o valor do console está preenchido, ele prevalece nos cálculos e na geração de faturas, sinalizando se houve aumento após o 1º ano promocional.</p>
                        <p class="text-muted-soft mb-0">Ao gerar a fatura, aplicam-se spread, IOF e tributos configurados pela cotação atual do dólar/euro.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i> {{ $license->exists ? 'Salvar alterações' : 'Cadastrar serviço S3' }}
                        </button>
                        <a href="{{ $license->exists ? route('s3.show', $license) : route('s3.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    (() => {
        const select = document.getElementById('license_catalog_item_id');
        if (!select) {
            return;
        }

        const product = document.getElementById('product');
        const name = document.getElementById('name');
        const price = document.getElementById('unit_price');
        const currency = document.getElementById('currency');
        const cycle = document.getElementById('billing_cycle');

        const syncLock = () => {
            if (!price) {
                return;
            }
            const linked = select.value !== '';
            price.readOnly = linked;
            price.title = linked ? 'Valor definido pela tabela de planos IDrive e2' : '';
        };

        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];

            if (option && option.value !== '') {
                if (product && option.dataset.product) {
                    product.value = option.dataset.product;
                }
                if (name && name.value.trim() === '' && option.dataset.name) {
                    name.value = option.dataset.name;
                }
                if (price) {
                    price.value = option.dataset.price || '';
                }
                if (currency && option.dataset.currency) {
                    currency.value = option.dataset.currency;
                }
                if (cycle && option.dataset.cycle) {
                    cycle.value = option.dataset.cycle;
                }
            }

            syncLock();
        });

        syncLock();
    })();
</script>
@endpush
