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

@section('title', $cron->name)
@section('eyebrow', 'Central de cron')
@section('subtitle', 'Comando, frequência, saída e histórico de execuções deste agendamento.')

@section('actions')
    <a href="{{ route('crons.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
    <a href="{{ route('crons.edit', $cron) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i> Editar
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <span class="{{ $cron->statusBadgeClass() }}">{{ $cron->statusLabel() }}</span>
                            <span class="currency-chip ms-1">{{ $cron->type === \App\Models\CronJob::TYPE_ARTISAN ? 'Artisan' : 'Shell' }}</span>
                            <h2 class="mt-3 mb-1" style="font-size:1.25rem;">{{ $cron->name }}</h2>
                            <code class="mono text-muted-soft" style="font-size:.78rem;">{{ $cron->command }}</code>
                        </div>
                        <div class="text-end">
                            <div class="stat-label">Próxima execução</div>
                            <div class="fs-4 fw-bold tabular mt-1">{{ $cron->nextRun()?->format('d/m H:i') ?? '—' }}</div>
                            <div class="text-muted-soft mt-1" style="font-size:.74rem;">{{ $cron->expression }} · {{ $cron->timezone }}</div>
                        </div>
                    </div>

                    <div class="kpi-inline mt-4 pt-3 border-top">
                        <div class="kpi">
                            <div class="kpi-label">Execuções</div>
                            <div class="kpi-value">{{ $cron->run_count }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Falhas</div>
                            <div class="kpi-value">{{ $cron->failure_count }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Última execução</div>
                            <div class="kpi-value">{{ $cron->last_run_at?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                        <div class="kpi">
                            <div class="kpi-label">Último código</div>
                            <div class="kpi-value">
                                @if ($cron->last_exit_code === null)
                                    —
                                @elseif ($cron->last_exit_code === 0)
                                    <span class="text-success">0 · sucesso</span>
                                @else
                                    <span class="text-danger">{{ $cron->last_exit_code }} · falha</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Última saída</div>
                        <div class="text-muted-soft" style="font-size:.76rem;">Capturada na última execução, manual ou agendada</div>
                    </div>
                    @if ($cron->last_finished_at)
                        <span class="cell-sub">{{ $cron->last_finished_at->format('d/m/Y H:i:s') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <pre class="mb-0 p-3 rounded" style="background: var(--kdk-surface-muted);font-size:.76rem;white-space:pre-wrap;max-height:260px;overflow:auto;">{{ $cron->last_output ?: 'Nenhuma execução registrada até agora.' }}</pre>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">Histórico</div>
                    <span class="badge text-bg-light border">{{ $cron->runs->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Início</th>
                                <th>Origem</th>
                                <th>Duração</th>
                                <th>Código</th>
                                <th>Saída</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cron->runs as $run)
                                <tr>
                                    <td class="tabular">{{ $run->started_at?->format('d/m/Y H:i:s') }}</td>
                                    <td><span class="badge text-bg-light border">{{ $run->triggerLabel() }}</span></td>
                                    <td class="tabular">{{ $run->durationLabel() }}</td>
                                    <td>
                                        @if ($run->exit_code === 0)
                                            <span class="badge text-bg-success">0</span>
                                        @else
                                            <span class="badge text-bg-danger">{{ $run->exit_code }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <details>
                                            <summary class="text-muted-soft" style="font-size:.78rem;cursor:pointer;">Ver saída</summary>
                                            <pre class="mb-0 mt-2 p-2 rounded" style="background: var(--kdk-surface-muted);font-size:.72rem;white-space:pre-wrap;max-height:220px;overflow:auto;">{{ $run->output ?: '(sem saída)' }}</pre>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state py-4">
                                            <div class="icon"><i class="bi bi-clock-history"></i></div>
                                            <h3>Sem execuções</h3>
                                            <p class="mb-0">Use “Executar agora” ou aguarde o próximo horário agendado.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Ações</div>
                <div class="card-body d-grid gap-2">
                    <form method="POST" action="{{ route('crons.run', $cron) }}" data-confirm="Executar &quot;{{ $cron->name }}&quot; agora?" data-confirm-button="Executar">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-lightning-charge me-1"></i> Executar agora
                        </button>
                    </form>

                    <form method="POST" action="{{ route('crons.toggle', $cron) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi {{ $cron->enabled ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                            {{ $cron->enabled ? 'Desativar cron' : 'Ativar cron' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('crons.destroy', $cron) }}" data-confirm="Remover o cron &quot;{{ $cron->name }}&quot;?" data-confirm-button="Remover" data-confirm-variant="danger">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-secondary w-100 text-danger">
                            <i class="bi bi-trash3 me-1"></i> Remover cron
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Configuração</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Tipo</span>
                        <span class="fw-semibold">{{ $cron->typeLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Expressão</span>
                        <span class="fw-semibold mono">{{ $cron->expression }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Frequência</span>
                        <span class="fw-semibold">{{ $cron->expressionLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Fuso horário</span>
                        <span class="fw-semibold">{{ $cron->timezone }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1" style="font-size:.8rem;">
                        <span class="text-muted-soft">Criado em</span>
                        <span class="fw-semibold">{{ $cron->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    @if ($cron->description)
                        <p class="text-muted-soft mt-2 mb-0" style="font-size:.78rem;">{{ $cron->description }}</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">Linha da hospedagem</div>
                <div class="card-body">
                    <code class="mono d-block p-2 rounded" style="background: var(--kdk-surface-muted);font-size:.72rem;word-break:break-all;">{{ $hostingCommand }}</code>
                    <p class="text-muted-soft mt-2 mb-0" style="font-size:.74rem;">Cadastre esta linha uma única vez no cron da hospedagem; todos os agendamentos do painel passam a rodar por ela.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
