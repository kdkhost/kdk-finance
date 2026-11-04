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

@section('title', 'Licenças')
@section('eyebrow', 'Licenças')
@section('subtitle', 'Serviços contratados da LicenseBoy com valores individuais, renovação e faturas vinculadas.')

@section('actions')
    @if (auth()->user()->canManageFinance())
        <a href="{{ route('licenses.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nova licença
        </a>
    @endif
@endsection

@section('content')
    @php
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $amount = fn ($value, $currency) => ($currency === 'USD' ? 'US$' : '€').' '.number_format((float) $value, 2, ',', '.');
    @endphp

    @if ($rows->isEmpty())
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-key"></i></div>
                    <h3>Nenhuma licença cadastrada</h3>
                    <p>A LicenseBoy não publica uma API de consulta. Cadastre cada serviço contratado para controlar valores e gerar as faturas aqui no sistema.</p>
                    @if (auth()->user()->canManageFinance())
                        <a href="{{ route('licenses.create') }}" class="btn btn-primary btn-sm">Cadastrar primeira licença</a>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="stat-grid mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Licenças ativas</span>
                        <span class="stat-icon"><i class="bi bi-key"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['active'] }}</div>
                    <div class="stat-meta mt-2">{{ $rows->count() }} cadastradas no total</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Custo mensal</span>
                        <span class="stat-icon is-blue"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['monthly_brl']) }}</div>
                    <div class="stat-meta mt-2">Equivalente mensal convertido para BRL</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Projeção anual</span>
                        <span class="stat-icon is-amber"><i class="bi bi-calendar3"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $money($totals['yearly_brl']) }}</div>
                    <div class="stat-meta mt-2">Mensal × 12 na cotação atual</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="stat-label">Renovações próximas</span>
                        <span class="stat-icon is-red"><i class="bi bi-alarm"></i></span>
                    </div>
                    <div class="stat-value mt-2">{{ $totals['due_soon'] }}</div>
                    <div class="stat-meta mt-2">
                        @if ($totals['next_due'])
                            Próximo vencimento {{ $totals['next_due']->format('d/m/Y') }}
                        @else
                            Nenhum vencimento cadastrado
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">Serviços de licença</div>
                    <div class="text-muted-soft" style="font-size:.76rem;">{{ $totals['invoices'] }} faturas vinculadas · valores na moeda original e em BRL</div>
                </div>
                <a href="https://licenseboy.com" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i> LicenseBoy
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Licença</th>
                            <th>Servidor</th>
                            <th>Ciclo</th>
                            <th class="text-end">Valor do ciclo</th>
                            <th class="text-end">Mensal (BRL)</th>
                            <th>Próximo vencimento</th>
                            <th>Situação</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $license = $row['license']; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('licenses.show', $license) }}" class="cell-title d-block">{{ $license->name }}</a>
                                    <span class="cell-sub">{{ $license->product }} · {{ $license->provider }}</span>
                                </td>
                                <td class="mono" style="font-size:.78rem;">{{ $license->server_name ?: '—' }}</td>
                                <td>
                                    <span class="badge text-bg-light border">{{ $license->cycleLabel() }}</span>
                                    @if ($license->quantity > 1)
                                        <span class="cell-sub d-block">{{ $license->quantity }} unidades</span>
                                    @endif
                                </td>
                                <td class="text-end tabular">
                                    {{ $amount($license->totalOriginal(), $license->currency) }}
                                    @if ($license->quantity > 1)
                                        <span class="cell-sub d-block">{{ $amount($license->unit_price, $license->currency) }} cada</span>
                                    @endif
                                </td>
                                <td class="text-end tabular fw-semibold">
                                    {{ $money($row['monthly_brl']) }}
                                    <span class="cell-sub d-block">{{ $amount($license->monthlyEquivalent(), $license->currency) }}/mês</span>
                                </td>
                                <td class="tabular">
                                    @if ($license->next_due_date)
                                        <span class="{{ $license->isDueSoon() ? 'text-danger fw-semibold' : '' }}">{{ $license->next_due_date->format('d/m/Y') }}</span>
                                        @if ($license->isDueSoon())
                                            <span class="cell-sub d-block text-danger">Renova em breve</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><span class="{{ $license->statusBadgeClass() }}">{{ $license->statusLabel() }}</span></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('licenses.show', $license) }}" class="btn btn-ghost btn-sm" title="Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if (auth()->user()->canManageFinance())
                                            <a href="{{ route('licenses.edit', $license) }}" class="btn btn-ghost btn-sm" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @if ($license->status === \App\Models\LicenseService::STATUS_ACTIVE)
                                                <form method="POST" action="{{ route('licenses.invoice', $license) }}" data-confirm="Gerar fatura de {{ $license->product }} no valor de {{ $amount($license->totalOriginal(), $license->currency) }}? O próximo vencimento avançará um ciclo." data-confirm-button="Gerar fatura">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost btn-sm" title="Gerar fatura">
                                                        <i class="bi bi-receipt"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="hint-banner mt-3">
            <i class="bi bi-info-circle"></i>
            <div>
                A LicenseBoy não oferece API pública de consulta; por isso o cadastro é manual. Ao gerar a fatura,
                o sistema aplica spread, IOF, ICMS e demais tributos pela cotação atual e vincula o documento à licença.
            </div>
        </div>
    @endif
@endsection
