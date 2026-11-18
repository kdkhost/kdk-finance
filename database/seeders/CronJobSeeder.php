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

namespace Database\Seeders;

use App\Models\CronJob;
use Illuminate\Database\Seeder;

class CronJobSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CronJob::systemDefaults() as $job) {
            CronJob::query()->firstOrCreate(
                ['command' => $job['command']],
                $job + ['type' => CronJob::TYPE_ARTISAN, 'enabled' => true]
            );
        }
    }
}
