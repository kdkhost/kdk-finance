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
        Schema::create('license_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 80)->default('LicenseBoy');
            $table->string('external_id', 40)->nullable();
            $table->string('category', 80);
            $table->string('category_name', 120);
            $table->string('name', 190);
            $table->string('slug', 190);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('price_is_from')->default(false);
            $table->char('currency', 3)->default('USD');
            $table->string('billing_cycle', 20)->nullable();
            $table->decimal('setup_fee', 10, 2)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'slug']);
            $table->index(['provider', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_catalog_items');
    }
};
