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

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class RefreshRatesCommand extends Command
{
    protected $signature = 'finance:refresh-rates';

    protected $description = 'Consulta os provedores de câmbio e renova o cache de USD e EUR';

    public function handle(ExchangeRateService $exchange): int
    {
        $rates = $exchange->rates(true);
        $stale = true;

        foreach ($rates as $currency => $data) {
            $stale = $stale && (bool) ($data['stale'] ?? true);

            $this->line(sprintf(
                '  %s: R$ %s · %s%s',
                $currency,
                number_format((float) ($data['rate'] ?? 0), 4, ',', '.'),
                (string) ($data['source'] ?? '—'),
                ($data['stale'] ?? false) ? ' (última conhecida)' : ''
            ));
        }

        if ($stale) {
            $this->warn('Nenhum provedor respondeu; mantidas as últimas cotações conhecidas.');

            return self::SUCCESS;
        }

        $this->info('Cotações atualizadas e cacheadas.');

        return self::SUCCESS;
    }
}
