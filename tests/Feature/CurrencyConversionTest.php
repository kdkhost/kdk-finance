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

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CurrencyConversion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private string $pairRate = '1.100000';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Http::fake([
            'economia.awesomeapi.com.br/last/*' => Http::response([
                'USDBRL' => ['bid' => '5.50', 'pctChange' => '0.12'],
                'EURBRL' => ['bid' => '6.00', 'pctChange' => '-0.08'],
            ]),
            'economia.awesomeapi.com.br/json/last/*' => fn () => Http::response([
                'EURUSD' => ['bid' => $this->pairRate, 'pctChange' => '0.10'],
                'USDEUR' => ['bid' => '0.909091', 'pctChange' => '-0.10'],
            ]),
        ]);
    }

    private function manager(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);
    }

    private function viewer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Servidor OVH de novembro',
            'from_currency' => 'EUR',
            'to_currency' => 'USD',
            'amount_from' => '100.00',
            'market_rate' => '1.10',
            'spread_percent' => '2',
            'iof_percent' => '3.5',
            'fixed_fee' => '1',
            'buffer_percent' => '1',
            'notes' => 'Cartão Bradesco internacional',
        ], $overrides);
    }

    public function test_financial_manager_creates_a_conversion_with_full_breakdown(): void
    {
        $response = $this->actingAs($this->manager())->post('/conversoes', $this->payload());

        $conversion = CurrencyConversion::query()->firstOrFail();

        $response->assertRedirect(route('conversions.show', $conversion));

        $this->assertSame('EUR', $conversion->from_currency);
        $this->assertSame('USD', $conversion->to_currency);
        $this->assertEquals(110.00, (float) $conversion->base_to);
        $this->assertEquals(2.20, (float) $conversion->spread_amount);
        $this->assertEquals(3.93, (float) $conversion->iof_amount);
        $this->assertEquals(1.00, (float) $conversion->fixed_fee);
        $this->assertEquals(1.17, (float) $conversion->buffer_amount);
        $this->assertEquals(8.30, (float) $conversion->fees_total);
        $this->assertEquals(118.30, (float) $conversion->total_to);
        $this->assertEquals(1.183, (float) $conversion->effective_rate);
        $this->assertEquals(650.65, (float) $conversion->total_brl);
        $this->assertSame('informada manualmente', $conversion->rate_source);
        $this->assertNotNull($conversion->created_by);
    }

    public function test_server_ignores_tampered_totals_sent_by_the_form(): void
    {
        $this->actingAs($this->manager())->post('/conversoes', $this->payload([
            'total_to' => '1.00',
            'base_to' => '1.00',
            'effective_rate' => '999',
        ]))->assertRedirect();

        $conversion = CurrencyConversion::query()->firstOrFail();

        $this->assertEquals(118.30, (float) $conversion->total_to);
        $this->assertEquals(110.00, (float) $conversion->base_to);
    }

    public function test_viewer_cannot_create_a_conversion(): void
    {
        $this->actingAs($this->viewer())
            ->post('/conversoes', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('currency_conversions', 0);
    }

    public function test_index_is_available_for_viewers(): void
    {
        CurrencyConversion::query()->create($this->payload() + [
            'base_to' => 110.00,
            'total_to' => 118.30,
            'total_brl' => 650.65,
            'effective_rate' => 1.183,
        ]);

        $this->actingAs($this->viewer())
            ->get('/conversoes')
            ->assertOk()
            ->assertSee('Servidor OVH de novembro')
            ->assertDontSee('Nova conversão');
    }

    public function test_calibrating_with_the_card_statement_stores_the_real_cost(): void
    {
        $this->actingAs($this->manager())->post('/conversoes', $this->payload());
        $conversion = CurrencyConversion::query()->firstOrFail();

        $this->actingAs($this->manager())
            ->post(route('conversions.calibrate', $conversion), ['statement_amount' => '120.00'])
            ->assertRedirect();

        $conversion->refresh();

        $this->assertEquals(120.00, (float) $conversion->statement_amount);
        $this->assertEquals(9.091, (float) $conversion->statement_cost_percent);
        $this->assertEquals(1.70, $conversion->statementDifference());
        $this->assertNotNull($conversion->statement_checked_at);
    }

    public function test_recalculate_uses_the_current_market_rate(): void
    {
        $this->actingAs($this->manager())->post('/conversoes', $this->payload());
        $conversion = CurrencyConversion::query()->firstOrFail();

        $this->pairRate = '1.200000';

        $this->actingAs($this->manager())
            ->post(route('conversions.recalculate', $conversion))
            ->assertRedirect();

        $conversion->refresh();

        $this->assertEquals(1.200000, (float) $conversion->market_rate);
        $this->assertSame('awesomeapi', $conversion->rate_source);
        // 100 × 1,20 = 120,00 ; spread 2,40 ; IOF 3,5% sobre 122,40 = 4,28 ; +1,00 ; margem 1% = 1,28
        $this->assertEquals(128.96, (float) $conversion->total_to);
    }

    public function test_quote_endpoint_returns_the_direct_pair(): void
    {
        $response = $this->actingAs($this->manager())
            ->getJson('/conversoes/cotacao?from=EUR&to=USD')
            ->assertOk();

        $this->assertEquals(1.1, (float) $response->json('rate'));
        $this->assertEquals(0.909091, (float) $response->json('inverse'));
        $this->assertSame('awesomeapi', $response->json('source'));
        $this->assertEquals(5.5, (float) $response->json('to_brl_rate'));
    }

    public function test_quote_endpoint_is_forbidden_for_viewers(): void
    {
        $this->actingAs($this->viewer())
            ->getJson('/conversoes/cotacao?from=EUR&to=USD')
            ->assertForbidden();
    }

    public function test_settings_fees_become_defaults_for_new_conversions(): void
    {
        $this->actingAs($this->manager())->put('/configuracoes/conversao', [
            'conversion_spread_percent' => '1.5',
            'conversion_iof_percent' => '0',
            'conversion_fixed_fee' => '0',
            'conversion_buffer_percent' => '0.5',
        ])->assertRedirect(route('settings.index', ['tab' => 'conversion']));

        $this->assertSame('1.500', AppSetting::get('conversion_spread_percent'));

        $this->actingAs($this->manager())->post('/conversoes', $this->payload([
            'spread_percent' => null,
            'iof_percent' => null,
            'fixed_fee' => null,
            'buffer_percent' => null,
        ]))->assertRedirect();

        // 100 × 1,10 = 110,00 ; spread 1,5% = 1,65 ; subtotal 111,65 ; margem 0,5% = 0,56
        $this->assertEquals(112.21, (float) CurrencyConversion::query()->firstOrFail()->total_to);
    }
}
