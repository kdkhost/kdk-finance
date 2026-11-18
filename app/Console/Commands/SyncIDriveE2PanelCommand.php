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

use App\Services\IDriveE2PanelService;
use Illuminate\Console\Command;
use Throwable;

class SyncIDriveE2PanelCommand extends Command
{
    protected $signature = 'idrivee2:sync-panel {--otp= : Código 2FA de 6 dígitos opcional}';

    protected $description = 'Sincroniza os serviços S3 e faturas do painel IDrive e2 (https://console.idrivee2.com)';

    public function handle(IDriveE2PanelService $panel): int
    {
        if (! $panel->isConfigured()) {
            $this->warn('Credenciais do IDrive e2 não configuradas em Configurações → IDrive e2 (S3).');

            return self::SUCCESS;
        }

        try {
            $result = $panel->syncPanel($this->option('otp') ? (string) $this->option('otp') : null);
        } catch (Throwable $exception) {
            $this->error('Falha ao sincronizar o IDrive e2: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($result['requires_2fa'] && ! $result['ok']) {
            $this->warn($result['message']);

            return self::SUCCESS;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}
