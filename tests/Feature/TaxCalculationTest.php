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

        $result = app(TaxCalculationService::class)->calculate(100.00, 5.5000);

        $this->assertSame(550.00, $result['base_brl']);
        $this->assertSame(11.00, $result['spread_amount']);
        $this->assertSame(561.00, $result['base_with_spread_brl']);
        $this->assertSame(18.96, $result['iof_amount']);
        $this->assertSame(0.0, $result['icms_amount']);
        $this->assertSame(579.96, $result['total_brl']);
    }

    public function test_rounds_each_step_to_two_decimals(): void
    {
        $result = app(TaxCalculationService::class)->calculate(33.33, 6.1234, [
            'spread' => 2.00,
            'iof' => 3.38,
            'icms' => 0.0,
            'outros' => 0.0,
        ]);

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
        ]);

        $this->assertSame(50.00, $result['total_brl']);
    }
}
