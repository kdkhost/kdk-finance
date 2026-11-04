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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_conversions', function (Blueprint $table) {
            // Cotação praticada pelo provedor (PayPal, banco, emissor), sempre
            // normalizada na direção da conversão (1 origem = ? destino).
            $table->decimal('provider_rate', 12, 6)->nullable()->after('market_rate');
            // Verdadeiro quando o recibo trouxe a taxa na direção inversa
            // (ex.: 1 USD = 0,8498 EUR num pagamento EUR → USD).
            $table->boolean('provider_rate_inverted')->default(false)->after('provider_rate');
        });
    }

    public function down(): void
    {
        Schema::table('currency_conversions', function (Blueprint $table) {
            $table->dropColumn(['provider_rate', 'provider_rate_inverted']);
        });
    }
};
