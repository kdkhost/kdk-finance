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
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRates(float $usd = 5.50, float $eur = 6.10): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => (string) $usd, 'pctChange' => '0.25'],
                'EURBRL' => ['bid' => (string) $eur, 'pctChange' => '-0.10'],
            ], 200),
        ]);
    }

    public function test_recalculates_pending_unlocked_invoices_with_current_rates(): void
    {
        $this->fakeRates();

        $invoice = Invoice::query()->create([
            'external_reference' => 'SYNC-1',
            'description' => 'VPS OVHcloud',
            'currency' => 'EUR',
            'amount_original' => 100.00,
            'exchange_rate' => 5.0000,
            'total_brl' => 0,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(4),
            'rate_locked' => false,
        ]);

        $locked = Invoice::query()->create([
            'external_reference' => 'SYNC-2',
            'description' => 'Fatura paga',
            'currency' => 'EUR',
            'amount_original' => 50.00,
            'exchange_rate' => 5.0000,
            'total_brl' => 250.00,
            'status' => Invoice::STATUS_PAID,
            'due_date' => now()->subDays(4),
            'rate_locked' => true,
        ]);

        $this->artisan('finance:sync-invoices', ['--fresh' => true])->assertExitCode(0);

        $invoice->refresh();

        $this->assertEquals(6.10, (float) $invoice->exchange_rate);
        $this->assertEquals(12.20, (float) $invoice->spread_amount);
        $this->assertEquals(21.03, (float) $invoice->iof_amount);
        $this->assertEquals(643.23, (float) $invoice->total_brl);
        $this->assertNotNull($invoice->calculated_at);

        $this->assertEquals(250.00, (float) $locked->fresh()->total_brl);
        $this->assertEquals(6.10, (float) AppSetting::get('last_rate_EUR'));
    }

    public function test_falls_back_to_last_known_rate_when_api_is_down(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response('indisponível', 503),
        ]);

        $this->artisan('finance:sync-invoices', ['--fresh' => true])
            ->expectsOutputToContain('última cotação conhecida')
            ->assertExitCode(0);
    }
}
