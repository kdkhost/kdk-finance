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

@section('title', $license->exists ? 'Editar licença' : 'Nova licença')
@section('eyebrow', 'Licenças')
@section('subtitle', $license->exists ? 'Atualize os dados de cobrança e renovação da licença.' : 'Cadastre um serviço contratado da LicenseBoy para controlar as faturas.')

@section('actions')
    <a href="{{ $license->exists ? route('licenses.show', $license) : route('licenses.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ $license->exists ? route('licenses.update', $license) : route('licenses.store') }}">
        @csrf
        @if ($license->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">Identificação</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="license_catalog_item_id">Preço de referência</label>
                                <select class="form-select @error('license_catalog_item_id') is-invalid @enderror" id="license_catalog_item_id" name="license_catalog_item_id">
                                    <option value="">— Preço manual (sem vínculo com a loja) —</option>
                                    @foreach ($catalogItems as $group)
                                        <optgroup label="{{ $group['label'] }}">
                                            @foreach ($group['items'] as $item)
                                                <option
                                                    value="{{ $item->id }}"
                                                    data-product="{{ $item->productLabel() }}"
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
                                    @if ($catalogItems->isEmpty())
                                        O catálogo está vazio. Rode a sincronização em
                                        <a href="{{ route('licenses.prices') }}">Preços da loja</a>.
                                    @else
                                        Ao vincular, produto, valor, moeda e ciclo passam a seguir a loja da LicenseBoy
                                        @if ($lastSyncedAt)
                                            (última leitura em {{ $lastSyncedAt->format('d/m/Y H:i') }})
                                        @endif
                                        e são atualizados a cada sincronização. Veja o
                                        <a href="{{ route('licenses.prices') }}">catálogo completo</a>.
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label" for="name">Identificação da licença</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $license->name) }}" required maxlength="150" placeholder="Ex.: cPanel · Servidor RJ">
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
                                <label class="form-label" for="provider">Fornecedor</label>
                                <input type="text" class="form-control @error('provider') is-invalid @enderror" id="provider" name="provider" value="{{ old('provider', $license->provider ?? 'LicenseBoy') }}" required maxlength="80">
                                @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="product">Produto</label>
                                <input type="text" class="form-control @error('product') is-invalid @enderror" id="product" name="product" value="{{ old('product', $license->product) }}" required maxlength="80" list="license-products" placeholder="cPanel, DirectAdmin, CloudLinux…">
                                <datalist id="license-products">
                                    @foreach (\App\Models\LicenseService::products() as $product)
                                        <option value="{{ $product }}"></option>
                                    @endforeach
                                </datalist>
                                @error('product')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-7">
                                <label class="form-label" for="server_name">Servidor / hostname</label>
                                <input type="text" class="form-control mono @error('server_name') is-invalid @enderror" id="server_name" name="server_name" value="{{ old('server_name', $license->server_name) }}" maxlength="190" placeholder="ns576607.ip-51-222-153.net">
                                @error('server_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="license_key">Chave / comando de ativação</label>
                                <input type="password" class="form-control mono @error('license_key') is-invalid @enderror" id="license_key" name="license_key" value="{{ old('license_key') }}" maxlength="500" autocomplete="new-password" placeholder="{{ $license->license_key ? 'Chave salva · '.$license->maskedKey() : 'Opcional' }}">
                                <div class="form-text">Criptografada com a APP_KEY. Deixe em branco para manter.</div>
                                @error('license_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Cobrança e renovação</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="unit_price">Valor unitário</label>
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

                            <div class="col-md-5">
                                <label class="form-label" for="next_due_date">Próximo vencimento</label>
                                <input type="date" class="form-control @error('next_due_date') is-invalid @enderror" id="next_due_date" name="next_due_date" value="{{ old('next_due_date', $license->next_due_date?->format('Y-m-d')) }}">
                                @error('next_due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="auto_renew" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="auto_renew" name="auto_renew" value="1" @checked(old('auto_renew', $license->auto_renew ?? true))>
                                    <label class="form-check-label" for="auto_renew" style="font-size:.84rem;">Renovação automática prevista</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="notes">Observações</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Notas sobre o plano, limite de contas, contato do fornecedor…">{{ old('notes', $license->notes) }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">Resumo da cobrança</div>
                    <div class="card-body" style="font-size:.84rem;line-height:1.6;">
                        <p class="text-muted-soft mb-2">O sistema calcula o equivalente mensal dividindo o valor do ciclo pela quantidade de meses:</p>
                        <ul class="mb-3 ps-3 text-muted-soft">
                            <li>Mensal: valor × 1</li>
                            <li>Trimestral: valor ÷ 3</li>
                            <li>Semestral: valor ÷ 6</li>
                            <li>Anual: valor ÷ 12</li>
                            <li>Vitalícia: sem custo recorrente</li>
                        </ul>
                        <p class="text-muted-soft mb-0">A geração de fatura aplica spread, IOF, ICMS e demais tributos configurados, com o câmbio do dia.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i> {{ $license->exists ? 'Salvar alterações' : 'Cadastrar licença' }}
                        </button>
                        <a href="{{ $license->exists ? route('licenses.show', $license) : route('licenses.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
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
            price.title = linked ? 'Valor definido pelo catálogo da LicenseBoy' : '';
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
