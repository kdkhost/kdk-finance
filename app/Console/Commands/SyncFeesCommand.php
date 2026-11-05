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

use App\Models\FeeSyncRun;
use App\Services\Fees\FeeSyncService;
use Illuminate\Console\Command;

class SyncFeesCommand extends Command
{
    protected $signature = 'finance:sync-fees {--source=all : visa, mastercard, bcb, paypal, bancos ou all}';

    protected $description = 'Lê o markup das bandeiras, a PTAX do BCB e o spread observado no PayPal e grava a procedência das tarifas';

    public function handle(FeeSyncService $fees): int
    {
        $runs = $fees->run((string) $this->option('source'));

        foreach ($runs as $run) {
            $value = match (true) {
                $run->value === null => '—',
                $run->source === 'bcb' => 'PTAX '.number_format((float) $run->value, 4, ',', '.'),
                default => number_format((float) $run->value, 4, ',', '.').'%',
            };

            $this->line(sprintf(
                '  %-11s %-13s %-18s %s',
                $run->source,
                $run->status,
                $value,
                $run->note ?? ''
            ));
        }

        $failed = collect($runs)->contains(fn (FeeSyncRun $run): bool => $run->status === FeeSyncRun::STATUS_UNAVAILABLE);

        if ($failed) {
            $this->warn('Uma ou mais fontes não responderam; os valores anteriores foram mantidos.');

            return self::SUCCESS;
        }

        $this->info('Tarifas sincronizadas.');

        return self::SUCCESS;
    }
}
