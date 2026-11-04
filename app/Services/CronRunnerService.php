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

namespace App\Services;

use App\Models\AppSetting;
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

        if ($exitCode !== 0) {
            $this->notifyFailure($job, $output, $exitCode);
        }

        return $run;
    }

    /**
     * Avisa os destinatários de alerta quando um agendamento termina com erro.
     */
    private function notifyFailure(CronJob $job, string $output, int $exitCode): void
    {
        try {
            app(MailTemplateService::class)->sendToAlerts('sync_failure', [
                'usuario' => 'equipe técnica',
                'rotina' => $job->name,
                'comando' => $job->command,
                'data' => now()->format('d/m/Y H:i'),
                'erro' => mb_substr(trim($output) !== '' ? trim($output) : 'O comando terminou com código '.$exitCode.'.', 0, 300),
                'link' => route('crons.show', $job),
            ]);
        } catch (Throwable) {
            // O histórico da execução já registra a falha; o aviso é secundário.
        }
    }

    public function buildCommand(CronJob $job): string
    {
        if ($job->type === CronJob::TYPE_ARTISAN) {
            return escapeshellarg($this->phpBinary()).' '.escapeshellarg(base_path('artisan')).' '.$job->command;
        }

        return (string) $job->command;
    }

    /**
     * Binário do PHP usado para executar os agendamentos.
     *
     * Em hospedagens cPanel/LiteSpeed o PHP_BINARY da requisição web é o
     * lsphp, que pode apontar para uma versão antiga; por isso procuramos
     * primeiro um CLI 8.2 conhecido e só depois caímos para o PHP atual.
     * O caminho pode ser fixado no painel (app_settings.cron_php_binary).
     */
    public function phpBinary(): string
    {
        $configured = trim((string) AppSetting::get('cron_php_binary'));

        if ($configured !== '' && $this->isExecutable($configured)) {
            return $configured;
        }

        foreach ($this->phpCandidates() as $candidate) {
            if ($this->isExecutable($candidate) && $this->supports($candidate)) {
                return $candidate;
            }
        }

        return PHP_BINARY;
    }

    /**
     * @return array{binary: string, version: string|null}
     */
    public function environment(): array
    {
        $binary = $this->phpBinary();

        return ['binary' => $binary, 'version' => $this->versionOf($binary)];
    }

    /**
     * @return list<string>
     */
    private function phpCandidates(): array
    {
        $candidates = [
            '/usr/local/bin/ea-php82',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/opt/alt/php82/usr/bin/php',
            '/usr/bin/php8.2',
            PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.(PHP_OS_FAMILY === 'Windows' ? '.exe' : ''),
            PHP_BINARY,
        ];

        return array_values(array_unique(array_filter($candidates)));
    }

    private function isExecutable(string $path): bool
    {
        return $path !== '' && @is_file($path) && @is_executable($path);
    }

    private function supports(string $binary): bool
    {
        $version = $this->versionOf($binary);

        return $version !== null && version_compare($version, '8.2.0', '>=');
    }

    public function versionOf(string $binary): ?string
    {
        try {
            $process = new Process([$binary, '-r', 'echo PHP_VERSION;']);
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $version = trim($process->getOutput());

            return preg_match('/^\d+\.\d+\.\d+/', $version) === 1 ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }
}
