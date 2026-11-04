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
        Schema::create('ovh_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_id')->unique();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('type')->nullable()->index();
            $table->string('route_path')->nullable();
            $table->string('route_url')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('renew_mode')->nullable();
            $table->string('renew_interval')->nullable();
            $table->date('creation_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->decimal('monthly_price_eur', 12, 2)->nullable();
            $table->string('billing_domain')->nullable()->index();
            $table->string('last_bill_id')->nullable();
            $table->date('last_billed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovh_services');
    }
};
