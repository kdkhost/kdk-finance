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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('external_reference')->nullable()->unique();
            $table->string('description');
            $table->string('currency', 3);
            $table->decimal('amount_original', 12, 2);
            $table->decimal('exchange_rate', 10, 4)->default(0);
            $table->decimal('spread_amount', 10, 2)->default(0);
            $table->decimal('iof_amount', 10, 2)->default(0);
            $table->decimal('icms_amount', 10, 2)->default(0);
            $table->decimal('other_taxes_amount', 10, 2)->default(0);
            $table->decimal('total_brl', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'canceled'])->default('pending')->index();
            $table->date('due_date')->index();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('rate_locked')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
