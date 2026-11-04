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

namespace App\Console\Commands;

use App\Services\LicenseBoyPanelService;
use Illuminate\Console\Command;
use Throwable;

class SyncLicensePanelCommand extends Command
{
    protected $signature = 'licenses:sync-panel';

    protected $description = 'Lê o painel da LicenseBoy e grava o valor realmente cobrado em cada licença';

    public function handle(LicenseBoyPanelService $panel): int
    {
        if (! $panel->isConfigured()) {
            $this->error('Informe o e-mail e a senha do painel da LicenseBoy em Configurações → LicenseBoy.');

            return self::FAILURE;
        }

        try {
            $services = $panel->services();
        } catch (Throwable $exception) {
            $this->error('Não foi possível ler o painel: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($services === []) {
            $this->warn('Nenhum serviço ativo foi encontrado no painel.');

            return self::FAILURE;
        }

        $result = $panel->apply($services);

        $this->info(sprintf(
            'Painel: %d serviços lidos · %d licenças atualizadas.',
            count($services),
            $result['updated'],
        ));

        foreach ($result['unmatched'] as $name) {
            $this->warn('Sem correspondência no painel: '.$name);
        }

        return self::SUCCESS;
    }
}
