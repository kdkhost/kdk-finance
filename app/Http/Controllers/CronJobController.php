<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CronJob;
use App\Models\CronJobRun;
use App\Services\CronRunnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CronJobController extends Controller
{
    public function index(CronRunnerService $runner): View
    {
        $jobs = CronJob::query()
            ->withCount('runs')
            ->orderByDesc('enabled')
            ->orderBy('name')
            ->get();

        $heartbeat = AppSetting::get('cron_runner_heartbeat');
        $heartbeatAt = $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat) : null;

        return view('crons.index', [
            'jobs' => $jobs,
            'presets' => CronJob::presets(),
            'totals' => [
                'total' => $jobs->count(),
                'enabled' => $jobs->where('enabled', true)->count(),
                'failing' => $jobs->where('enabled', true)
                    ->whereNotNull('last_exit_code')
                    ->where('last_exit_code', '!=', 0)
                    ->count(),
                'runs_24h' => CronJobRun::query()->where('started_at', '>=', now()->subDay())->count(),
            ],
            'heartbeatAt' => $heartbeatAt,
            'heartbeatOk' => $heartbeatAt !== null && $heartbeatAt->gt(now()->subMinutes(3)),
            'hostingCommand' => $this->hostingCommand($runner),
            'environment' => $runner->environment(),
            'phpBinarySetting' => (string) AppSetting::get('cron_php_binary'),
        ]);
    }

    public function create(): View
    {
        return view('crons.form', [
            'cron' => new CronJob([
                'type' => CronJob::TYPE_ARTISAN,
                'expression' => '0 3 * * *',
                'timezone' => config('app.timezone'),
                'enabled' => true,
            ]),
            'presets' => CronJob::presets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $cron = CronJob::query()->create($data);

        return redirect()
            ->route('crons.show', $cron)
            ->with('success', 'Cron "'.$cron->name.'" cadastrado.');
    }

    public function show(CronJob $cron, CronRunnerService $runner): View
    {
        return view('crons.show', [
            'cron' => $cron->load(['runs' => fn ($query) => $query->orderByDesc('started_at')->limit(30)]),
            'hostingCommand' => $this->hostingCommand($runner),
        ]);
    }

    public function edit(CronJob $cron): View
    {
        return view('crons.form', [
            'cron' => $cron,
            'presets' => CronJob::presets(),
        ]);
    }

    public function update(Request $request, CronJob $cron): RedirectResponse
    {
        $cron->fill($this->validated($request))->save();

        return redirect()
            ->route('crons.show', $cron)
            ->with('success', 'Cron "'.$cron->name.'" atualizado.');
    }

    public function toggle(CronJob $cron): RedirectResponse
    {
        $cron->update(['enabled' => ! $cron->enabled]);

        return back()->with(
            'success',
            $cron->enabled ? 'Cron ativado.' : 'Cron desativado; ele deixa de ser executado pelo agendador.'
        );
    }

    public function run(CronJob $cron, CronRunnerService $runner): RedirectResponse
    {
        @set_time_limit(0);

        try {
            $run = $runner->run($cron, 'manual');
        } catch (Throwable $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        if ($run->isSuccessful()) {
            return back()->with('success', 'Cron executado com sucesso em '.$run->durationLabel().'.');
        }

        return back()->with('warning', 'O cron terminou com código '.$run->exit_code.' em '.$run->durationLabel().'. Veja a saída no histórico.');
    }

    public function destroy(CronJob $cron): RedirectResponse
    {
        $name = $cron->name;
        $cron->delete();

        return redirect()
            ->route('crons.index')
            ->with('success', 'Cron "'.$name.'" removido.');
    }

    /**
     * Recoloca na Central de cron todos os agendamentos que mantêm o sistema
     * atualizado, sem duplicar os já existentes e preservando o que foi
     * ajustado (frequência editada, descrição, ativação).
     */
    public function restoreDefaults(Request $request): RedirectResponse
    {
        $created = 0;
        $updated = 0;

        foreach (CronJob::systemDefaults() as $default) {
            $cron = CronJob::query()->firstOrNew(['command' => $default['command']]);

            if (! $cron->exists) {
                $created++;
                $cron->fill([
                    'name' => $default['name'],
                    'type' => CronJob::TYPE_ARTISAN,
                    'expression' => $default['expression'],
                    'timezone' => $default['timezone'],
                    'description' => $default['description'],
                    'enabled' => true,
                    'created_by' => $request->user()->id,
                ]);

                $cron->save();

                continue;
            }

            $cron->fill([
                'name' => $default['name'],
                'type' => CronJob::TYPE_ARTISAN,
                'expression' => $default['expression'],
                'timezone' => $default['timezone'],
                'description' => $default['description'],
            ])->save();

            $updated++;
        }

        return redirect()
            ->route('crons.index')
            ->with('success', sprintf(
                'Padrões verificados: %d cron(s) criado(s) e %d atualizado(s). Todos usam o agendador do painel.',
                $created,
                $updated
            ));
    }

    /**
     * Define o binário do PHP usado para executar os agendamentos.
     * Em branco volta para a detecção automática.
     */
    public function updateEnvironment(Request $request, CronRunnerService $runner): RedirectResponse
    {
        $data = $request->validate([
            'php_binary' => ['nullable', 'string', 'max:255'],
        ], [], [
            'php_binary' => 'binário do PHP',
        ]);

        $binary = trim((string) ($data['php_binary'] ?? ''));

        if ($binary !== '') {
            if (! @is_file($binary) || ! @is_executable($binary)) {
                return back()
                    ->withErrors(['php_binary' => 'O caminho informado não aponta para um executável válido.'])
                    ->withInput();
            }

            $version = $runner->versionOf($binary);

            if ($version === null || version_compare($version, '8.2.0', '<')) {
                return back()
                    ->withErrors(['php_binary' => 'O binário informado não executa PHP 8.2 ou superior.'])
                    ->withInput();
            }
        }

        AppSetting::set('cron_php_binary', $binary, 'cron');

        return back()->with('success', $binary === ''
            ? 'Detecção automática do PHP restabelecida.'
            : 'Binário do PHP atualizado: '.$binary.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(CronJob::types()))],
            'command' => ['required', 'string', 'max:500'],
            'expression' => [
                'required', 'string', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! CronJob::isValidExpression((string) $value)) {
                        $fail('A expressão cron informada não é válida. Use o formato minuto hora dia mês dia-da-semana.');
                    }
                },
            ],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'description' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ], [], [
            'name' => 'nome',
            'type' => 'tipo',
            'command' => 'comando',
            'expression' => 'expressão',
            'timezone' => 'fuso horário',
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['command'] = trim($data['command']);
        $data['expression'] = trim($data['expression']);

        return $data;
    }

    private function hostingCommand(CronRunnerService $runner): string
    {
        $path = str_replace('"', '', base_path());
        $binary = $runner->phpBinary();
        $binary = preg_match('/\s/', $binary) === 1 ? escapeshellarg($binary) : $binary;

        return '* * * * * cd "'.$path.'" && '.$binary.' artisan schedule:run >> /dev/null 2>&1';
    }
}
