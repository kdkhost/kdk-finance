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

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CurrencyConversion;

class CurrencyConversionService
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    /**
     * Tarifas padrão da conversão (banco > configuração).
     *
     * @return array{spread_percent: float, iof_percent: float, fixed_fee: float, buffer_percent: float}
     */
    public function defaults(): array
    {
        return [
            'spread_percent' => (float) AppSetting::get('conversion_spread_percent', config('finance.conversion.spread_percent', 0)),
            'iof_percent' => (float) AppSetting::get('conversion_iof_percent', config('finance.conversion.iof_percent', 0)),
            'fixed_fee' => (float) AppSetting::get('conversion_fixed_fee', config('finance.conversion.fixed_fee', 0)),
            'buffer_percent' => (float) AppSetting::get('conversion_buffer_percent', config('finance.conversion.buffer_percent', 0)),
        ];
    }

    /**
     * Cotação de mercado do par, com referência em BRL.
     *
     * @return array<string, mixed>
     */
    public function quote(string $from, string $to, bool $fresh = false): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $pair = $this->rates->pair($from, $to, $fresh);

        // Referência em reais para o usuário enxergar o custo na moeda corrente.
        $toBrl = $this->rates->rate($to);
        $pair['to_brl_rate'] = round($toBrl, 4);

        return $pair;
    }

    /**
     * Fórmula completa da conversão, passo a passo e com arredondamento
     * financeiro em cada etapa:
     *
     * base            = valor_origem * cotação
     * spread          = base * spread%
     * subtotal        = base + spread
     * iof             = subtotal * iof%
     * buffer          = (subtotal + iof + taxa fixa) * buffer%
     * total           = subtotal + iof + taxa fixa + buffer
     * valor_efetivo   = total / valor_origem  (cotação realmente paga)
     *
     * @param  array<string, float|int|string|null>  $overrides
     * @return array<string, float>
     */
    public function calculate(float $amountFrom, float $marketRate, array $overrides = []): array
    {
        $fees = array_merge($this->defaults(), array_filter(
            [
                'spread_percent' => $overrides['spread_percent'] ?? null,
                'iof_percent' => $overrides['iof_percent'] ?? null,
                'fixed_fee' => $overrides['fixed_fee'] ?? null,
                'buffer_percent' => $overrides['buffer_percent'] ?? null,
            ],
            fn ($value): bool => $value !== null && $value !== ''
        ));

        $amountFrom = round(max($amountFrom, 0), 2);
        $marketRate = round(max($marketRate, 0), 6);

        $base = round($amountFrom * $marketRate, 2);
        $spread = round($base * ($fees['spread_percent'] / 100), 2);
        $subtotal = round($base + $spread, 2);
        $iof = round($subtotal * ($fees['iof_percent'] / 100), 2);
        $fixed = round(max($fees['fixed_fee'], 0), 2);
        $buffer = round(($subtotal + $iof + $fixed) * ($fees['buffer_percent'] / 100), 2);
        $total = round($subtotal + $iof + $fixed + $buffer, 2);

        return [
            'amount_from' => $amountFrom,
            'market_rate' => $marketRate,
            'base_to' => $base,
            'spread_percent' => round($fees['spread_percent'], 3),
            'spread_amount' => $spread,
            'iof_percent' => round($fees['iof_percent'], 3),
            'iof_amount' => $iof,
            'fixed_fee' => $fixed,
            'buffer_percent' => round($fees['buffer_percent'], 3),
            'buffer_amount' => $buffer,
            'fees_total' => round($total - $base, 2),
            'total_to' => $total,
            'effective_rate' => $amountFrom > 0 ? round($total / $amountFrom, 6) : 0.0,
            'cost_percent' => $base > 0 ? round((($total / $base) - 1) * 100, 3) : 0.0,
        ];
    }

    /**
     * Calcula e devolve um array pronto para preencher o model.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function build(array $input): array
    {
        $from = strtoupper((string) ($input['from_currency'] ?? 'EUR'));
        $to = strtoupper((string) ($input['to_currency'] ?? 'USD'));
        $amount = (float) ($input['amount_from'] ?? 0);
        $marketRate = (float) ($input['market_rate'] ?? 0);

        $quote = [];
        $stale = false;
        $source = null;
        $rateAt = null;

        // Sem cotação informada: busca a de mercado e registra a origem.
        if ($marketRate <= 0) {
            $quote = $this->quote($from, $to, (bool) ($input['refresh_rate'] ?? false));
            $marketRate = (float) $quote['rate'];
            $stale = (bool) $quote['stale'];
            $source = (string) $quote['source'];
            $rateAt = $quote['updated_at'];
        }

        $result = $this->calculate($amount, $marketRate, [
            'spread_percent' => $input['spread_percent'] ?? null,
            'iof_percent' => $input['iof_percent'] ?? null,
            'fixed_fee' => $input['fixed_fee'] ?? null,
            'buffer_percent' => $input['buffer_percent'] ?? null,
        ]);

        $toBrlRate = (float) ($quote['to_brl_rate'] ?? $this->rates->rate($to));

        return array_merge($result, [
            'description' => trim((string) ($input['description'] ?? '')),
            'from_currency' => $from,
            'to_currency' => $to,
            'to_brl_rate' => round($toBrlRate, 4),
            'total_brl' => round($result['total_to'] * $toBrlRate, 2),
            'vet_brl' => $result['amount_from'] > 0
                ? round(($result['total_to'] * $toBrlRate) / $result['amount_from'], 6)
                : 0.0,
            'rate_source' => $source ?? ($input['rate_source'] ?? 'informada manualmente'),
            'rate_at' => $rateAt,
            'rate_stale' => $stale,
            'notes' => $input['notes'] ?? null,
        ]);
    }

    /**
     * Recalcula uma conversão já salva com a cotação atual.
     *
     * @return array<string, mixed>
     */
    public function recalculate(CurrencyConversion $conversion, bool $fresh = true): array
    {
        $quote = $this->quote($conversion->from_currency, $conversion->to_currency, $fresh);

        $result = $this->calculate((float) $conversion->amount_from, (float) $quote['rate'], [
            'spread_percent' => (float) $conversion->spread_percent,
            'iof_percent' => (float) $conversion->iof_percent,
            'fixed_fee' => (float) $conversion->fixed_fee,
            'buffer_percent' => (float) $conversion->buffer_percent,
        ]);

        return array_merge($result, [
            'to_brl_rate' => round((float) $quote['to_brl_rate'], 4),
            'total_brl' => round($result['total_to'] * (float) $quote['to_brl_rate'], 2),
            'vet_brl' => $result['amount_from'] > 0
                ? round(($result['total_to'] * (float) $quote['to_brl_rate']) / $result['amount_from'], 6)
                : 0.0,
            'rate_source' => (string) $quote['source'],
            'rate_at' => $quote['updated_at'],
            'rate_stale' => (bool) $quote['stale'],
        ]);
    }
}
