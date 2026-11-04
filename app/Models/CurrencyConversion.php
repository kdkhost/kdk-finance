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
        'provider_rate',
        'provider_rate_inverted',
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
        'provider_rate' => 'decimal:6',
        'provider_rate_inverted' => 'boolean',
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

    /**
     * VET (Valor Efetivo Total) em reais por unidade da moeda de origem:
     * tudo o que a operação custou (câmbio + spread + IOF + tarifa + margem)
     * dividido pelo valor convertido. É a taxa que o app do banco exibe.
     */
    public function vetBrl(): float
    {
        $amount = (float) $this->amount_from;

        if ($amount <= 0) {
            return 0.0;
        }

        return round((float) $this->total_brl / $amount, 6);
    }

    /**
     * Cotação do provedor exatamente como o recibo mostrou. Quando o recibo
     * trouxe a direção inversa, devolve 1 / provider_rate.
     */
    public function providerRateRaw(): ?float
    {
        if ($this->provider_rate === null) {
            return null;
        }

        $rate = (float) $this->provider_rate;

        if ($this->provider_rate_inverted && $rate > 0) {
            return round(1 / $rate, 6);
        }

        return $rate;
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
