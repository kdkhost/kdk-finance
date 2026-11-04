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

use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRatePairTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_the_direct_pair_without_an_api_key(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/json/last/EUR-USD' => Http::response([
                'EURUSD' => ['bid' => '1.12378', 'pctChange' => '0.42'],
            ]),
        ]);

        $pair = app(ExchangeRateService::class)->pair('EUR', 'USD');

        $this->assertEquals(1.12378, $pair['rate']);
        $this->assertEquals(0.889854, $pair['inverse']);
        $this->assertEquals(0.42, $pair['pct_change']);
        $this->assertSame('awesomeapi', $pair['source']);
        $this->assertFalse($pair['stale']);
        $this->assertNull($pair['error']);
    }

    public function test_falls_back_to_the_brl_cross_when_the_direct_pair_fails(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/json/last/*' => Http::response([], 503),
            'economia.awesomeapi.com.br/last/*' => Http::response([
                'USDBRL' => ['bid' => '5.50', 'pctChange' => '0'],
                'EURBRL' => ['bid' => '6.05', 'pctChange' => '0'],
            ]),
        ]);

        $pair = app(ExchangeRateService::class)->pair('EUR', 'USD');

        // 6,05 / 5,50 = 1,1
        $this->assertEquals(1.1, $pair['rate']);
        $this->assertSame('cruzamento BRL', $pair['source']);
        $this->assertFalse($pair['stale']);
    }

    public function test_marks_the_brl_cross_as_stale_when_both_endpoints_fail(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([], 500),
        ]);

        $pair = app(ExchangeRateService::class)->pair('EUR', 'USD');

        // 6,00 / 5,50 — cotações padrão do sistema, marcadas como desatualizadas.
        $this->assertEquals(1.090909, $pair['rate']);
        $this->assertSame('cruzamento BRL', $pair['source']);
        $this->assertTrue($pair['stale']);
    }

    public function test_uses_the_configured_pair_as_the_very_last_resort(): void
    {
        config(['finance.exchange.default_rates' => ['USD' => 0, 'EUR' => 0]]);

        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([], 500),
        ]);

        $pair = app(ExchangeRateService::class)->pair('EUR', 'USD');

        $this->assertEquals(1.09, $pair['rate']);
        $this->assertSame('padrão do sistema', $pair['source']);
        $this->assertTrue($pair['stale']);
        $this->assertNotNull($pair['error']);
    }

    public function test_the_pair_is_cached_for_five_minutes(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/json/last/EUR-USD' => Http::response([
                'EURUSD' => ['bid' => '1.12378', 'pctChange' => '0'],
            ]),
        ]);

        $service = app(ExchangeRateService::class);

        $service->pair('EUR', 'USD');
        $service->pair('EUR', 'USD');

        Http::assertSentCount(1);

        $service->pair('EUR', 'USD', fresh: true);

        Http::assertSentCount(2);
    }

    public function test_identical_currencies_return_one_without_an_http_call(): void
    {
        Http::preventStrayRequests();

        $pair = app(ExchangeRateService::class)->pair('USD', 'USD');

        $this->assertEquals(1.0, $pair['rate']);
        $this->assertSame('identidade', $pair['source']);
    }
}
