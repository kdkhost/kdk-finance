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

use App\Services\LicenseBoyPriceService;
use Illuminate\Console\Command;

class SyncLicensePricesCommand extends Command
{
    protected $signature = 'licenses:sync-prices';

    protected $description = 'Atualiza a tabela de preços da LicenseBoy e as licenças vinculadas';

    public function handle(LicenseBoyPriceService $prices): int
    {
        $result = $prices->sync();
        $applied = $prices->applyToLicenses();

        $this->info(sprintf(
            'Catálogo: %d produtos · %d novos · %d atualizados · %d removidos · %d licenças atualizadas.',
            $result['total'],
            $result['created'],
            $result['updated'],
            $result['removed'],
            $applied,
        ));

        foreach ($result['failed'] as $category => $message) {
            $this->warn('Falha em '.$category.': '.$message);
        }

        if (count($result['failed']) === count(LicenseBoyPriceService::CATEGORIES)) {
            $this->error('Nenhuma categoria pôde ser sincronizada.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
