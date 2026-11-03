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

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
            'external_reference' => 'TEST-'.uniqid(),
            'description' => 'Fatura OVHcloud',
            'currency' => 'EUR',
            'amount_original' => 100.00,
            'exchange_rate' => 5.5000,
            'total_brl' => 0,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(5),
            'rate_locked' => false,
        ], $attributes));
    }

    public function test_financial_manager_can_create_an_invoice_with_full_breakdown(): void
    {
        $response = $this->actingAs($this->manager())->post('/faturas', [
            'description' => 'Servidor Advance',
            'external_reference' => 'OVH-9001',
            'currency' => 'EUR',
            'amount_original' => '100.00',
            'exchange_rate' => '5.5000',
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => Invoice::STATUS_PENDING,
        ]);

        $invoice = Invoice::query()->where('external_reference', 'OVH-9001')->firstOrFail();

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertEquals(579.96, (float) $invoice->total_brl);
        $this->assertEquals(11.00, (float) $invoice->spread_amount);
        $this->assertEquals(18.96, (float) $invoice->iof_amount);
        $this->assertNotNull($invoice->calculated_at);
    }

    public function test_viewer_cannot_create_invoices(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)->post('/faturas', [
            'description' => 'Bloqueada',
            'currency' => 'EUR',
            'amount_original' => '10.00',
            'due_date' => now()->addDay()->format('Y-m-d'),
        ])->assertForbidden();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_marking_as_paid_locks_the_exchange_rate(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->manager())
            ->post(route('invoices.pay', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertTrue($invoice->rate_locked);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_locked_invoice_is_not_recalculated(): void
    {
        $invoice = $this->invoice(['rate_locked' => true, 'total_brl' => 579.96]);

        $this->actingAs($this->manager())
            ->post(route('invoices.recalculate', $invoice))
            ->assertSessionHas('warning');

        $this->assertEquals(579.96, (float) $invoice->fresh()->total_brl);
    }

    public function test_export_streams_a_csv_with_portuguese_headers(): void
    {
        $this->invoice();

        $response = $this->actingAs($this->manager())->get(route('invoices.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Descrição', $content);
        $this->assertStringContainsString('Cotação', $content);
    }
}
