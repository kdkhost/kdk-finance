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
        Schema::create('currency_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('description', 150);
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('amount_from', 12, 2);
            $table->decimal('market_rate', 12, 6);
            $table->decimal('spread_percent', 6, 3)->default(0);
            $table->decimal('iof_percent', 6, 3)->default(0);
            $table->decimal('buffer_percent', 6, 3)->default(0);
            $table->decimal('fixed_fee', 12, 2)->default(0);
            $table->decimal('base_to', 12, 2)->default(0);
            $table->decimal('spread_amount', 12, 2)->default(0);
            $table->decimal('iof_amount', 12, 2)->default(0);
            $table->decimal('buffer_amount', 12, 2)->default(0);
            $table->decimal('fees_total', 12, 2)->default(0);
            $table->decimal('total_to', 12, 2)->default(0);
            $table->decimal('effective_rate', 12, 6)->default(0);
            $table->decimal('to_brl_rate', 12, 4)->nullable();
            $table->decimal('total_brl', 12, 2)->nullable();
            $table->decimal('statement_amount', 12, 2)->nullable();
            $table->decimal('statement_cost_percent', 6, 3)->nullable();
            $table->timestamp('statement_checked_at')->nullable();
            $table->string('rate_source', 100)->nullable();
            $table->timestamp('rate_at')->nullable();
            $table->boolean('rate_stale')->default(false);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['from_currency', 'to_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_conversions');
    }
};
