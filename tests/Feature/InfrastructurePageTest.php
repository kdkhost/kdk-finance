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
use App\Models\InvoiceItem;
use App\Models\OvhIp;
use App\Models\OvhServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfrastructurePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response([
                'USDBRL' => ['bid' => 5.42, 'pctChange' => 0.12],
                'EURBRL' => ['bid' => 5.90, 'pctChange' => -0.08],
            ]),
        ]);
    }

    private function user(string $role = User::ROLE_FINANCIAL): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function service(array $attributes = []): OvhServiceItem
    {
        return OvhServiceItem::query()->create(array_merge([
            'service_id' => 'ns576607.ip-51-222-153.net',
            'name' => 'ns576607.ip-51-222-153.net',
            'display_name' => 'Servidor Advance',
            'type' => 'dedicated',
            'state' => 'ok',
            'quantity' => 1,
            'monthly_price_eur' => 37.99,
            'last_bill_id' => 'PT820366',
        ], $attributes));
    }

    private function ip(array $attributes = []): OvhIp
    {
        return OvhIp::query()->create(array_merge([
            'block' => '15.235.57.212/32',
            'version' => 'v4',
            'type' => 'failover',
            'country' => 'ca',
            'routed_to' => 'ns576607.ip-51-222-153.net',
            'reverse_dns' => ['212.57.235.15.in-addr.arpa' => 'ns576607.ip-51-222-153.net'],
        ], $attributes));
    }

    public function test_guests_are_redirected_from_infrastructure(): void
    {
        $this->get('/servicos')->assertRedirect('/login');
    }

    public function test_services_page_lists_each_service_with_individual_values(): void
    {
        $this->service();
        $this->service([
            'service_id' => 'ip-15.235.58.232',
            'name' => 'ip-15.235.58.232',
            'display_name' => null,
            'type' => 'ip',
            'monthly_price_eur' => 1.99,
            'last_bill_id' => 'PT820366',
        ]);
        $this->ip();

        $response = $this->actingAs($this->user(User::ROLE_VIEWER))->get('/servicos');

        $response->assertOk();
        $response->assertSee('Servidor Advance');
        $response->assertSee('.ip-51-222-153.net');
        $response->assertSee('€ 37,99');
        $response->assertSee('€ 1,99');
        $response->assertSee('R$ 235,88'); // 39,98 EUR × 5,90
        $response->assertSee('15.235.57.212/32');
        $response->assertSee('Failover');
    }

    public function test_dashboard_shows_services_and_ips(): void
    {
        $this->service();
        $this->ip();

        $response = $this->actingAs($this->user())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Serviços contratados e valores individuais');
        $response->assertSee('Servidor Advance');
        $response->assertSee('R$ 224,14'); // 37,99 × 5,90
        $response->assertSee('15.235.57.212/32');
    }

    public function test_invoice_detail_renders_individual_line_items(): void
    {
        $invoice = Invoice::query()->create([
            'external_reference' => 'PT820366',
            'description' => 'Fatura OVHcloud',
            'currency' => 'EUR',
            'amount_original' => 43.96,
            'exchange_rate' => 5.9040,
            'total_brl' => 259.54,
            'status' => Invoice::STATUS_PAID,
            'due_date' => now()->subMonth(),
            'rate_locked' => true,
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'external_id' => 'item-1',
            'description' => 'Serveur Advance',
            'domain' => 'ns576607.ip-51-222-153.net',
            'quantity' => 1,
            'unit_price' => 37.99,
            'total_price' => 37.99,
            'currency' => 'EUR',
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'external_id' => 'item-2',
            'description' => 'Adresse IPv4 supplémentaire',
            'domain' => 'ip-15.235.58.232',
            'quantity' => 3,
            'unit_price' => 1.99,
            'total_price' => 5.97,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($this->user())->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Itens da fatura');
        $response->assertSee('Serveur Advance');
        $response->assertSee('Adresse IPv4 supplémentaire');
        $response->assertSee('€ 37,99');
        $response->assertSee('€ 5,97');
        $response->assertSee('€ 43,96');
    }

    public function test_invoice_without_items_hides_the_section(): void
    {
        $invoice = Invoice::query()->create([
            'external_reference' => 'MANUAL-1',
            'description' => 'Fatura manual',
            'currency' => 'EUR',
            'amount_original' => 10.00,
            'exchange_rate' => 5.9040,
            'total_brl' => 59.04,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(3),
        ]);

        $this->actingAs($this->user())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Itens da fatura');
    }
}
