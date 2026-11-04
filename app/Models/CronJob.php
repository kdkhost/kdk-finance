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

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class CronJob extends Model
{
    public const TYPE_ARTISAN = 'artisan';
    public const TYPE_SHELL = 'shell';

    protected $fillable = [
        'name',
        'command',
        'type',
        'expression',
        'timezone',
        'description',
        'enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_exit_code' => 'integer',
            'run_count' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ARTISAN => 'Comando Artisan (php artisan)',
            self::TYPE_SHELL => 'Comando do sistema (shell)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function presets(): array
    {
        return [
            '* * * * *' => 'A cada minuto',
            '*/5 * * * *' => 'A cada 5 minutos',
            '*/15 * * * *' => 'A cada 15 minutos',
            '0 * * * *' => 'A cada hora',
            '0 3 * * *' => 'Diariamente às 03:00',
            '0 3 * * 1' => 'Semanalmente (segunda, 03:00)',
            '0 3 1 * *' => 'Mensalmente (dia 1, 03:00)',
        ];
    }

    /**
     * Crons que mantêm o sistema atualizado. São gravados na instalação e
     * podem ser restaurados pelo botão "Restaurar padrões" da Central de cron.
     *
     * @return list<array{name: string, command: string, expression: string, timezone: string, description: string}>
     */
    public static function systemDefaults(): array
    {
        return [
            [
                'name' => 'Atualizar cotações de câmbio',
                'command' => 'finance:refresh-rates',
                'expression' => '*/30 * * * *',
                'timezone' => 'America/Sao_Paulo',
                'description' => 'Mantém USD e EUR atualizados no cache e grava a última cotação válida.',
            ],
            [
                'name' => 'Sincronizar faturas OVHcloud',
                'command' => 'finance:sync-invoices',
                'expression' => '*/15 * * * *',
                'timezone' => 'America/Sao_Paulo',
                'description' => 'Importa faturas, itens, serviços e blocos de IP da OVHcloud e recalcula os valores em BRL.',
            ],
            [
                'name' => 'Sincronizar preços das licenças',
                'command' => 'licenses:sync-prices',
                'expression' => '10 5 * * *',
                'timezone' => 'America/Sao_Paulo',
                'description' => 'Lê a tabela de preços da LicenseBoy e atualiza o catálogo e as licenças vinculadas.',
            ],
            [
                'name' => 'Sincronizar painel LicenseBoy',
                'command' => 'licenses:sync-panel',
                'expression' => '25 5 * * *',
                'timezone' => 'America/Sao_Paulo',
                'description' => 'Lê o valor realmente cobrado no painel da LicenseBoy e sinaliza divergências.',
            ],
            [
                'name' => 'Alertas de vencimento',
                'command' => 'finance:notify-alerts',
                'expression' => '0 8 * * *',
                'timezone' => 'America/Sao_Paulo',
                'description' => 'Envia e-mails de faturas a vencer, faturas vencidas e licenças próximas do vencimento.',
            ],
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CronJobRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    public function isDue(?string $at = null): bool
    {
        try {
            return (new CronExpression($this->expression))->isDue($at ?? now(), $this->timezone);
        } catch (Throwable) {
            return false;
        }
    }

    public function nextRun(): ?\Illuminate\Support\Carbon
    {
        try {
            $next = (new CronExpression($this->expression))
                ->getNextRunDate(now($this->timezone), 0, true, $this->timezone);

            return \Illuminate\Support\Carbon::instance($next)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    public function expressionLabel(): string
    {
        return self::presets()[$this->expression] ?? $this->expression;
    }

    public function statusLabel(): string
    {
        if (! $this->enabled) {
            return 'Desativado';
        }

        if ($this->last_exit_code !== null && $this->last_exit_code !== 0) {
            return 'Com falha';
        }

        return 'Ativo';
    }

    public function statusBadgeClass(): string
    {
        if (! $this->enabled) {
            return 'badge-status badge-status--canceled';
        }

        if ($this->last_exit_code !== null && $this->last_exit_code !== 0) {
            return 'badge-status badge-status--pending';
        }

        return 'badge-status badge-status--paid';
    }

    public static function isValidExpression(string $expression): bool
    {
        try {
            return CronExpression::isValidExpression($expression);
        } catch (Throwable) {
            return false;
        }
    }
}
