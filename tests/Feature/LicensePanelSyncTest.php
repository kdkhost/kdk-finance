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
use App\Models\Invoice;
use App\Models\LicenseService;
use App\Models\User;
use App\Services\LicenseBoyPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicensePanelSyncTest extends TestCase
{
    use RefreshDatabase;

    private function configurePanel(): void
    {
        AppSetting::set('licenseboy_panel_email', 'cliente@exemplo.com', 'licenseboy');
        AppSetting::set('licenseboy_panel_password', 'segredo', 'licenseboy', true);
    }

    private function fakePanel(): void
    {
        Http::fake([
            'https://licenseboy.com/panel/login' => Http::sequence()
                ->push('<form method="post"><input type="hidden" name="token" value="abc123"></form>')
                ->push('<html><body>Bem-vindo à clientarea</body></html>'),
            'https://licenseboy.com/panel/clientarea.php?action=services' => Http::response(
                '<table id="tableServicesList"><tbody>'
                .'<tr><td><a href="clientarea.php?action=productdetails&id=77">Shared Control Panel Licenses - cPanel - Metal 51.222.153.58</a></td></tr>'
                .'<tr><td><a href="clientarea.php?action=productdetails&id=55">Addons &amp; Extensions - Jetbackup License 51.222.153.58</a></td></tr>'
                .'</tbody></table>'
            ),
            'https://licenseboy.com/panel/clientarea.php?action=productdetails&id=77' => Http::response(
                '<ul>'
                .'<li><span class="list-info-title">Valor Reincidente</span><span class="list-info-text">$7.99 USD</span></li>'
                .'<li><span class="list-info-title">Ciclo de Pagamento</span><span class="list-info-text">Mensal</span></li>'
                .'<li><span class="list-info-title">Próximo Vencimento</span><span class="list-info-text">Sábado, Novembro 7º, 2026</span></li>'
                .'<li><span class="list-info-title">Status</span><span class="list-info-text">Ativo</span></li>'
                .'</ul>'
            ),
            'https://licenseboy.com/panel/clientarea.php?action=productdetails&id=55' => Http::response(
                '<ul>'
                .'<li><span class="list-info-title">Valor Reincidente</span><span class="list-info-text">$1.80 USD</span></li>'
                .'<li><span class="list-info-title">Ciclo de Pagamento</span><span class="list-info-text">Mensal</span></li>'
                .'<li><span class="list-info-title">Próximo Vencimento</span><span class="list-info-text">Sexta, Novembro 27º, 2026</span></li>'
                .'<li><span class="list-info-title">Status</span><span class="list-info-text">Ativo</span></li>'
                .'</ul>'
            ),
        ]);
    }

    private function license(array $attributes = []): LicenseService
    {
        return LicenseService::query()->create(array_merge([
            'provider' => 'LicenseBoy',
            'product' => 'JetBackup',
            'name' => 'Jetbackup License',
            'server_name' => 'ns576607.ip-51-222-153.net',
            'quantity' => 1,
            'unit_price' => 3.99,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'next_due_date' => '2026-11-27',
            'status' => LicenseService::STATUS_ACTIVE,
            'auto_renew' => true,
        ], $attributes));
    }

    public function test_panel_login_parses_decimal_prices_without_multiplying_by_one_hundred(): void
    {
        $this->configurePanel();
        $this->fakePanel();

        $services = app(LicenseBoyPanelService::class)->services();

        $this->assertCount(2, $services);

        $jetbackup = $services[1];

        $this->assertSame('55', $jetbackup['service_id']);
        $this->assertEqualsWithDelta(1.80, $jetbackup['price'], 0.001);
        $this->assertSame('USD', $jetbackup['currency']);
        $this->assertSame(LicenseService::CYCLE_MONTHLY, $jetbackup['cycle']);
        $this->assertSame('2026-11-27', $jetbackup['next_due']);
        $this->assertSame(LicenseService::STATUS_ACTIVE, $jetbackup['status']);

        $cpanel = $services[0];

        $this->assertEqualsWithDelta(7.99, $cpanel['price'], 0.001);
    }

    public function test_sync_command_stores_the_panel_price_and_uses_it_for_billing(): void
    {
        $this->configurePanel();
        $this->fakePanel();
        $license = $this->license();

        $this->artisan('licenses:sync-panel')->assertSuccessful();

        $license->refresh();

        $this->assertSame('55', $license->panel_service_id);
        $this->assertEqualsWithDelta(1.80, (float) $license->panel_price, 0.001);
        $this->assertTrue($license->usesPanelPrice());
        $this->assertEqualsWithDelta(1.80, $license->billingUnitPrice(), 0.001);
        $this->assertSame('2026-11-27', $license->next_due_date->toDateString());
        $this->assertNotNull($license->panel_synced_at);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_FINANCIAL, 'is_active' => true]))
            ->post(route('licenses.invoice', $license))
            ->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertEqualsWithDelta(1.80, (float) $invoice->amount_original, 0.001);
        $this->assertSame('USD', $invoice->currency);
    }

    public function test_panel_credentials_are_encrypted_and_can_be_cleared(): void
    {
        $developer = User::factory()->create(['role' => User::ROLE_DEVELOPER, 'is_active' => true]);

        $this->actingAs($developer)
            ->put(route('settings.licenseboy'), [
                'licenseboy_panel_email' => 'cliente@exemplo.com',
                'licenseboy_panel_password' => 'segredo-forte',
            ])
            ->assertRedirect(route('settings.index', ['tab' => 'licenseboy']));

        $this->assertSame('cliente@exemplo.com', AppSetting::get('licenseboy_panel_email'));
        $this->assertSame('segredo-forte', AppSetting::get('licenseboy_panel_password'));

        $raw = (string) DB::table('app_settings')->where('key', 'licenseboy_panel_password')->value('value');
        $this->assertNotSame('segredo-forte', $raw);
        $this->assertTrue((bool) DB::table('app_settings')->where('key', 'licenseboy_panel_password')->value('is_secret'));

        $this->actingAs($developer)
            ->put(route('settings.licenseboy'), ['clear_licenseboy_credentials' => '1'])
            ->assertRedirect(route('settings.index', ['tab' => 'licenseboy']));

        $this->assertSame('', AppSetting::get('licenseboy_panel_email', ''));
        $this->assertFalse(app(LicenseBoyPanelService::class)->isConfigured());
    }

    public function test_viewer_cannot_run_the_panel_sync(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER, 'is_active' => true]);

        $this->actingAs($viewer)
            ->post(route('licenses.panel.sync'))
            ->assertForbidden();
    }

    public function test_ambiguous_product_matches_are_ignored_instead_of_using_a_wrong_price(): void
    {
        $this->configurePanel();

        Http::fake([
            'https://licenseboy.com/panel/login' => Http::sequence()
                ->push('<input type="hidden" name="token" value="abc123">')
                ->push('clientarea'),
            'https://licenseboy.com/panel/clientarea.php?action=services' => Http::response(
                '<table id="tableServicesList"><tbody>'
                .'<tr><td><a href="clientarea.php?action=productdetails&id=1">cPanel - Metal 10.0.0.1</a></td></tr>'
                .'<tr><td><a href="clientarea.php?action=productdetails&id=2">cPanel - Metal 10.0.0.2</a></td></tr>'
                .'</tbody></table>'
            ),
            'https://licenseboy.com/panel/clientarea.php?action=productdetails&id=1' => Http::response(
                '<li><span class="list-info-title">Valor Reincidente</span><span class="list-info-text">$7.99 USD</span></li>'
            ),
            'https://licenseboy.com/panel/clientarea.php?action=productdetails&id=2' => Http::response(
                '<li><span class="list-info-title">Valor Reincidente</span><span class="list-info-text">$8.99 USD</span></li>'
            ),
        ]);

        $license = $this->license(['name' => 'cPanel · Servidor sem IP', 'product' => 'cPanel']);

        $result = app(LicenseBoyPanelService::class)->apply(app(LicenseBoyPanelService::class)->services());

        $this->assertSame(0, $result['updated']);
        $this->assertSame(['cPanel · Servidor sem IP'], $result['unmatched']);
        $this->assertNull($license->fresh()->panel_price);
    }
}
