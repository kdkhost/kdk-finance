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

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Invoice;

/**
 * Calcula a fatura em duas camadas:
 *
 * 1. Encargos do cartão — o que aparece na fatura do banco: spread cambial,
 *    IOF e tarifa fixa.
 * 2. Tributos da importação — o que a empresa recolhe por conta própria ao
 *    importar serviços (PIS/COFINS-Importação, IRRF, ISS, CIDE) ou bens
 *    (ICMS, Imposto de Importação).
 *
 * A separação evita comparar a fatura do cartão com um total que já inclui
 * tributos que o banco não cobra. O "total a reservar" é a soma das camadas.
 *
 * Alíquotas legais vigentes em 2026 (todas editáveis no painel):
 * - IOF cartão/remessa: 3,5% — Decreto 6.306/2007, art. 15-B, VII e XXIV,
 *   redação do Decreto 12.499/2025, restabelecido pelo STF em 16/07/2025.
 * - PIS/COFINS-Importação de serviços: 1,65% + 7,6% = 9,25%.
 * - IRRF: 15% (25% para paraíso fiscal).
 * - ISS: 2% a 5%, conforme o município.
 * - CIDE: 10% somente em royalties, serviços técnicos e transferência de
 *   tecnologia.
 * - ICMS de importação: 17% a 20%, conforme o estado (bens).
 * - Imposto de Importação: 60% acima de US$ 50, com dedução de US$ 30 em
 *   plataformas do Remessa Conforme (bens; para CNPJ incide desde a primeira
 *   faixa).
 * - CBS/IBS 2026: fase de teste (0,9% + 0,1%), compensáveis com PIS/COFINS.
 */
class TaxCalculationService
{
    public const GROUP_CARD = 'cartao';
    public const GROUP_SERVICE = 'servicos';
    public const GROUP_GOODS = 'bens';

    /**
     * Percentuais efetivos (banco > configuração padrão).
     *
     * @return array<string, float|bool>
     */
    public function percentages(): array
    {
        return [
            'spread' => $this->rate('spread_percent'),
            'iof' => $this->rate('iof_percent'),
            'icms' => $this->rate('icms_percent'),
            'outros' => $this->rate('other_taxes_percent'),
            'other' => $this->rate('other_taxes_percent'),
            'card_fixed_fee' => $this->rate('card_fixed_fee'),
            'pis_cofins' => $this->rate('pis_cofins_percent'),
            'irrf' => $this->rate('irrf_percent'),
            'iss' => $this->rate('iss_percent'),
            'cide' => $this->rate('cide_percent'),
            'cbs' => $this->rate('cbs_percent'),
            'ibs' => $this->rate('ibs_percent'),
            'import_tax' => $this->rate('import_tax_percent'),
            'import_tax_deduction_usd' => $this->rate('import_tax_deduction_usd'),
            'service_taxes_enabled' => $this->enabled('service_taxes_enabled', true),
            'cide_enabled' => $this->enabled('cide_enabled', false),
            'icms_enabled' => $this->enabled('icms_enabled', false),
            'import_tax_enabled' => $this->enabled('import_tax_enabled', false),
            'cbs_enabled' => $this->enabled('cbs_enabled', false),
            'ibs_enabled' => $this->enabled('ibs_enabled', false),
            'pis_cofins_enabled' => $this->enabled('pis_cofins_enabled', true),
            'irrf_enabled' => $this->enabled('irrf_enabled', true),
            'iss_enabled' => $this->enabled('iss_enabled', true),
            'other_taxes_enabled' => $this->enabled('other_taxes_enabled', false),
            'other_enabled' => $this->enabled('other_taxes_enabled', false),
            'card_spread_auto' => $this->enabled('card_spread_auto', false),
        ];
    }

