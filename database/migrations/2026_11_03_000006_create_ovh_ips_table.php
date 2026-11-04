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
        Schema::create('ovh_ips', function (Blueprint $table) {
            $table->id();
            $table->string('block')->unique();
            $table->unsignedTinyInteger('version')->default(4);
            $table->string('type')->nullable()->index();
            $table->string('country', 5)->nullable();
            $table->string('campus')->nullable();
            $table->string('rir')->nullable();
            $table->string('routed_to')->nullable()->index();
            $table->boolean('is_additional')->default(false);
            $table->boolean('can_be_terminated')->default(false);
            $table->string('description')->nullable();
            $table->json('reverse_dns')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovh_ips');
    }
};
