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
use App\Models\LicenseService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function license(array $attributes = []): LicenseService
    {
        return LicenseService::query()->create(array_merge([
            'provider' => 'LicenseBoy',
            'product' => 'cPanel',
            'name' => 'cPanel · BR-01',
            'server_name' => 'br-01.kdkhost.com.br',
            'license_key' => 'CPANEL-AAAA-BBBB-1234',
            'quantity' => 1,
            'unit_price' => 24.90,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'next_due_date' => '2026-12-01',
            'status' => LicenseService::STATUS_ACTIVE,
            'auto_renew' => true,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'LicenseBoy',
            'product' => 'cPanel',
            'name' => 'cPanel · BR-02',
            'server_name' => 'br-02.kdkhost.com.br',
            'license_key' => 'CPANEL-CCCC-DDDD-5678',
            'quantity' => '1',
            'unit_price' => '24.90',
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'next_due_date' => '2026-12-10',
            'status' => LicenseService::STATUS_ACTIVE,
            'auto_renew' => '1',
        ], $overrides);
    }

    public function test_financial_manager_can_register_a_license_with_encrypted_key(): void
    {
        $response = $this->actingAs($this->user())->post('/licencas', $this->payload());

        $license = LicenseService::query()->where('name', 'cPanel · BR-02')->firstOrFail();

        $response->assertRedirect(route('licenses.show', $license));
        $this->assertSame('cPanel', $license->product);
        $this->assertTrue($license->auto_renew);
        $this->assertSame('CPANEL-CCCC-DDDD-5678', $license->license_key);

        $raw = (string) DB::table('license_services')->where('id', $license->id)->value('license_key');
        $this->assertNotSame('CPANEL-CCCC-DDDD-5678', $raw);
        $this->assertSame('••••••••5678', $license->maskedKey());
    }

    public function test_viewer_cannot_write_or_see_license_forms(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);

        $this->actingAs($viewer)->get('/licencas/nova')->assertForbidden();
        $this->actingAs($viewer)->post('/licencas', $this->payload())->assertForbidden();

        $this->assertDatabaseCount('license_services', 0);
    }

    public function test_index_lists_individual_license_values_for_every_role(): void
    {
        $this->license();

        $response = $this->actingAs($this->user(User::ROLE_VIEWER))->get('/licencas');

        $response->assertOk();
        $response->assertSee('LicenseBoy');
        $response->assertSee('cPanel');
        $response->assertSee('US$ 24,90');
        $response->assertSee('R$ 134,96'); // 24,90 × 5,42
    }

    public function test_detail_shows_the_masked_key_but_never_the_plain_key(): void
    {
        $license = $this->license();

        $response = $this->actingAs($this->user(User::ROLE_VIEWER))
            ->get(route('licenses.show', $license));

        $response->assertOk();
        $response->assertSee('••••••••1234');
        $response->assertDontSee('CPANEL-AAAA-BBBB-1234');
    }

    public function test_update_without_a_new_key_preserves_the_current_key(): void
    {
        $license = $this->license();

        $this->actingAs($this->user())
            ->put(route('licenses.update', $license), $this->payload([
                'product' => 'CloudLinux',
                'license_key' => '',
                'unit_price' => '19.00',
            ]))
            ->assertRedirect(route('licenses.show', $license));

        $license->refresh();

        $this->assertSame('CloudLinux', $license->product);
        $this->assertEquals(19.00, (float) $license->unit_price);
        $this->assertSame('CPANEL-AAAA-BBBB-1234', $license->license_key);
    }

    public function test_generating_an_invoice_applies_taxes_links_and_advances_the_cycle(): void
    {
        $license = $this->license([
            'unit_price' => 12.50,
            'quantity' => 2,
            'next_due_date' => '2026-12-01',
        ]);

        $response = $this->actingAs($this->user())
            ->post(route('licenses.invoice', $license));

        $invoice = Invoice::query()->firstOrFail();

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame('USD', $invoice->currency);
        $this->assertEquals(25.00, (float) $invoice->amount_original);
        $this->assertEquals(5.42, (float) $invoice->exchange_rate);
        $this->assertEquals(142.88, (float) $invoice->total_brl);
        $this->assertSame($license->id, $invoice->license_service_id);
        $this->assertStringStartsWith('LIC-'.$license->id.'-', $invoice->external_reference);

        $license->refresh();

        $this->assertSame($invoice->id, $license->last_invoice_id);
        $this->assertSame('2027-01-01', $license->next_due_date->toDateString());
        $this->assertSame(1, $license->invoices()->count());
    }

    public function test_lifetime_license_does_not_advance_the_due_date(): void
    {
        $license = $this->license(['billing_cycle' => LicenseService::CYCLE_LIFETIME]);

        $this->actingAs($this->user())
            ->post(route('licenses.invoice', $license))
            ->assertRedirect();

        $this->assertSame('2026-12-01', $license->fresh()->next_due_date->toDateString());
    }

    public function test_inactive_license_cannot_generate_an_invoice(): void
    {
        $license = $this->license(['status' => LicenseService::STATUS_EXPIRED]);

        $this->actingAs($this->user())
            ->post(route('licenses.invoice', $license))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_deleting_a_license_preserves_its_invoices(): void
    {
        $license = $this->license();

        $this->actingAs($this->user())->post(route('licenses.invoice', $license));

        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->user())
            ->delete(route('licenses.destroy', $license))
            ->assertRedirect(route('licenses.index'));

        $this->assertDatabaseMissing('license_services', ['id' => $license->id]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'license_service_id' => null,
        ]);
    }
}
