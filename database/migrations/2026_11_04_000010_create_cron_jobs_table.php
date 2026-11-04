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
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('command', 500);
            $table->string('type', 20)->default('artisan');
            $table->string('expression', 100)->default('* * * * *');
            $table->string('timezone', 60)->default('America/Sao_Paulo');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->smallInteger('last_exit_code')->nullable();
            $table->text('last_output')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
