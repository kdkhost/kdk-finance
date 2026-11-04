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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJobRun extends Model
{
    protected $fillable = [
        'cron_job_id',
        'trigger',
        'started_at',
        'finished_at',
        'exit_code',
        'duration_ms',
        'output',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'exit_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'cron_job_id');
    }

    public function isSuccessful(): bool
    {
        return $this->exit_code === 0;
    }

    public function triggerLabel(): string
    {
        return $this->trigger === 'manual' ? 'Manual' : 'Agendado';
    }

    public function durationLabel(): string
    {
        if ($this->duration_ms < 1000) {
            return $this->duration_ms.' ms';
        }

        return number_format($this->duration_ms / 1000, 2, ',', '.').' s';
    }
}
