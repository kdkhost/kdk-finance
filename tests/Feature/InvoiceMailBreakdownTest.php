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

use App\Mail\ThemedMail;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\LicenseService;
use App\Services\InvoiceMailDataService;
use App\Services\MailTemplateService;
use App\Services\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceMailBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(array $overrides = []): Invoice
    {
        $invoice = Invoice::query()->create(array_merge([
            'external_reference' => 'PT-TESTE-1',
            'description' => 'OVHcloud · servidor',
            'currency' => 'EUR',
            'amount_original' => 100.00,
            'due_date' => now()->addDays(2),
            'status' => Invoice::STATUS_PENDING,
            'rate_locked' => false,
        ], $overrides));

        app(TaxCalculationService::class)->applyToInvoice($invoice, 5.5000, [
            'spread' => 2.0,
            'iof' => 3.5,
            'icms' => 0.0,
            'outros' => 0.0,
        ]);

        return $invoice->fresh();
    }

    public function test_variables_discriminate_conversion_and_every_tax(): void
    {
        $invoice = $this->invoice();
        $variables = app(InvoiceMailDataService::class)->variables($invoice, ['dias' => '2']);

        $this->assertSame('OVHcloud', $variables['fornecedor']);
        $this->assertSame('Euro (EUR)', $variables['moeda']);
        $this->assertSame('€ 100,00', $variables['valor_original']);
        $this->assertSame('R$ 5,5000', $variables['cotacao']);
        $this->assertSame('R$ 550,00', $variables['valor_base']);
        $this->assertSame('Serviço importado', $variables['natureza']);
        $this->assertSame('2,00% · R$ 11,00', $variables['spread']);
        $this->assertSame('3,50% · R$ 19,64', $variables['iof']);
        $this->assertSame('R$ 580,64', $variables['total_cartao']);
        $this->assertSame('9,25% · R$ 51,89', $variables['pis_cofins']);
        $this->assertSame('15,00% · R$ 84,15', $variables['irrf']);
        $this->assertSame('5,00% · R$ 28,05', $variables['iss']);
        $this->assertSame('R$ 164,09', $variables['tributos_importacao']);
        $this->assertStringContainsString('PIS/COFINS-Importação 9,25% · R$ 51,89', $variables['tributos_importacao_detalhe']);
        $this->assertStringContainsString('IRRF — rendimento no exterior 15,00% · R$ 84,15', $variables['tributos_importacao_detalhe']);
        $this->assertSame('R$ 744,73', $variables['total_brl']);
        $this->assertSame('R$ 744,73', $variables['valor']);
        $this->assertSame(route('invoices.show', $invoice), $variables['link_painel']);
        $this->assertSame($variables['link_painel'], $variables['link_oficial']);
    }

    public function test_official_button_uses_the_bill_link_stored_on_the_invoice(): void
    {
        $url = 'https://www.ovh.pt/cgi-bin/order/bill.cgi?reference=PT-TESTE-1&esign=abc';
        $invoice = $this->invoice(['external_url' => $url]);

        $payload = app(MailTemplateService::class)->payload('invoice_due', app(InvoiceMailDataService::class)->variables($invoice, [
            'dias' => '2',
        ]));

        $this->assertSame($url, $payload['action_url']);
        $this->assertSame('Pagar fatura', $payload['action_text']);
        $this->assertArrayHasKey('Total no cartão', $payload['details']);
        $this->assertArrayHasKey('Tributos da importação', $payload['details']);
        $this->assertSame('R$ 580,64', $payload['details']['Total no cartão']);
        $this->assertSame('R$ 164,09', $payload['details']['Tributos da importação']);
        $this->assertSame('R$ 744,73', $payload['details']['Total a reservar']);
        $this->assertStringContainsString('R$ 744,73', $payload['subject']);
    }

    public function test_licenseboy_invoice_points_to_the_official_payment_area(): void
    {
        $license = LicenseService::query()->create([
            'provider' => 'LicenseBoy',
            'product' => 'cPanel',
            'name' => 'Licença cPanel',
            'server_name' => 'srv01',
            'quantity' => 1,
            'unit_price' => 20,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'status' => LicenseService::STATUS_ACTIVE,
        ]);

        $invoice = $this->invoice([
            'external_reference' => 'LIC-1-20261105000000',
            'description' => 'Licença cPanel',
            'license_service_id' => $license->id,
            'currency' => 'USD',
        ]);

        $variables = app(InvoiceMailDataService::class)->variables($invoice);

        $this->assertSame('LicenseBoy', $variables['fornecedor']);
        $this->assertSame('Dólar americano (USD)', $variables['moeda']);
        $this->assertSame('US$ 100,00', $variables['valor_original']);
        $this->assertSame(InvoiceMailDataService::LICENSEBOY_INVOICES_URL, $variables['link_oficial']);
    }

    public function test_daily_alert_email_carries_the_breakdown_and_the_official_button(): void
    {
        AppSetting::set('mail_enabled', '1', 'mail');
        AppSetting::set('mail_host', 'smtp.exemplo.com', 'mail');
        AppSetting::set('mail_from_address', 'alertas@exemplo.com', 'mail');
        AppSetting::set('mail_alert_recipients', 'financeiro@exemplo.com', 'mail');

        Mail::fake();

        $url = 'https://www.ovh.pt/cgi-bin/order/bill.cgi?reference=PT-TESTE-1&esign=abc';
        $this->invoice(['external_url' => $url]);

        $this->artisan('finance:notify-alerts')->assertSuccessful();

        Mail::assertSent(ThemedMail::class, function (ThemedMail $mail) use ($url): bool {
            return $mail->actionUrl === $url
                && ($mail->details['IOF'] ?? null) === '3,50% · R$ 19,64'
                && ($mail->details['Total no cartão'] ?? null) === 'R$ 580,64'
                && ($mail->details['Tributos da importação'] ?? null) === 'R$ 164,09'
                && ($mail->details['Total a reservar'] ?? null) === 'R$ 744,73'
                && str_contains((string) $mail->subject, 'R$ 744,73');
        });
    }
}
