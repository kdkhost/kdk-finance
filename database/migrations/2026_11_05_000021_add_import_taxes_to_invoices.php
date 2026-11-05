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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa o que é cobrado na fatura do cartão do que é tributo da importação
 * de serviços, e cria o histórico das sincronizações de tarifas.
 *
 * As faturas já existentes são preservadas: recebem card_total = total atual,
 * import_taxes = 0 e o interruptor de tributos desligado. Só as novas faturas
 * passam a aplicar os tributos habilitados nas configurações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('card_total_brl', 12, 2)->nullable()->after('total_brl');
            $table->decimal('import_taxes_amount', 12, 2)->default(0)->after('card_total_brl');
            $table->json('tax_breakdown')->nullable()->after('import_taxes_amount');
            $table->boolean('apply_service_taxes')->default(false)->after('tax_breakdown');
            // O tributo da importação depende da natureza: serviço (PIS/COFINS,
            // IRRF, ISS, CIDE) ou produto físico (ICMS, Imposto de Importação).
            $table->string('tax_nature', 10)->default('service')->after('apply_service_taxes');
        });

        DB::table('invoices')->update([
            'card_total_brl' => DB::raw('total_brl'),
            'import_taxes_amount' => 0,
            'apply_service_taxes' => false,
        ]);

        Schema::create('fee_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40)->index();
            $table->string('status', 20);
            $table->decimal('value', 12, 4)->nullable();
            $table->string('note', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $this->seedSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_sync_runs');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'card_total_brl',
                'import_taxes_amount',
                'tax_breakdown',
                'apply_service_taxes',
                'tax_nature',
            ]);
        });
    }

    /**
     * Grava somente as chaves ausentes e corrige o IOF antigo (3,38%).
     * Valores personalizados pelo administrador são preservados.
     */
    private function seedSettings(): void
    {
        $defaults = [
            'spread_percent' => '2.00',
            'iof_percent' => '3.50',
            'card_fixed_fee' => '0.00',
            'card_spread_auto' => '0',
            'service_taxes_enabled' => '1',
            'pis_cofins_percent' => '9.25',
            'pis_cofins_enabled' => '1',
            'irrf_percent' => '15.00',
            'irrf_enabled' => '1',
            'iss_percent' => '5.00',
            'iss_enabled' => '1',
            'cide_percent' => '10.00',
            'cide_enabled' => '0',
            'cbs_percent' => '0.90',
            'cbs_enabled' => '0',
            'ibs_percent' => '0.10',
            'ibs_enabled' => '0',
            'other_taxes_percent' => '0.00',
            'other_taxes_enabled' => '0',
            'icms_percent' => '17.00',
            'icms_enabled' => '0',
            'import_tax_percent' => '60.00',
            'import_tax_enabled' => '0',
            'import_tax_deduction_usd' => '30.00',
        ];

        foreach ($defaults as $key => $value) {
            $existing = DB::table('app_settings')->where('key', $key)->first();

            if (! $existing) {
                DB::table('app_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'group' => 'taxes',
                    'is_secret' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            // O IOF ficou em 3,38% entre setembro e outubro de 2025; desde
            // 17/07/2025 a alíquota legal é 3,5%. Só corrige o valor antigo.
            if ($key === 'iof_percent' && in_array((string) $existing->value, ['3.38', '3.3800'], true)) {
                DB::table('app_settings')->where('id', $existing->id)->update([
                    'value' => '3.50',
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
