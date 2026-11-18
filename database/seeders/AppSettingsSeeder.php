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

use App\Models\AppSetting;
use Database\Seeders\Concerns\ReadsLocalCredentials;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    use ReadsLocalCredentials;

    public function run(): void
    {
        $ovh = $this->localCredentials()['ovh'] ?? [];

        // ------------------------------------------------------------------
        // OVHcloud — valores sigilosos ficam criptografados no banco
        // ------------------------------------------------------------------
        AppSetting::set(
            'ovh_endpoint',
            $ovh['endpoint'] ?? env('OVH_ENDPOINT', 'ovh-eu'),
            'ovh'
        );

        $this->putSecret('ovh_application_key', $ovh['application_key'] ?? env('OVH_APPLICATION_KEY'));
        $this->putSecret('ovh_application_secret', $ovh['application_secret'] ?? env('OVH_APPLICATION_SECRET'));
        $this->putSecret('ovh_consumer_key', $ovh['consumer_key'] ?? env('OVH_CONSUMER_KEY'));
        $this->putSecret('ovh_v2_bearer_token', $ovh['v2_bearer_token'] ?? env('OVH_V2_BEARER_TOKEN'));

        // ------------------------------------------------------------------
        // Tributos padrão
        // ------------------------------------------------------------------
        // Encargos do cartão (fatura do banco).
        AppSetting::set('spread_percent', '2.00', 'taxes');
        AppSetting::set('iof_percent', '3.50', 'taxes');
        AppSetting::set('card_fixed_fee', '0.00', 'taxes');
        AppSetting::set('card_spread_auto', '0', 'taxes');
        // Tributos da importação de serviços.
        AppSetting::set('service_taxes_enabled', '1', 'taxes');
        AppSetting::set('pis_cofins_percent', '9.25', 'taxes');
        AppSetting::set('pis_cofins_enabled', '1', 'taxes');
        AppSetting::set('irrf_percent', '15.00', 'taxes');
        AppSetting::set('irrf_enabled', '1', 'taxes');
        AppSetting::set('iss_percent', '5.00', 'taxes');
        AppSetting::set('iss_enabled', '1', 'taxes');
        AppSetting::set('cide_percent', '10.00', 'taxes');
        AppSetting::set('cide_enabled', '0', 'taxes');
        AppSetting::set('cbs_percent', '0.90', 'taxes');
        AppSetting::set('cbs_enabled', '0', 'taxes');
        AppSetting::set('ibs_percent', '0.10', 'taxes');
        AppSetting::set('ibs_enabled', '0', 'taxes');
        AppSetting::set('other_taxes_percent', '0.00', 'taxes');
        AppSetting::set('other_taxes_enabled', '0', 'taxes');
        // Importação de bens.
        AppSetting::set('icms_percent', '17.00', 'taxes');
        AppSetting::set('icms_enabled', '0', 'taxes');
        AppSetting::set('import_tax_percent', '60.00', 'taxes');
        AppSetting::set('import_tax_enabled', '0', 'taxes');
        AppSetting::set('import_tax_deduction_usd', '30.00', 'taxes');

        // ------------------------------------------------------------------
        // Procedência das tarifas (preenchida pelo finance:sync-fees)
        // ------------------------------------------------------------------
        AppSetting::set('card_spread_source', 'manual', 'fees');

        // ------------------------------------------------------------------
        // Regras operacionais
        // ------------------------------------------------------------------
        AppSetting::set('rate_variation_threshold', '1.00', 'finance');
        AppSetting::set('ovh_auto_import', '1', 'ovh');
        AppSetting::set('ovh_import_days', '365', 'ovh');
        AppSetting::set('ovh_import_limit', '100', 'ovh');
        AppSetting::set('notify_email', 'contato@kdkhost.com.br', 'mail');

        $this->command?->info('Configurações iniciais gravadas (credenciais OVHcloud criptografadas).');
    }

    private function putSecret(string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->command?->warn('Credencial '.$key.' não informada; mantida sem valor.');

            return;
        }

        AppSetting::set($key, trim($value), 'ovh', true);
    }
}
