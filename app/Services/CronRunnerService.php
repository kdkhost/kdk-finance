<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

namespace App\Services;

use App\Models\CronJob;
use App\Models\CronJobRun;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Throwable;

class CronRunnerService
{
    /**
     * Executa um cron registrado no painel e grava o histórico.
     */
    public function run(CronJob $job, string $trigger = 'manual'): CronJobRun
    {
        $timeout = max(10, (int) config('finance.cron.timeout', 300));
        $lock = Cache::lock('cron-job:'.$job->id, $timeout + 30);

        if (! $lock->get()) {
            throw new \RuntimeException('Este cron já está em execução.');
        }

        $startedAt = now();
        $start = microtime(true);

        try {
            $process = Process::fromShellCommandline(
                $this->buildCommand($job),
                base_path(),
                null,
                null,
                $timeout,
            );

            $process->run();

            $exitCode = $process->getExitCode() ?? 1;
            $output = trim($process->getOutput());
            $error = trim($process->getErrorOutput());

            if ($error !== '') {
                $output = trim($output."\n".$error);
            }
        } catch (Throwable $exception) {
            $exitCode = 1;
            $output = $exception->getMessage();
        } finally {
            $lock->release();
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $output = mb_substr($output !== '' ? $output : '(sem saída)', 0, 20000);

        $run = $job->runs()->create([
            'trigger' => $trigger,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'exit_code' => $exitCode,
            'duration_ms' => $duration,
            'output' => $output,
        ]);

        $job->forceFill([
            'last_run_at' => $startedAt,
            'last_finished_at' => now(),
            'last_exit_code' => $exitCode,
            'last_output' => $output,
            'run_count' => (int) $job->run_count + 1,
            'failure_count' => (int) $job->failure_count + ($exitCode === 0 ? 0 : 1),
        ])->save();

        return $run;
    }

    public function buildCommand(CronJob $job): string
    {
        if ($job->type === CronJob::TYPE_ARTISAN) {
            return escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan')).' '.$job->command;
        }

        return (string) $job->command;
    }
}
