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
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_services', function (Blueprint $table) {
            $table->foreignId('license_catalog_item_id')
                ->nullable()
                ->after('status')
                ->constrained('license_catalog_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('license_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('license_catalog_item_id');
        });
    }
};
