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

class CurrencyConversion extends Model
{
    protected $fillable = [
        'description',
        'from_currency',
        'to_currency',
        'amount_from',
        'market_rate',
        'spread_percent',
        'iof_percent',
        'buffer_percent',
        'fixed_fee',
        'base_to',
        'spread_amount',
        'iof_amount',
        'buffer_amount',
        'fees_total',
        'total_to',
        'effective_rate',
        'to_brl_rate',
        'total_brl',
        'statement_amount',
        'statement_cost_percent',
        'statement_checked_at',
        'rate_source',
        'rate_at',
        'rate_stale',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount_from' => 'decimal:2',
        'market_rate' => 'decimal:6',
        'spread_percent' => 'decimal:3',
        'iof_percent' => 'decimal:3',
        'buffer_percent' => 'decimal:3',
        'fixed_fee' => 'decimal:2',
        'base_to' => 'decimal:2',
        'spread_amount' => 'decimal:2',
        'iof_amount' => 'decimal:2',
        'buffer_amount' => 'decimal:2',
        'fees_total' => 'decimal:2',
        'total_to' => 'decimal:2',
        'effective_rate' => 'decimal:6',
        'to_brl_rate' => 'decimal:4',
        'total_brl' => 'decimal:2',
        'statement_amount' => 'decimal:2',
        'statement_cost_percent' => 'decimal:3',
        'statement_checked_at' => 'datetime',
        'rate_at' => 'datetime',
        'rate_stale' => 'boolean',
    ];

    public static function currencies(): array
    {
        return [
            'EUR' => 'EUR · Euro',
            'USD' => 'USD · Dólar americano',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pairLabel(): string
    {
        return $this->from_currency.' → '.$this->to_currency;
    }

    public function fromSymbol(): string
    {
        return (string) config('finance.currencies.'.$this->from_currency.'.symbol', $this->from_currency);
    }

    public function toSymbol(): string
    {
        return (string) config('finance.currencies.'.$this->to_currency.'.symbol', $this->to_currency);
    }

    /**
     * Custo total acima da cotação de mercado, em percentual.
     */
    public function costPercent(): float
    {
        $market = (float) $this->market_rate;

        if ($market <= 0) {
            return 0.0;
        }

        return round((((float) $this->effective_rate / $market) - 1) * 100, 3);
    }

    public function isCalibrated(): bool
    {
        return $this->statement_amount !== null;
    }

    public function statementDifference(): ?float
    {
        if ($this->statement_amount === null) {
            return null;
        }

        return round((float) $this->statement_amount - (float) $this->total_to, 2);
    }
}
