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
    @php
        $rate = (float) ($quote['rate'] ?? 0);
        $inverse = (float) ($quote['inverse'] ?? ($rate > 0 ? 1 / $rate : 0));
        $defaults = $defaults ?? [];
        $hasConversions = $summary['total'] > 0;
        $isFiltered = filled($filters['q'] ?? null)
            || filled($filters['pair'] ?? null)
            || ! empty($filters['calibrated']);
        $quoteAt = ! empty($quote['updated_at'])
            ? \Illuminate\Support\Carbon::parse($quote['updated_at'])->format('d/m/Y H:i')
            : '—';
    @endphp

    <section class="conversion-hero mb-3" aria-label="Cotação atual do par EUR-USD">
        <div class="conversion-hero-main">
            <div class="conversion-hero-eyebrow">
                <i class="bi bi-broadcast"></i> Câmbio agora
                @if (! empty($quote['stale']))
                    <span class="conversion-hero-flag"><i class="bi bi-wifi-off"></i> última conhecida</span>
                @endif
            </div>

            <div class="conversion-hero-rate">
                <span class="conversion-hero-value">{{ number_format($rate, 6, ',', '.') }}</span>
                <span class="conversion-hero-unit">USD por EUR</span>
            </div>

            <div class="conversion-hero-meta">
                <span class="conversion-hero-pair">EUR → USD</span>
                <span>1 USD = {{ number_format($inverse, 6, ',', '.') }} EUR</span>
                <span class="conversion-hero-sep" aria-hidden="true"></span>
                <span>{{ $quote['source'] ?? '—' }}</span>
                <span class="conversion-hero-sep" aria-hidden="true"></span>
                <span>{{ $quoteAt }}</span>
            </div>
        </div>

        <div class="conversion-hero-side">
            <div class="conversion-hero-side-label">Tarifas padrão</div>
            <div class="conversion-hero-fees">
                <span>Spread <strong>{{ number_format((float) ($defaults['spread_percent'] ?? 0), 3, ',', '.') }}%</strong></span>
                <span>IOF <strong>{{ number_format((float) ($defaults['iof_percent'] ?? 0), 3, ',', '.') }}%</strong></span>
                <span>Margem <strong>{{ number_format((float) ($defaults['buffer_percent'] ?? 0), 3, ',', '.') }}%</strong></span>
            </div>
            @if (auth()->user()->canManageFinance())
                <a href="{{ route('settings.index', ['tab' => 'conversion']) }}" class="conversion-hero-link">
                    Ajustar tarifas <i class="bi bi-arrow-up-right"></i>
                </a>
            @endif
        </div>
    </section>

    @if ($hasConversions)
        <div class="conversion-summary mb-3">
            <div class="conversion-summary-item">
                <span>Conversões salvas</span>
                <strong>{{ $summary['total'] }}</strong>
                <small>{{ $summary['calibrated'] }} conferida(s) na fatura</small>
            </div>
            <div class="conversion-summary-item">
                <span>Custo médio conferido</span>
                <strong>{{ $summary['avg_cost'] !== null ? number_format($summary['avg_cost'], 3, ',', '.').'%' : '—' }}</strong>
                <small>sobre a cotação de mercado</small>
            </div>
            <div class="conversion-summary-item">
                <span>Equivalente em BRL</span>
                <strong>R$ {{ number_format($summary['total_brl'], 2, ',', '.') }}</strong>
                <small>soma das conversões salvas</small>
            </div>
        </div>
    @endif

    @if (! $hasConversions && ! $isFiltered)
        <div class="card conversion-empty">
            <div class="conversion-empty-mark" aria-hidden="true">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <span class="conversion-empty-eyebrow">Conversões</span>
            <h2>Quanto de dólar você precisa?</h2>
            <p>
                Informe o valor da fatura em euro, a cotação de mercado e — se tiver — a cotação que o
                PayPal ou o banco mostrou. O sistema aplica spread, IOF e tarifas e devolve o total
                que vai sair do cartão.
            </p>

            <div class="conversion-example" aria-label="Exemplo de conversão">
                <div>
                    <span>Fatura em euro</span>
                    <strong>€ 43,96</strong>
                </div>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                <div>
                    <span>PayPal a 0,8498</span>
                    <strong>US$ 51,73</strong>
                </div>
            </div>

            @if (auth()->user()->canManageFinance())
                <a href="{{ route('conversions.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Criar primeira conversão
                </a>
            @else
                <div class="text-muted-soft" style="font-size:.82rem;">Seu perfil permite apenas consultar as conversões salvas.</div>
            @endif

            <div class="conversion-empty-note">
                Depois de salva, a conversão pode ser conferida contra o valor real do cartão
                e recalculada com o câmbio do dia.
            </div>
        </div>
    @else
        @if ($hasConversions)
            <div class="card mb-3">
                <div class="card-body py-3">
                    <form method="GET" action="{{ route('conversions.index') }}" class="conversions-toolbar">
                        <div class="conversions-toolbar-search">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por descrição ou observação" aria-label="Buscar conversões">
                            </div>
                        </div>
                        <div class="conversions-toolbar-pair">
                            <label class="visually-hidden" for="pair">Direção</label>
                            <select class="form-select" id="pair" name="pair">
                                <option value="">Todas as direções</option>
                                @foreach (['EUR-USD', 'USD-EUR'] as $pair)
                                    <option value="{{ $pair }}" @selected(($filters['pair'] ?? '') === $pair)>
                                        {{ str_replace('-', ' → ', $pair) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check form-switch conversions-toolbar-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="calibrated" name="calibrated" value="1" @checked(! empty($filters['calibrated']))>
                            <label class="form-check-label" for="calibrated">Só conferidas</label>
                        </div>
                        <button type="submit" class="btn btn-navy">Filtrar</button>
                        @if ($isFiltered)
                            <a href="{{ route('conversions.index') }}" class="btn btn-outline-secondary" title="Limpar filtros" aria-label="Limpar filtros">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        @endif

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
                                            @if ($conversion->provider_rate)
                                                · provedor {{ number_format((float) $conversion->provider_rate, 6, ',', '.') }}
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="currency-chip">{{ $conversion->pairLabel() }}</span>
                                </td>
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
                                        <div class="icon"><i class="bi bi-search"></i></div>
                                        <h3>Nenhuma conversão encontrada</h3>
                                        <p>Ajuste os filtros ou limpe a busca para ver o histórico completo.</p>
                                        <a href="{{ route('conversions.index') }}" class="btn btn-outline-secondary btn-sm">Limpar filtros</a>
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
    @endif
@endsection
