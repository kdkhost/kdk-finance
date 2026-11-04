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
        Schema::create('cron_job_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cron_job_id')->constrained('cron_jobs')->cascadeOnDelete();
            $table->string('trigger', 20)->default('schedule');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->smallInteger('exit_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('output')->nullable();
            $table->timestamps();

            $table->index(['cron_job_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_job_runs');
    }
};
