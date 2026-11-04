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

@section('title', 'Central de cron')
@section('eyebrow', 'Sistema')
@section('subtitle', 'Todos os agendamentos do painel rodam a partir de uma única linha na hospedagem.')

@section('actions')
    <a href="{{ route('crons.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo cron
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="page-eyebrow">Única linha na hospedagem</div>
                    <p class="text-muted-soft mb-2" style="font-size:.84rem;">
                        Cadastre no cron da hospedagem apenas a linha abaixo. O Laravel dispara todos os crons
                        cadastrados aqui a cada minuto.
                    </p>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <code class="mono p-2 rounded" style="background: var(--kdk-surface-muted);font-size:.78rem;">{{ $hostingCommand }}</code>
                        <button type="button" class="btn btn-ghost btn-sm" id="copy-hosting-command">
                            <i class="bi bi-clipboard me-1"></i> Copiar
                        </button>
                    </div>
                </div>
                <div class="text-end">
                    <div class="stat-label">Agendador</div>
                    @if ($heartbeatOk)
                        <span class="badge text-bg-success mt-2"><i class="bi bi-broadcast me-1"></i> Ativo</span>
                    @else
                        <span class="badge text-bg-danger mt-2"><i class="bi bi-wifi-off me-1"></i> Sem sinal</span>
                    @endif
                    <div class="cell-sub d-block mt-1">
                        {{ $heartbeatAt ? 'Última verificação '.$heartbeatAt->format('d/m/Y H:i:s') : 'Nenhuma verificação registrada' }}
                    </div>
                </div>
            </div>
            @unless ($heartbeatOk)
                <div class="hint-banner mt-3" style="background: var(--kdk-red-soft); border-color: color-mix(in srgb, var(--kdk-red) 30%, transparent);">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>O agendador ainda não enviou sinal. Em produção, confira a linha acima no cron da hospedagem; em ambiente local, rode <span class="mono">php artisan schedule:work</span>.</div>
                </div>
            @endunless
        </div>
    </div>

    <div class="stat-grid mb-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Crons cadastrados</span>
                    <span class="stat-icon"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $totals['total'] }}</div>
                <div class="stat-meta mt-2">{{ $totals['enabled'] }} ativos</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Com falha</span>
                    <span class="stat-icon is-red"><i class="bi bi-exclamation-octagon"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $totals['failing'] }}</div>
                <div class="stat-meta mt-2">Última execução com código diferente de zero</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Execuções em 24h</span>
                    <span class="stat-icon is-blue"><i class="bi bi-play-circle"></i></span>
                </div>
                <div class="stat-value mt-2">{{ $totals['runs_24h'] }}</div>
                <div class="stat-meta mt-2">Agendadas e manuais</div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="stat-label">Relógio do servidor</span>
                    <span class="stat-icon is-amber"><i class="bi bi-alarm"></i></span>
                </div>
                <div class="stat-value mt-2" style="font-size:1.35rem;">{{ now()->format('H:i') }}</div>
                <div class="stat-meta mt-2">{{ now()->format('d/m/Y') }} · {{ config('app.timezone') }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Agendamentos</div>
                <div class="text-muted-soft" style="font-size:.76rem;">Expressões no padrão cron (minuto hora dia mês dia-da-semana)</div>
            </div>
            <span class="badge text-bg-light border">{{ $totals['total'] }}</span>
        </div>

        @if ($jobs->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <div class="icon"><i class="bi bi-clock-history"></i></div>
                    <h3>Nenhum cron cadastrado</h3>
                    <p>Crie agendamentos para comandos Artisan ou comandos do sistema, como backups e limpezas.</p>
                    <a href="{{ route('crons.create') }}" class="btn btn-primary btn-sm">Cadastrar cron</a>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Agendamento</th>
                            <th>Expressão</th>
                            <th>Situação</th>
                            <th>Última execução</th>
                            <th>Próxima</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jobs as $cron)
                            <tr>
                                <td>
                                    <a href="{{ route('crons.show', $cron) }}" class="cell-title d-block">{{ $cron->name }}</a>
                                    <span class="cell-sub mono">{{ $cron->type === \App\Models\CronJob::TYPE_ARTISAN ? 'artisan' : 'shell' }} · {{ \Illuminate\Support\Str::limit($cron->command, 60) }}</span>
                                </td>
                                <td>
                                    <code class="mono" style="font-size:.76rem;">{{ $cron->expression }}</code>
                                    <span class="cell-sub d-block">{{ $cron->expressionLabel() }} · {{ $cron->timezone }}</span>
                                </td>
                                <td><span class="{{ $cron->statusBadgeClass() }}">{{ $cron->statusLabel() }}</span></td>
                                <td>
                                    @if ($cron->last_run_at)
                                        <span class="tabular">{{ $cron->last_run_at->format('d/m H:i') }}</span>
                                        @if ($cron->last_exit_code === 0)
                                            <span class="badge text-bg-success ms-1">0</span>
                                        @elseif ($cron->last_exit_code !== null)
                                            <span class="badge text-bg-danger ms-1">{{ $cron->last_exit_code }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted-soft">Nunca executado</span>
                                    @endif
                                </td>
                                <td class="tabular text-muted-soft">{{ $cron->nextRun()?->format('d/m H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <form method="POST" action="{{ route('crons.toggle', $cron) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm" title="{{ $cron->enabled ? 'Desativar' : 'Ativar' }}">
                                                <i class="bi {{ $cron->enabled ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('crons.run', $cron) }}" data-confirm="Executar &quot;{{ $cron->name }}&quot; agora?" data-confirm-button="Executar">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Executar agora">
                                                <i class="bi bi-lightning-charge"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('crons.show', $cron) }}" class="btn btn-ghost btn-sm" title="Histórico">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('crons.edit', $cron) }}" class="btn btn-ghost btn-sm" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="{{ route('crons.destroy', $cron) }}" data-confirm="Remover o cron &quot;{{ $cron->name }}&quot;?" data-confirm-button="Remover" data-confirm-variant="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Remover">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="hint-banner mt-3">
        <i class="bi bi-shield-lock"></i>
        <div>
            Comandos do sistema (shell) são executados com o usuário da aplicação e com o diretório base do projeto.
            Cadastre apenas comandos confiáveis — a central é restrita ao perfil desenvolvedor/administrador.
        </div>
    </div>

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            document.getElementById('copy-hosting-command')?.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(@json($hostingCommand));
                    window.toastr.success('Linha copiada. Cole no cron da hospedagem.', 'Cron da hospedagem');
                } catch (error) {
                    window.toastr.error('Não foi possível copiar automaticamente. Selecione a linha e copie manualmente.', 'Área de transferência');
                }
            });
        </script>
    @endpush
@endsection