    /**
     * Catálogo dos tributos e tarifas, com a origem e a base legal de cada um.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $p = $this->percentages();

        return [
            [
                'key' => 'spread',
                'label' => 'Spread da bandeira/banco',
                'group' => self::GROUP_CARD,
                'percent' => $p['spread'],
                'enabled' => true,
                'source' => (string) AppSetting::get('card_spread_source', 'manual'),
                'legal' => 'Custo do banco/bandeira, não é tributo.',
                'note' => '',
            ],
            [
                'key' => 'iof',
                'label' => 'IOF — cartão internacional',
                'group' => self::GROUP_CARD,
                'percent' => $p['iof'],
                'enabled' => true,
                'source' => 'Receita Federal',
                'legal' => 'Decreto 6.306/2007, art. 15-B, VII (3,5% desde 11/06/2025).',
                'note' => '',
            ],
            [
                'key' => 'card_fee',
                'label' => 'Tarifa fixa do banco/cartão',
                'group' => self::GROUP_CARD,
                'percent' => null,
                'fixed' => true,
                'enabled' => true,
                'source' => 'Banco (manual)',
                'legal' => 'Tarifa contratada com o banco, não é tributo.',
                'note' => 'Bancos não publicam API de tarifas; o valor é mantido no painel.',
            ],
            [
                'key' => 'pis_cofins',
                'label' => 'PIS/COFINS-Importação',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['pis_cofins'],
                'enabled' => (bool) $p['pis_cofins_enabled'],
                'source' => 'Receita Federal',
                'legal' => 'Lei 10.865/2004: 1,65% (PIS) + 7,6% (COFINS) sobre serviços importados.',
                'note' => 'Recolhido pela empresa; no lucro real pode gerar crédito.',
            ],
            [
                'key' => 'irrf',
                'label' => 'IRRF — rendimento no exterior',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['irrf'],
                'enabled' => (bool) $p['irrf_enabled'],
                'source' => 'Receita Federal',
                'legal' => 'Lei 9.779/1999, art. 7º: 15%; 25% para paraíso fiscal.',
                'note' => 'Acordos de bitributação podem reduzir a alíquota.',
            ],
            [
                'key' => 'iss',
                'label' => 'ISS — serviço importado',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['iss'],
                'enabled' => (bool) $p['iss_enabled'],
                'source' => 'Município',
                'legal' => 'LC 116/2003: 2% a 5%, conforme o município do tomador.',
                'note' => 'Ajuste para a alíquota da sua cidade.',
            ],
            [
                'key' => 'cide',
                'label' => 'CIDE — serviços técnicos',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['cide'],
                'enabled' => (bool) $p['cide_enabled'],
                'source' => 'Receita Federal',
                'legal' => 'Lei 10.168/2000: 10% em royalties, serviços técnicos e transferência de tecnologia.',
                'note' => 'Não incide em hospedagem, licenças e serviços comuns.',
            ],
            [
                'key' => 'cbs',
                'label' => 'CBS — fase de teste 2026',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['cbs'],
                'enabled' => (bool) $p['cbs_enabled'],
                'source' => 'Receita Federal',
                'legal' => 'LC 214/2025: 0,9% em 2026, compensável com PIS/COFINS.',
                'note' => 'Ative apenas se a contabilidade já destacar CBS.',
            ],
            [
                'key' => 'ibs',
                'label' => 'IBS — fase de teste 2026',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['ibs'],
                'enabled' => (bool) $p['ibs_enabled'],
                'source' => 'Comitê Gestor do IBS',
                'legal' => 'LC 214/2025: 0,1% em 2026, compensável com ICMS/ISS.',
                'note' => 'Ative apenas se a contabilidade já destacar IBS.',
            ],
            [
                'key' => 'other',
                'label' => 'Outros tributos (manual)',
                'group' => self::GROUP_SERVICE,
                'percent' => $p['outros'],
                'enabled' => (bool) $p['other_taxes_enabled'],
                'source' => 'Manual',
                'legal' => '',
                'note' => 'Use para taxas específicas do seu município ou regime.',
            ],
            [
                'key' => 'icms',
                'label' => 'ICMS — importação de bens',
                'group' => self::GROUP_GOODS,
                'percent' => $p['icms'],
                'enabled' => (bool) $p['icms_enabled'],
                'source' => 'Estado',
                'legal' => 'Convênio 81/2023: 17% a 20%, conforme o estado.',
                'note' => 'Não se aplica a serviços.',
            ],
            [
                'key' => 'import_tax',
                'label' => 'Imposto de Importação — bens',
                'group' => self::GROUP_GOODS,
                'percent' => $p['import_tax'],
                'enabled' => (bool) $p['import_tax_enabled'],
                'source' => 'Receita Federal',
                'legal' => '60% acima de US$ 50, com dedução de US$ 30 no Remessa Conforme.',
                'note' => 'Não se aplica a serviços. Para CNPJ incide desde a primeira faixa.',
                'deduction_usd' => $p['import_tax_deduction_usd'],
            ],
        ];
    }

    /**
     * Aplica a fórmula completa e devolve todos os valores discriminados.
     *
     * base_brl        = valor_original × cotação
     * spread          = base_brl × spread%
     * subtotal_cartao = base_brl + spread + tarifa fixa
     * iof             = subtotal_cartao × iof%
     * total_cartao    = subtotal_cartao + iof
     * base_servico    = base_brl + spread (valor remetido + custo cambial)
     * tributos        = base_servico × alíquota de cada tributo habilitado
     * total_brl       = total_cartao + tributos
     *
     * @param  array<string, float|bool|array<string, bool>|string>  $overrides
     * @return array<string, mixed>
     */
    public function calculate(
        float $amountOriginal,
        float $exchangeRate,
        array $overrides = [],
        ?bool $applyServiceTaxes = null
    ): array {
        $p = array_merge($this->percentages(), $overrides);

        if (array_key_exists('outros', $overrides) && ! array_key_exists('other', $overrides)) {
            $p['other'] = (float) $overrides['outros'];
        }

        $nature = strtolower((string) ($overrides['nature'] ?? 'service')) === 'goods' ? 'goods' : 'service';
        $isService = $nature === 'service';

        $enabled = is_array($overrides['enabled'] ?? null) ? $overrides['enabled'] : [];
        $isEnabled = fn (string $key, bool $default): bool => array_key_exists($key, $enabled)
            ? (bool) $enabled[$key]
            : (bool) ($p[$key.'_enabled'] ?? $default);

        $applyService = $isService && ($applyServiceTaxes ?? (bool) ($p['service_taxes_enabled'] ?? true));

        $rate = round($exchangeRate, 4);
        $base = round($amountOriginal * $rate, 2);
        $spread = round($base * ($this->num($p, 'spread') / 100), 2);
        $cardFee = round($this->num($p, 'card_fixed_fee'), 2);
        $subtotalCard = round($base + $spread + $cardFee, 2);
        $iof = round($subtotalCard * ($this->num($p, 'iof') / 100), 2);
        $cardTotal = round($subtotalCard + $iof, 2);

        // O tributo incide sobre o valor remetido ao exterior + custo cambial,
        // e não sobre a tarifa fixa cobrada pelo banco.
        $serviceBase = round($base + $spread, 2);

        $lines = [];
        $lines[] = $this->line('spread', 'Spread da bandeira/banco', self::GROUP_CARD, $this->num($p, 'spread'), $spread, true, 'Custo do banco/bandeira, não é tributo.');
        $lines[] = $this->line('iof', 'IOF — cartão internacional', self::GROUP_CARD, $this->num($p, 'iof'), $iof, true, 'Decreto 6.306/2007, art. 15-B, VII.');
        $lines[] = $this->line('card_fee', 'Tarifa fixa do banco/cartão', self::GROUP_CARD, null, $cardFee, true, 'Tarifa contratada com o banco.');

        $serviceKeys = [
            'pis_cofins' => ['PIS/COFINS-Importação', 9.25, true],
            'irrf' => ['IRRF — rendimento no exterior', 15.00, true],
            'iss' => ['ISS — serviço importado', 5.00, true],
            'cide' => ['CIDE — serviços técnicos', 10.00, false],
            'cbs' => ['CBS — fase de teste 2026', 0.90, false],
            'ibs' => ['IBS — fase de teste 2026', 0.10, false],
            'other' => ['Outros tributos (manual)', 0.00, false],
        ];

        $serviceTotal = 0.0;

        foreach ($serviceKeys as $key => [$label, $default, $defaultEnabled]) {
            $percent = $this->num($p, $key);
            $active = $applyService && $isEnabled($key, $defaultEnabled) && $percent > 0;
            $amount = $active ? round($serviceBase * ($percent / 100), 2) : 0.0;
            $serviceTotal += $amount;
            $lines[] = $this->line($key, $label, self::GROUP_SERVICE, $percent, $amount, $active);
        }

        $icmsActive = ! $isService && $isEnabled('icms', false) && $this->num($p, 'icms') > 0;
        $icms = $icmsActive ? round($serviceBase * ($this->num($p, 'icms') / 100), 2) : 0.0;
        $lines[] = $this->line('icms', 'ICMS — importação de bens', self::GROUP_GOODS, $this->num($p, 'icms'), $icms, $icmsActive);

        $importTaxActive = ! $isService && $isEnabled('import_tax', false) && $this->num($p, 'import_tax') > 0;
        $deductionUsd = $this->num($p, 'import_tax_deduction_usd');
        $deduction = $importTaxActive && $amountOriginal > 50 ? round($deductionUsd * $rate, 2) : 0.0;
        $importTax = $importTaxActive
            ? max(0.0, round($serviceBase * ($this->num($p, 'import_tax') / 100), 2) - $deduction)
            : 0.0;
        $lines[] = $this->line('import_tax', 'Imposto de Importação — bens', self::GROUP_GOODS, $this->num($p, 'import_tax'), $importTax, $importTaxActive);

        $goodsTotal = round($icms + $importTax, 2);
        $importTaxes = round($serviceTotal + $goodsTotal, 2);
        $total = round($cardTotal + $importTaxes, 2);

        // Compatibilidade com as colunas antigas: ICMS fica na coluna própria
        // e o restante dos tributos em other_taxes_amount.
        $otherTaxes = round($importTaxes - $icms, 2);

        return [
            'amount_original' => round($amountOriginal, 2),
            'exchange_rate' => $rate,
            'base_brl' => $base,
            'spread_percent' => round($this->num($p, 'spread'), 4),
            'spread_amount' => $spread,
            'card_fixed_fee' => $cardFee,
            'base_with_spread_brl' => $subtotalCard,
            'iof_percent' => round($this->num($p, 'iof'), 4),
            'iof_amount' => $iof,
            'card_total_brl' => $cardTotal,
            'pis_cofins_percent' => round($this->num($p, 'pis_cofins'), 4),
            'pis_cofins_amount' => round($this->amountFor($lines, 'pis_cofins'), 2),
            'irrf_percent' => round($this->num($p, 'irrf'), 4),
            'irrf_amount' => round($this->amountFor($lines, 'irrf'), 2),
            'iss_percent' => round($this->num($p, 'iss'), 4),
            'iss_amount' => round($this->amountFor($lines, 'iss'), 2),
            'cide_percent' => round($this->num($p, 'cide'), 4),
            'cide_amount' => round($this->amountFor($lines, 'cide'), 2),
            'cbs_percent' => round($this->num($p, 'cbs'), 4),
            'cbs_amount' => round($this->amountFor($lines, 'cbs'), 2),
            'ibs_percent' => round($this->num($p, 'ibs'), 4),
            'ibs_amount' => round($this->amountFor($lines, 'ibs'), 2),
            'icms_percent' => round($this->num($p, 'icms'), 4),
            'icms_amount' => $icms,
            'import_tax_percent' => round($this->num($p, 'import_tax'), 4),
            'import_tax_amount' => $importTax,
            'import_tax_deduction' => $deduction,
            'other_taxes_percent' => round($this->num($p, 'other'), 4),
            'other_taxes_amount' => $otherTaxes,
            'import_taxes_amount' => $importTaxes,
            'card_charges_total' => round($spread + $iof + $cardFee, 2),
            'taxes_total' => round($spread + $iof + $importTaxes, 2),
            'total_brl' => $total,
            'apply_service_taxes' => $applyService,
            'nature' => $nature,
            'lines' => $lines,
        ];
    }

