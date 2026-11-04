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
    public function index(): View
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
            'hostingCommand' => $this->hostingCommand(),
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

    public function show(CronJob $cron): View
    {
        return view('crons.show', [
            'cron' => $cron->load(['runs' => fn ($query) => $query->orderByDesc('started_at')->limit(30)]),
            'hostingCommand' => $this->hostingCommand(),
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

    private function hostingCommand(): string
    {
        $path = str_replace('"', '', base_path());

        return '* * * * * cd "'.$path.'" && php artisan schedule:run >> /dev/null 2>&1';
    }
}
