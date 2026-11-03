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

@section('title', 'Faturas')
@section('eyebrow', 'Financeiro')
@section('subtitle', 'Conciliação das faturas em EUR e USD com cotação, spread e tributos discriminados.')

@section('actions')
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('invoices.export', request()->only(['q', 'status', 'currency', 'overdue'])) }}" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Exportar CSV
        </a>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova fatura
        </a>
    @endif
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="q">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Descrição ou referência OVH">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach (\App\Models\Invoice::statuses() as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="currency">Moeda</label>
                    <select class="form-select" id="currency" name="currency">
                        <option value="">Todas</option>
                        <option value="EUR" @selected(($filters['currency'] ?? '') === 'EUR')>EUR · Euro</option>
                        <option value="USD" @selected(($filters['currency'] ?? '') === 'USD')>USD · Dólar</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <div class="form-check form-switch mt-4 pt-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="overdue" name="overdue" value="1" @checked(! empty($filters['overdue']))>
                        <label class="form-check-label" for="overdue" style="font-size:.8rem;">Somente vencidas</label>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-navy flex-fill">Filtrar</button>
                    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="{{ route('invoices.index') }}" class="currency-chip text-decoration-none">Todas · {{ $invoices->total() }}</a>
                <a href="{{ route('invoices.index', ['status' => 'pending']) }}" class="currency-chip text-decoration-none">Pendentes · {{ $summary['pending'] }}</a>
                <a href="{{ route('invoices.index', ['status' => 'paid']) }}" class="currency-chip text-decoration-none">Pagas · {{ $summary['paid'] }}</a>
                <a href="{{ route('invoices.index', ['status' => 'canceled']) }}" class="currency-chip text-decoration-none">Canceladas · {{ $summary['canceled'] }}</a>
                <a href="{{ route('invoices.index', ['overdue' => 1]) }}" class="currency-chip text-decoration-none" style="color: var(--kdk-red);">Vencidas · {{ $summary['overdue'] }}</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fatura</th>
                        <th>Moeda</th>
                        <th class="text-end">Valor original</th>
                        <th class="text-end">Cotação</th>
                        <th class="text-end">Total BRL</th>
                        <th>Status</th>
                        <th>Vencimento</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr data-invoice-row="{{ $invoice->id }}">
                            <td style="min-width: 230px;">
                                <a href="{{ route('invoices.show', $invoice) }}" class="cell-title d-block">{{ \Illuminate\Support\Str::limit($invoice->description, 48) }}</a>
                                <span class="cell-sub">{{ $invoice->external_reference ?: 'Cadastro manual' }}</span>
                            </td>
                            <td><span class="currency-chip">{{ $invoice->currencySymbol() }} {{ $invoice->currency }}</span></td>
                            <td class="text-end tabular">{{ number_format((float) $invoice->amount_original, 2, ',', '.') }}</td>
                            <td class="text-end tabular">{{ number_format((float) $invoice->exchange_rate, 4, ',', '.') }}</td>
                            <td class="text-end tabular fw-semibold" data-invoice-total="{{ $invoice->id }}">{{ $money($invoice->total_brl) }}</td>
                            <td>
                                <span class="{{ $invoice->statusBadgeClass() }}">{{ $invoice->statusLabel() }}</span>
                                @if ($invoice->rate_locked)
                                    <i class="bi bi-lock-fill ms-1 text-muted-soft" title="Câmbio travado" style="font-size:.7rem;"></i>
                                @endif
                            </td>
                            <td class="tabular {{ $invoice->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-ghost btn-icon-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da fatura">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('invoices.show', $invoice) }}"><i class="bi bi-eye me-2"></i> Detalhes</a></li>
                                        @if (auth()->user()->canManageFinance())
                                            <li><a class="dropdown-item" href="{{ route('invoices.edit', $invoice) }}"><i class="bi bi-pencil me-2"></i> Editar</a></li>
                                            @if ($invoice->status === \App\Models\Invoice::STATUS_PENDING)
                                                <li>
                                                    <form method="POST" action="{{ route('invoices.recalculate', $invoice) }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-calculator me-2"></i> Recalcular</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('invoices.pay', $invoice) }}" data-confirm="Confirmar o pagamento e travar a cotação desta fatura?" data-confirm-button="Marcar como paga">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-check2-circle me-2"></i> Marcar como paga</button>
                                                    </form>
                                                </li>
                                            @endif
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="icon"><i class="bi bi-receipt"></i></div>
                                    <h3>Nenhuma fatura encontrada</h3>
                                    <p>Ajuste os filtros ou cadastre uma nova fatura para começar a conciliação.</p>
                                    @if (auth()->user()->canManageFinance())
                                        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">Nova fatura</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())
            <div class="card-body border-top">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection
