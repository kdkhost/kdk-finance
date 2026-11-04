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

@section('title', $cron->exists ? 'Editar cron' : 'Novo cron')
@section('eyebrow', 'Central de cron')
@section('subtitle', $cron->exists ? 'Ajuste o comando, a frequência ou o fuso horário deste agendamento.' : 'Cadastre um novo agendamento para rodar pela linha única da hospedagem.')

@section('actions')
    <a href="{{ $cron->exists ? route('crons.show', $cron) : route('crons.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ $cron->exists ? route('crons.update', $cron) : route('crons.store') }}">
        @csrf
        @if ($cron->exists)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">Agendamento</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="name">Nome</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $cron->name) }}" required maxlength="150" placeholder="Ex.: Backup do banco de dados">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" @checked(old('enabled', $cron->enabled ?? true))>
                                    <label class="form-check-label" for="enabled" style="font-size:.84rem;">Ativo</label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="type">Tipo</label>
                                <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                    @foreach (\App\Models\CronJob::types() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('type', $cron->type) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="command">Comando</label>
                                <input type="text" class="form-control mono @error('command') is-invalid @enderror" id="command" name="command" value="{{ old('command', $cron->command) }}" required maxlength="500" placeholder="finance:sync-invoices --import">
                                <div class="form-text" id="command-help">
                                    Para Artisan, informe apenas o comando sem “php artisan”.
                                </div>
                                @error('command')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label class="form-label" for="expression">Expressão cron</label>
                                <input type="text" class="form-control mono @error('expression') is-invalid @enderror" id="expression" name="expression" value="{{ old('expression', $cron->expression) }}" required maxlength="100">
                                @error('expression')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Formato: minuto hora dia mês dia-da-semana.</div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="timezone">Fuso horário</label>
                                <select class="form-select @error('timezone') is-invalid @enderror" id="timezone" name="timezone" required>
                                    @foreach (\DateTimeZone::listIdentifiers() as $zone)
                                        <option value="{{ $zone }}" @selected(old('timezone', $cron->timezone ?? config('app.timezone')) === $zone)>{{ $zone }}</option>
                                    @endforeach
                                </select>
                                @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Atalhos de frequência</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($presets as $expression => $label)
                                        <button type="button" class="btn btn-outline-secondary btn-sm js-cron-preset" data-expression="{{ $expression }}">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="description">Descrição</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="2" maxlength="500" placeholder="Para que serve este agendamento?">{{ old('description', $cron->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">Como funciona</div>
                    <div class="card-body" style="font-size:.84rem;line-height:1.6;">
                        <p class="text-muted-soft">A hospedagem executa apenas <span class="mono">php artisan schedule:run</span> a cada minuto. O comando <span class="mono">cron:run-due</span> verifica os agendamentos vencidos e executa cada um.</p>
                        <p class="text-muted-soft">A saída, o código de retorno e a duração ficam registrados no histórico deste cron.</p>
                        <p class="text-muted-soft mb-0">Exemplos de comandos Artisan:</p>
                        <ul class="text-muted-soft mb-0 ps-3">
                            <li><span class="mono">finance:sync-invoices --import</span></li>
                            <li><span class="mono">queue:work --stop-when-empty</span></li>
                            <li><span class="mono">cache:prune-stale-tags</span></li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i> {{ $cron->exists ? 'Salvar alterações' : 'Cadastrar cron' }}
                        </button>
                        <a href="{{ $cron->exists ? route('crons.show', $cron) : route('crons.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            document.querySelectorAll('.js-cron-preset').forEach(function (button) {
                button.addEventListener('click', function () {
                    const input = document.getElementById('expression');
                    if (input) {
                        input.value = button.dataset.expression;
                    }
                });
            });

            document.getElementById('type')?.addEventListener('change', function () {
                const help = document.getElementById('command-help');
                const command = document.getElementById('command');
                if (!help || !command) return;

                if (this.value === 'artisan') {
                    help.textContent = 'Para Artisan, informe apenas o comando sem “php artisan”.';
                    command.placeholder = 'finance:sync-invoices --import';
                } else {
                    help.textContent = 'Comando executado no shell, a partir da raiz do projeto.';
                    command.placeholder = 'cp storage/logs/laravel.log /backup/laravel-$(date +%F).log';
                }
            });
        </script>
    @endpush
@endsection
