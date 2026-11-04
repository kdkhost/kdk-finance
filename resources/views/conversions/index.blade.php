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

@section('title', 'Conversões')
@section('eyebrow', 'Câmbio internacional')
@section('subtitle', 'Quanto de EUR ou USD é necessário para pagar cada compromisso, incluindo spread, IOF e tarifas do cartão.')

@section('actions')
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('conversions.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova conversão
        </a>
    @endif
@endsection

@section('content')
    <div class="stat-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-currency-exchange"></i></div>
            <div>
                <div class="stat-label">EUR → USD agora</div>
                <div class="stat-value">{{ number_format((float) ($quote['rate'] ?? 0), 6, ',', '.') }}</div>
                <div class="cell-sub">
                    {{ $quote['source'] ?? '—' }}
                    @if (! empty($quote['stale']))
                        · <span class="text-danger">última conhecida</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-list-check"></i></div>
            <div>
                <div class="stat-label">Conversões salvas</div>
                <div class="stat-value">{{ $summary['total'] }}</div>
                <div class="cell-sub">{{ $summary['calibrated'] }} conferida(s) na fatura</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <div>
                <div class="stat-label">Custo médio conferido</div>
                <div class="stat-value">{{ $summary['avg_cost'] !== null ? number_format($summary['avg_cost'], 3, ',', '.').'%' : '—' }}</div>
                <div class="cell-sub">sobre a cotação de mercado</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">Equivalente em BRL</div>
                <div class="stat-value">R$ {{ number_format($summary['total_brl'], 2, ',', '.') }}</div>
                <div class="cell-sub">soma das conversões salvas</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('conversions.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="q">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Descrição ou observação">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="pair">Direção</label>
                    <select class="form-select" id="pair" name="pair">
                        <option value="">Todas</option>
                        @foreach (['EUR-USD', 'USD-EUR'] as $pair)
                            <option value="{{ $pair }}" @selected(($filters['pair'] ?? '') === $pair)>
                                {{ str_replace('-', ' → ', $pair) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="calibrated" name="calibrated" value="1" @checked(! empty($filters['calibrated']))>
                        <label class="form-check-label" for="calibrated" style="font-size:.8rem;">Só conferidas</label>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-navy flex-fill">Filtrar</button>
                    <a href="{{ route('conversions.index') }}" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 conversions-table">
                <thead>
                    <tr>
                        <th>Conversão</th>
                        <th class="d-none d-lg-table-cell">Direção</th>
                        <th class="text-end d-none d-xl-table-cell">Valor de origem</th>
                        <th class="text-end d-none d-xxl-table-cell">Cotação</th>
                        <th class="text-end d-none d-md-table-cell">Custo</th>
                        <th class="text-end">Total<span class="d-none d-sm-inline"> a pagar</span></th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversions as $conversion)
                        <tr>
                            <td class="conversion-title-cell">
                                <a href="{{ route('conversions.show', $conversion) }}" class="cell-title d-block">{{ \Illuminate\Support\Str::limit($conversion->description, 48) }}</a>
                                <span class="cell-sub">{{ $conversion->created_at?->format('d/m/Y H:i') }}</span>
                                <div class="conversion-mobile-meta d-md-none">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="currency-chip">{{ $conversion->pairLabel() }}</span>
                                        @if ($conversion->isCalibrated())
                                            <span class="badge text-bg-light border"><i class="bi bi-check2-circle me-1"></i> conferida</span>
                                        @endif
                                        <span class="ms-auto {{ $conversion->rate_stale ? 'text-danger fw-semibold' : '' }}">
                                            @if ($conversion->rate_stale)
                                                <i class="bi bi-wifi-off me-1"></i> última conhecida
                                            @endif
                                        </span>
                                    </div>
                                    <div>
                                        {{ $conversion->fromSymbol() }} {{ number_format((float) $conversion->amount_from, 2, ',', '.') }}
                                        · cotação {{ number_format((float) $conversion->market_rate, 6, ',', '.') }}
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-lg-table-cell"><span class="currency-chip">{{ $conversion->pairLabel() }}</span></td>
                            <td class="text-end tabular d-none d-xl-table-cell">{{ number_format((float) $conversion->amount_from, 2, ',', '.') }}</td>
                            <td class="text-end tabular d-none d-xxl-table-cell">{{ number_format((float) $conversion->market_rate, 6, ',', '.') }}</td>
                            <td class="text-end tabular d-none d-md-table-cell">
                                {{ number_format($conversion->costPercent(), 3, ',', '.') }}%
                                @if ($conversion->isCalibrated())
                                    <i class="bi bi-patch-check-fill text-success ms-1" title="Conferida com a fatura do cartão" style="font-size:.72rem;"></i>
                                @endif
                            </td>
                            <td class="text-end tabular fw-semibold">{{ $conversion->toSymbol() }} {{ number_format((float) $conversion->total_to, 2, ',', '.') }}</td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-ghost btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da conversão">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('conversions.show', $conversion) }}"><i class="bi bi-eye me-2"></i> Detalhes</a></li>
                                        @if (auth()->user()->canManageFinance())
                                            <li><a class="dropdown-item" href="{{ route('conversions.edit', $conversion) }}"><i class="bi bi-pencil me-2"></i> Editar</a></li>
                                            <li>
                                                <form method="POST" action="{{ route('conversions.recalculate', $conversion) }}" data-confirm="Recalcular esta conversão com a cotação atual?" data-confirm-button="Recalcular">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-calculator me-2"></i> Recalcular</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('conversions.destroy', $conversion) }}" data-confirm="Excluir a conversão “{{ $conversion->description }}”? Esta ação não pode ser desfeita." data-confirm-button="Excluir" data-confirm-variant="danger">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i> Excluir</button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="icon"><i class="bi bi-currency-exchange"></i></div>
                                    <h3>Nenhuma conversão encontrada</h3>
                                    <p>Calcule quanto precisa em dólar para pagar um valor em euro — com as tarifas do cartão já incluídas.</p>
                                    @if (auth()->user()->canManageFinance())
                                        <a href="{{ route('conversions.create') }}" class="btn btn-primary btn-sm">Nova conversão</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($conversions->hasPages())
            <div class="card-body border-top">
                {{ $conversions->links() }}
            </div>
        @endif
    </div>
@endsection
