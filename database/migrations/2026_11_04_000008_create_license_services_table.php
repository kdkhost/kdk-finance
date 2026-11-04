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
        Schema::create('license_services', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 80)->default('LicenseBoy');
            $table->string('product', 80);
            $table->string('name', 150);
            $table->string('server_name', 190)->nullable();
            $table->text('license_key')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'semiannual', 'annual', 'lifetime'])->default('monthly');
            $table->date('next_due_date')->nullable()->index();
            $table->enum('status', ['active', 'expired', 'canceled'])->default('active')->index();
            $table->boolean('auto_renew')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('last_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_services');
    }
};
