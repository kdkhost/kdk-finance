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

use App\Models\Invoice;
use App\Models\User;
use App\Services\OvhService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoicePaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRates(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.50', 'pctChange' => '0.10'],
                'EURBRL' => ['bid' => '6.10', 'pctChange' => '-0.10'],
            ], 200),
        ]);
    }

    private function manager(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'external_reference' => 'PT-TESTE-1',
            'description' => 'OVHcloud · fatura PT-TESTE-1',
            'currency' => 'EUR',
            'amount_original' => 43.96,
            'exchange_rate' => 6.1000,
            'total_brl' => 280.00,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->toDateString(),
            'rate_locked' => false,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function bill(): array
    {
        return [
            'billId' => 'PT-TESTE-1',
            'date' => now()->toIso8601String(),
            'orderId' => 987654,
            'priceWithTax' => ['value' => 43.96, 'currencyCode' => 'EUR'],
        ];
    }

    public function test_sync_marks_a_current_month_bill_as_paid_when_ovh_confirms_it(): void
    {
        $this->fakeRates();

        $invoice = $this->invoice();

        $cancelled = $this->invoice([
            'external_reference' => 'PT-TESTE-2',
            'status' => Invoice::STATUS_CANCELED,
        ]);

        $this->mock(OvhService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('debtStatuses')->andReturn([]);
            $mock->shouldReceive('getRecentBills')->andReturn([$this->bill()]);
            $mock->shouldReceive('billToInvoiceAttributes')->andReturnUsing(fn (array $bill): array => [
                'external_reference' => (string) $bill['billId'],
                'description' => 'OVHcloud · fatura '.$bill['billId'],
                'currency' => 'EUR',
                'amount_original' => 43.96,
                'due_date' => now()->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ]);
            $mock->shouldReceive('paymentStatusForBill')->andReturn('paid');
            $mock->shouldReceive('getBillItems')->andReturn([]);
            $mock->shouldReceive('getActiveServices')->andReturn([]);
            $mock->shouldReceive('getIps')->andReturn([]);
        });

        $this->artisan('finance:sync-invoices', ['--fresh' => true])->assertExitCode(0);

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertTrue((bool) $invoice->rate_locked);
        $this->assertSame(Invoice::STATUS_CANCELED, $cancelled->fresh()->status);
    }

    public function test_sync_keeps_the_invoice_pending_when_ovh_reports_it_unpaid(): void
    {
        $this->fakeRates();

        $invoice = $this->invoice([
            'due_date' => now()->subMonthNoOverflow()->toDateString(),
        ]);

        $this->mock(OvhService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('debtStatuses')->andReturn([]);
            $mock->shouldReceive('getRecentBills')->andReturn([$this->bill()]);
            $mock->shouldReceive('billToInvoiceAttributes')->andReturnUsing(fn (array $bill): array => [
                'external_reference' => (string) $bill['billId'],
                'description' => 'OVHcloud · fatura '.$bill['billId'],
                'currency' => 'EUR',
                'amount_original' => 43.96,
                'due_date' => now()->subMonthNoOverflow()->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ]);
            $mock->shouldReceive('paymentStatusForBill')->andReturn('pending');
            $mock->shouldReceive('getBillItems')->andReturn([]);
            $mock->shouldReceive('getActiveServices')->andReturn([]);
            $mock->shouldReceive('getIps')->andReturn([]);
        });

        $this->artisan('finance:sync-invoices', ['--fresh' => true])->assertExitCode(0);

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PENDING, $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    public function test_manager_can_check_the_payment_at_ovhcloud_and_lock_the_rate(): void
    {
        $this->fakeRates();

        $invoice = $this->invoice();

        $this->mock(OvhService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('refreshBill')->with('PT-TESTE-1')->andReturn($this->bill());
            $mock->shouldReceive('paymentStatusForBill')->andReturn('paid');
        });

        $this->actingAs($this->manager())
            ->post(route('invoices.sync-payment', $invoice))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertTrue((bool) $invoice->rate_locked);
        $this->assertGreaterThan(0, (float) $invoice->total_brl);
    }

    public function test_check_payment_warns_when_ovhcloud_still_has_the_bill_open(): void
    {
        $invoice = $this->invoice();

        $this->mock(OvhService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('refreshBill')->andReturn($this->bill());
            $mock->shouldReceive('paymentStatusForBill')->andReturn('pending');
        });

        $this->actingAs($this->manager())
            ->post(route('invoices.sync-payment', $invoice))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(Invoice::STATUS_PENDING, $invoice->fresh()->status);
    }

    public function test_viewer_cannot_check_the_payment_at_ovhcloud(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $invoice = $this->invoice();

        $this->actingAs($viewer)
            ->post(route('invoices.sync-payment', $invoice))
            ->assertForbidden();
    }
}
