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

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\CronJob;
use App\Services\CronRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunDueCronsCommand extends Command
{
    protected $signature = 'cron:run-due';

    protected $description = 'Executa os crons cadastrados no painel que estão vencidos neste minuto';

    public function handle(CronRunnerService $runner): int
    {
        AppSetting::set('cron_runner_heartbeat', now()->toIso8601String(), 'cron');

        $jobs = CronJob::query()->enabled()->get();
        $executed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            if (! $job->isDue()) {
                continue;
            }

            $lastRun = $job->last_run_at?->copy()->setTimezone($job->timezone)->format('Y-m-d H:i');
            $now = now($job->timezone)->format('Y-m-d H:i');

            if ($lastRun === $now) {
                continue;
            }

            try {
                $run = $runner->run($job, 'schedule');
                $executed++;

                if (! $run->isSuccessful()) {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('Falha ao executar cron do painel.', [
                    'cron' => $job->id,
                    'name' => $job->name,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Crons executados: {$executed} · falhas: {$failed}.");

        return self::SUCCESS;
    }
}
