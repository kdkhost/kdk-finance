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
use App\Models\CurrencyConversion;
use App\Models\FeeSyncRun;
use App\Models\User;
use App\Services\Fees\FeeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function fakeSources(): void
    {
        Http::fake([
            'https://www.visa.com.br/*' => Http::response([
                'originalValues' => [
                    'fromCurrency' => 'USD',
                    'fxRateVisa' => 5.4200,
                    'benchmarks' => [
                        ['markupWithoutAdditionalFee' => 0.003121],
                    ],
                ],
            ]),
            '*CotacaoDolarDia*' => Http::response([
                'value' => [[
                    'cotacaoCompra' => 5.3900,
                    'cotacaoVenda' => 5.4000,
                    'dataHoraCotacao' => '2026-11-04 13:10:00.000',
                ]],
            ]),
            '*CotacaoMoedaDia*' => Http::response([
                'value' => [
                    ['tipoBoletim' => 'Abertura', 'cotacaoVenda' => 6.3000, 'dataHoraCotacao' => '2026-11-04 10:00:00.000'],
                    ['tipoBoletim' => 'Fechamento', 'cotacaoVenda' => 6.3200, 'dataHoraCotacao' => '2026-11-04 13:10:00.000'],
                ],
            ]),
            'https://www.mastercard.us/*' => Http::response(['data' => ['conversionRate' => 5.4500]]),
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.4200', 'pctChange' => '0.10'],
            ]),
            '*' => Http::response([], 404),
        ]);
    }

    public function test_visa_markup_and_bcb_ptax_are_saved_from_public_endpoints(): void
    {
        $this->fakeSources();

        $fees = app(FeeSyncService::class);
        $fees->run('visa');
        $fees->run('bcb');

        $this->assertEqualsWithDelta(0.3121, (float) AppSetting::get('card_brand_visa_markup_percent'), 0.0001);
        $this->assertEqualsWithDelta(5.42, (float) AppSetting::get('card_brand_visa_rate_usd'), 0.0001);
        $this->assertEqualsWithDelta(5.40, (float) AppSetting::get('bcb_ptax_usd'), 0.0001);
        $this->assertEqualsWithDelta(6.32, (float) AppSetting::get('bcb_ptax_eur'), 0.0001);
        $this->assertSame('2026-11-04', AppSetting::get('bcb_ptax_usd_date'));

        $this->assertDatabaseHas('fee_sync_runs', [
            'source' => FeeSyncService::SOURCE_VISA,
            'status' => FeeSyncRun::STATUS_OK,
        ]);
        $this->assertDatabaseHas('fee_sync_runs', [
            'source' => FeeSyncService::SOURCE_BCB,
            'status' => FeeSyncRun::STATUS_OK,
        ]);
    }

    public function test_mastercard_block_is_recorded_without_breaking_the_command(): void
    {
        Http::fake([
            'https://www.mastercard.us/*' => Http::response('Access Denied', 403),
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.4200'],
            ]),
            '*' => Http::response([], 404),
        ]);

        $this->artisan('finance:sync-fees', ['--source' => 'mastercard'])->assertSuccessful();

        $run = FeeSyncRun::query()->where('source', FeeSyncService::SOURCE_MASTERCARD)->latest('id')->firstOrFail();

        $this->assertSame(FeeSyncRun::STATUS_UNAVAILABLE, $run->status);
        $this->assertStringContainsString('403', (string) $run->note);
    }

    public function test_paypal_spread_is_the_median_of_recorded_conversions(): void
    {
        foreach ([5.05, 5.06, 5.10] as $provider) {
            CurrencyConversion::query()->create([
                'description' => 'Recarga PayPal',
                'from_currency' => 'USD',
                'to_currency' => 'BRL',
                'amount_from' => 100,
                'market_rate' => 5.00,
                'provider_rate' => $provider,
                'base_to' => 500,
                'total_to' => 500,
                'effective_rate' => $provider,
            ]);
        }

        $observed = app(FeeSyncService::class)->paypalObserved();

        $this->assertSame(3, $observed['samples']);
        $this->assertEqualsWithDelta(1.20, (float) $observed['spread'], 0.0001);

        app(FeeSyncService::class)->run('paypal');

        $this->assertEqualsWithDelta(1.20, (float) AppSetting::get('paypal_observed_spread_percent'), 0.0001);
        $this->assertSame('3', (string) AppSetting::get('paypal_observed_samples'));
    }

    public function test_automatic_spread_only_applies_after_opt_in(): void
    {
        AppSetting::set('card_brand_visa_markup_percent', '0.3121', 'fees');
        AppSetting::set('card_spread_auto', '0', 'taxes');
        AppSetting::set('spread_percent', '2.0000', 'taxes');

        $fees = app(FeeSyncService::class);

        $this->assertFalse($fees->applyAutomaticSpread()['applied']);
        $this->assertEqualsWithDelta(2.00, (float) AppSetting::get('spread_percent'), 0.0001);

        AppSetting::set('card_spread_auto', '1', 'taxes');
        AppSetting::set('card_spread_source', 'visa', 'fees');

        $applied = $fees->applyAutomaticSpread();

        $this->assertTrue($applied['applied']);
        $this->assertEqualsWithDelta(0.3121, (float) AppSetting::get('spread_percent'), 0.0001);
        $this->assertSame('visa', $applied['source']);
    }

    public function test_developer_can_trigger_the_fee_sync_from_the_panel(): void
    {
        $this->fakeSources();

        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $this->actingAs($developer)
            ->post(route('settings.fees.sync'))
            ->assertRedirect(route('settings.index', ['tab' => 'taxes']));

        $this->assertGreaterThanOrEqual(3, FeeSyncRun::query()->count());
    }

    public function test_financial_manager_cannot_trigger_the_fee_sync(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('settings.fees.sync'))
            ->assertForbidden();

        $this->assertDatabaseCount('fee_sync_runs', 0);
    }

    public function test_taxes_tab_saves_the_service_and_goods_rates(): void
    {
        $developer = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $this->actingAs($developer)->put(route('settings.taxes'), [
            'spread_percent' => '2.0000',
            'iof_percent' => '3.5000',
            'card_fixed_fee' => '1.50',
            'pis_cofins_percent' => '9.2500',
            'irrf_percent' => '15.0000',
            'iss_percent' => '5.0000',
            'cide_percent' => '10.0000',
            'cbs_percent' => '0.9000',
            'ibs_percent' => '0.1000',
            'other_taxes_percent' => '0.0000',
            'icms_percent' => '17.0000',
            'import_tax_percent' => '60.0000',
            'import_tax_deduction_usd' => '30.00',
            'card_spread_source' => 'manual',
            'service_taxes_enabled' => '1',
            'pis_cofins_enabled' => '1',
            'irrf_enabled' => '1',
            'iss_enabled' => '1',
        ])->assertRedirect(route('settings.index', ['tab' => 'taxes']));

        $this->assertSame('1.50', AppSetting::get('card_fixed_fee'));
        $this->assertSame('17.0000', AppSetting::get('icms_percent'));
        $this->assertSame('1', AppSetting::get('service_taxes_enabled'));
        $this->assertSame('0', AppSetting::get('cide_enabled'));
        $this->assertSame('0', AppSetting::get('icms_enabled'));
        $this->assertSame('manual', AppSetting::get('card_spread_source'));
    }
}
