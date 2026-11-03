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
use App\Models\Invoice;

class TaxCalculationService
{
    /**
     * Percentuais efetivos (banco > configuração padrão).
     *
     * @return array{spread: float, iof: float, icms: float, outros: float}
     */
    public function percentages(): array
    {
        return [
            'spread' => (float) AppSetting::get('spread_percent', config('finance.taxes.spread_percent')),
            'iof' => (float) AppSetting::get('iof_percent', config('finance.taxes.iof_percent')),
            'icms' => (float) AppSetting::get('icms_percent', config('finance.taxes.icms_percent')),
            'outros' => (float) AppSetting::get('other_taxes_percent', config('finance.taxes.other_taxes_percent')),
        ];
    }

    /**
     * Aplica a fórmula completa e devolve todos os valores discriminados.
     *
     * base_brl        = valor_original * cotacao
     * spread          = base_brl * spread%
     * base_com_spread = base_brl + spread
     * iof / icms / outros = base_com_spread * respectivo%
     * total_brl       = base_com_spread + iof + icms + outros
     *
     * @param  array<string, float>  $overrides  Percentuais que substituem o banco.
     * @return array<string, float>
     */
    public function calculate(float $amountOriginal, float $exchangeRate, array $overrides = []): array
    {
        $percentages = array_merge($this->percentages(), $overrides);

        $baseBrl = round($amountOriginal * $exchangeRate, 2);
        $spread = round($baseBrl * ($percentages['spread'] / 100), 2);
        $baseWithSpread = round($baseBrl + $spread, 2);
        $iof = round($baseWithSpread * ($percentages['iof'] / 100), 2);
        $icms = round($baseWithSpread * ($percentages['icms'] / 100), 2);
        $others = round($baseWithSpread * ($percentages['outros'] / 100), 2);
        $total = round($baseWithSpread + $iof + $icms + $others, 2);

        return [
            'amount_original' => round($amountOriginal, 2),
            'exchange_rate' => round($exchangeRate, 4),
            'base_brl' => $baseBrl,
            'spread_percent' => round($percentages['spread'], 4),
            'spread_amount' => $spread,
            'base_with_spread_brl' => $baseWithSpread,
            'iof_percent' => round($percentages['iof'], 4),
            'iof_amount' => $iof,
            'icms_percent' => round($percentages['icms'], 4),
            'icms_amount' => $icms,
            'other_taxes_percent' => round($percentages['outros'], 4),
            'other_taxes_amount' => $others,
            'taxes_total' => round($spread + $iof + $icms + $others, 2),
            'total_brl' => $total,
        ];
    }

    /**
     * Aplica o cálculo diretamente na fatura e persiste os valores.
     *
     * @param  array<string, float>  $overrides
     * @return array<string, float>
     */
    public function applyToInvoice(Invoice $invoice, float $exchangeRate, array $overrides = []): array
    {
        $result = $this->calculate((float) $invoice->amount_original, $exchangeRate, $overrides);

        $invoice->forceFill([
            'exchange_rate' => $result['exchange_rate'],
            'spread_amount' => $result['spread_amount'],
            'iof_amount' => $result['iof_amount'],
            'icms_amount' => $result['icms_amount'],
            'other_taxes_amount' => $result['other_taxes_amount'],
            'total_brl' => $result['total_brl'],
            'calculated_at' => now(),
        ])->save();

        return $result;
    }
}
