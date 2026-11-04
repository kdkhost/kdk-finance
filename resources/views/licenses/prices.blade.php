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

@section('title', 'Preços LicenseBoy')
@section('eyebrow', 'Licenças')
@section('subtitle', 'Catálogo público da loja com os valores praticados hoje. Use um item para cadastrar ou vincular uma licença.')

@section('actions')
    @if (auth()->user()->canManageFinance())
        <form method="POST" action="{{ route('licenses.prices.sync') }}" data-confirm="Consultar a loja da LicenseBoy agora? O catálogo e as licenças vinculadas serão atualizados com os preços atuais." data-confirm-button="Atualizar preços">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-repeat me-1"></i> Atualizar preços
            </button>
        </form>
    @endif
@endsection

@section('content')
    <div class="stat-grid mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Produtos no catálogo</span>
                    <span class="stat-icon"><i class="bi bi-box-seam"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $total }}</div>
                <div class="stat-meta mt-2">{{ $groups->count() }} categorias publicadas</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Última sincronização</span>
                    <span class="stat-icon is-blue"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="stat-value mt-2" style="font-size:1.15rem;">
                    {{ $lastSyncedAt ? $lastSyncedAt->format('d/m/Y H:i') : 'Nunca' }}
                </div>
                <div class="stat-meta mt-2">
                    @if ($summary)
                        {{ $summary['updated'] ?? 0 }} atualizados · {{ $summary['created'] ?? 0 }} novos
                    @else
                        Rode a primeira sincronização
                    @endif
                </div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Licenças vinculadas</span>
                    <span class="stat-icon is-amber"><i class="bi bi-link-45deg"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $linked }}</div>
                <div class="stat-meta mt-2">Recebem o preço da loja a cada sincronização</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Sem estoque</span>
                    <span class="stat-icon is-red"><i class="bi bi-slash-circle"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $soldOut }}</div>
                <div class="stat-meta mt-2">Produtos marcados como esgotados na loja</div>
            </div>
        </div>
    </div>

    <div class="hint-banner mb-3">
        <i class="bi bi-info-circle"></i>
        <div>
            A LicenseBoy vende pelo WHMCS e não publica API de consulta. Os valores abaixo vêm das páginas
            públicas de cada categoria e são atualizados pelo botão acima ou pelo agendador (diariamente, às 05:10).
            Licenças vinculadas a um item do catálogo recebem automaticamente o preço, a moeda e o ciclo da loja.
        </div>
    </div>

    @if ($groups->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-tags"></i></div>
                    <h3>Catálogo ainda vazio</h3>
                    <p>Nenhum produto foi lido da loja. Use “Atualizar preços” para consultar as categorias públicas da LicenseBoy.</p>
                </div>
            </div>
        </div>
    @else
        @foreach ($groups as $group)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $group['label'] }}</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">{{ $group['items']->count() }} {{ $group['items']->count() === 1 ? 'produto' : 'produtos' }} · {{ $group['category'] }}</div>
                    </div>
                    <a href="https://licenseboy.com/panel/store/{{ $group['category'] }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir categoria
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="text-end">Preço</th>
                                <th>Ciclo</th>
                                <th class="text-end">Setup</th>
                                <th>Disponibilidade</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['items'] as $item)
                                <tr>
                                    <td>
                                        <a href="{{ $item->url ?: 'https://licenseboy.com/panel/store/'.$group['category'] }}" target="_blank" rel="noopener" class="cell-title d-block">{{ $item->name }}</a>
                                        <span class="cell-sub mono" style="font-size:.7rem;">{{ $item->slug }}</span>
                                    </td>
                                    <td class="text-end tabular">
                                        <span class="fw-semibold">{{ $item->priceLabel() }}</span>
                                        @if ($item->price_is_from)
                                            <span class="cell-sub d-block">a partir de</span>
                                        @endif
                                        <span class="cell-sub d-block" style="font-size:.68rem;">{{ $item->currency }}</span>
                                    </td>
                                    <td><span class="badge text-bg-light border">{{ $item->cycleLabel() }}</span></td>
                                    <td class="text-end tabular">
                                        @if ($item->setup_fee === null)
                                            —
                                        @elseif ((float) $item->setup_fee <= 0)
                                            Grátis
                                        @else
                                            {{ $item->currencySymbol() }} {{ number_format((float) $item->setup_fee, 2, ',', '.') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($item->isAvailable())
                                            <span class="badge text-bg-success">{{ $item->availabilityLabel() }}</span>
                                        @else
                                            <span class="badge text-bg-danger">{{ $item->availabilityLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (auth()->user()->canManageFinance())
                                            <a href="{{ route('licenses.create', ['catalogo' => $item->id]) }}" class="btn btn-ghost btn-sm" title="Cadastrar licença com este preço">
                                                <i class="bi bi-plus-circle me-1"></i> Usar este preço
                                            </a>
                                        @else
                                            <span class="text-muted-soft" style="font-size:.74rem;">Somente consulta</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
@endsection