    /**
     * Aplica o cálculo diretamente na fatura e persiste os valores.
     *
     * @param  array<string, float|bool|array<string, bool>>  $overrides
     * @return array<string, mixed>
     */
    public function applyToInvoice(
        Invoice $invoice,
        float $exchangeRate,
        array $overrides = [],
        ?bool $applyServiceTaxes = null
    ): array {
        $overrides['nature'] = $overrides['nature'] ?? ($invoice->tax_nature ?: 'service');

        $result = $this->calculate((float) $invoice->amount_original, $exchangeRate, $overrides, $applyServiceTaxes);

        $invoice->forceFill([
            'exchange_rate' => $result['exchange_rate'],
            'spread_amount' => $result['spread_amount'],
            'iof_amount' => $result['iof_amount'],
            'icms_amount' => $result['icms_amount'],
            'other_taxes_amount' => $result['other_taxes_amount'],
            'card_total_brl' => $result['card_total_brl'],
            'import_taxes_amount' => $result['import_taxes_amount'],
            'tax_breakdown' => $result['lines'],
            'apply_service_taxes' => $result['apply_service_taxes'],
            'tax_nature' => $result['nature'],
            'total_brl' => $result['total_brl'],
            'calculated_at' => now(),
        ])->save();

        return $result;
    }

    private function rate(string $key): float
    {
        return (float) AppSetting::get($key, config('finance.taxes.'.$key, 0));
    }

    private function enabled(string $key, bool $default): bool
    {
        return filter_var(
            AppSetting::get($key, config('finance.taxes.'.$key, $default ? '1' : '0')),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @param  array<string, float|bool>  $values
     */
    private function num(array $values, string $key): float
    {
        return isset($values[$key]) ? (float) $values[$key] : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function amountFor(array $lines, string $key): float
    {
        foreach ($lines as $line) {
            if ($line['key'] === $key) {
                return (float) $line['amount'];
            }
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        string $key,
        string $label,
        string $group,
        ?float $percent,
        float $amount,
        bool $active,
        string $legal = ''
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'group' => $group,
            'percent' => $percent === null ? null : round($percent, 4),
            'amount' => round($amount, 2),
            'active' => $active,
            'legal' => $legal,
        ];
    }
}
