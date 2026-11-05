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

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_spread_and_taxes_over_the_base_with_spread(): void
    {
        AppSetting::set('spread_percent', '2.00');
        AppSetting::set('iof_percent', '3.38');
        AppSetting::set('icms_percent', '0');
        AppSetting::set('other_taxes_percent', '0');

        $result = app(TaxCalculationService::class)->calculate(100.00, 5.5000, [], false);

        $this->assertSame(550.00, $result['base_brl']);
        $this->assertSame(11.00, $result['spread_amount']);
        $this->assertSame(561.00, $result['base_with_spread_brl']);
        $this->assertSame(18.96, $result['iof_amount']);
        $this->assertSame(0.0, $result['icms_amount']);
        $this->assertSame(579.96, $result['card_total_brl']);
        $this->assertSame(0.0, $result['import_taxes_amount']);
        $this->assertSame(579.96, $result['total_brl']);
    }

    public function test_service_taxes_are_applied_over_the_base_with_spread(): void
    {
        AppSetting::set('spread_percent', '2.00');
        AppSetting::set('iof_percent', '3.50');

        $result = app(TaxCalculationService::class)->calculate(100.00, 5.5000);

        $this->assertSame(550.00, $result['base_brl']);
        $this->assertSame(19.64, $result['iof_amount']);
        $this->assertSame(580.64, $result['card_total_brl']);
        $this->assertSame(51.89, $result['pis_cofins_amount']);
        $this->assertSame(84.15, $result['irrf_amount']);
        $this->assertSame(28.05, $result['iss_amount']);
        $this->assertSame(164.09, $result['import_taxes_amount']);
        $this->assertSame(744.73, $result['total_brl']);
        $this->assertSame('service', $result['nature']);
        $this->assertTrue($result['apply_service_taxes']);
    }

    public function test_goods_nature_swaps_service_taxes_for_icms_and_import_tax(): void
    {
        $result = app(TaxCalculationService::class)->calculate(100.00, 5.0000, [
            'nature' => 'goods',
            'spread' => 0.0,
            'iof' => 0.0,
            'enabled' => ['icms' => true, 'import_tax' => true],
        ]);

        $this->assertSame(500.00, $result['base_brl']);
        $this->assertSame(0.0, $result['pis_cofins_amount']);
        $this->assertSame(0.0, $result['irrf_amount']);
        $this->assertSame(85.00, $result['icms_amount']);
        // 60% de 500 = 300, menos a dedução de US$ 30 × 5,00 = 150.
        $this->assertSame(150.00, $result['import_tax_amount']);
        $this->assertSame(235.00, $result['import_taxes_amount']);
        $this->assertSame(735.00, $result['total_brl']);
        $this->assertSame('goods', $result['nature']);
        $this->assertFalse($result['apply_service_taxes']);
    }

    public function test_rounds_each_step_to_two_decimals(): void
    {
        $result = app(TaxCalculationService::class)->calculate(33.33, 6.1234, [
            'spread' => 2.00,
            'iof' => 3.38,
            'icms' => 0.0,
            'outros' => 0.0,
        ], false);

        // 33.33 * 6.1234 = 204.09 (204.0929...) ; spread 4.08 ; base 208.17 ; IOF 7.04
        $this->assertSame(204.09, $result['base_brl']);
        $this->assertSame(4.08, $result['spread_amount']);
        $this->assertSame(208.17, $result['base_with_spread_brl']);
        $this->assertSame(7.04, $result['iof_amount']);
        $this->assertSame(215.21, $result['total_brl']);
    }

    public function test_overrides_take_precedence_over_database_values(): void
    {
        AppSetting::set('spread_percent', '10.00');

        $result = app(TaxCalculationService::class)->calculate(10.00, 5.00, [
            'spread' => 0.0,
            'iof' => 0.0,
            'icms' => 0.0,
            'outros' => 0.0,
        ], false);

        $this->assertSame(50.00, $result['total_brl']);
    }
}
