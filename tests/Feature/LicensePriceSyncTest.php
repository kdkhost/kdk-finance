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
use App\Models\LicenseCatalogItem;
use App\Models\LicenseService;
use App\Models\User;
use App\Services\LicenseBoyPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicensePriceSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Páginas sobrescritas por teste (categoria => HTML|false para falha).
     *
     * @var array<string, string|false>
     */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'awesomeapi')) {
                return Http::response([
                    'USDBRL' => ['bid' => 5.42, 'pctChange' => 0.12],
                    'EURBRL' => ['bid' => 5.90, 'pctChange' => -0.08],
                ]);
            }

            $path = (string) parse_url($url, PHP_URL_PATH);
            $category = basename($path);
            $page = $this->pages[$category] ?? null;

            if ($page === false) {
                return Http::response('Indisponível', 404);
            }

            return Http::response($page ?? $this->page($category), 200);
        });
    }

    private function user(string $role = User::ROLE_FINANCIAL): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function cards(array $products): string
    {
        $html = '<html><body>';

        foreach ($products as $product) {
            $html .= '<div class="package">';
            $html .= '<div class="package-title">'.e($product['name']).'</div>';
            $html .= '<div class="price">';

            if (! empty($product['from'])) {
                $html .= '<span class="price-starting-from">Starting from</span>';
            }

            $html .= '<span class="price-amount">'.$product['amount'].'</span>';
            $html .= '<span class="price-cycle">'.$product['cycle'].'</span></div>';
            $html .= '<a href="'.$product['href'].'">Order</a>';

            if (array_key_exists('stock', $product) && $product['stock'] !== null) {
                $html .= '<span class="package-stock">'.$product['stock'].' Available</span>';
            }

            $html .= '</div>';
        }

        return $html.'</body></html>';
    }

    private function page(string $category): string
    {
        return $this->cards([[
            'name' => 'Plano '.$category,
            'amount' => '$3.99',
            'cycle' => 'Monthly + $1.00 Setup Fee',
            'href' => '/panel/store/order/'.$category.'-plan',
        ]]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function catalogItem(array $attributes = []): LicenseCatalogItem
    {
        return LicenseCatalogItem::query()->create(array_merge([
            'provider' => LicenseBoyPriceService::PROVIDER,
            'category' => 'control-panel-licenses',
            'category_name' => 'Painéis de controle',
            'name' => 'cPanel - Cloud',
            'slug' => 'cpanel-vps',
            'price' => 3.99,
            'price_is_from' => false,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'setup_fee' => 1.00,
            'stock' => null,
            'url' => 'https://licenseboy.com/panel/store/order/cpanel-vps',
            'synced_at' => now(),
        ], $attributes));
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
            'quantity' => 1,
            'unit_price' => 3.99,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'status' => LicenseService::STATUS_ACTIVE,
            'auto_renew' => true,
        ], $attributes));
    }

    public function test_sync_creates_catalog_and_updates_linked_licenses(): void
    {
        $item = $this->catalogItem();
        $license = $this->license(['license_catalog_item_id' => $item->id]);

        $this->pages['control-panel-licenses'] = $this->cards([[
            'name' => 'cPanel - Cloud',
            'amount' => '$4.49',
            'cycle' => 'Monthly + $2.00 Setup Fee',
            'href' => '/panel/store/order/cpanel-vps',
            'stock' => 5,
        ]]);

        $this->actingAs($this->user())
            ->post(route('licenses.prices.sync'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(8, LicenseCatalogItem::query()->count());
        $this->assertDatabaseHas('app_settings', ['key' => 'license_prices_synced_at']);

        $item->refresh();
        $this->assertEquals(4.49, (float) $item->price);
        $this->assertEquals(2.00, (float) $item->setup_fee);
        $this->assertSame(5, $item->stock);

        $license->refresh();
        $this->assertEquals(4.49, (float) $license->unit_price);
    }

    public function test_sync_removes_items_that_left_a_successfully_read_category(): void
    {
        $stale = $this->catalogItem(['slug' => 'cpanel-antigo', 'name' => 'cPanel Antigo']);

        $this->actingAs($this->user())->post(route('licenses.prices.sync'));

        $this->assertDatabaseMissing('license_catalog_items', ['id' => $stale->id]);
        $this->assertDatabaseHas('license_catalog_items', ['slug' => 'control-panel-licenses-plan']);
    }

    public function test_failed_category_keeps_its_existing_items(): void
    {
        $kept = $this->catalogItem([
            'category' => 'bundles',
            'category_name' => 'Bundles',
            'slug' => 'bundle-antigo',
            'name' => 'Bundle Antigo',
        ]);

        $this->pages['bundles'] = false;

        $this->actingAs($this->user())
            ->post(route('licenses.prices.sync'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('license_catalog_items', ['id' => $kept->id]);
    }

    public function test_viewer_cannot_run_the_sync(): void
    {
        $this->actingAs($this->user(User::ROLE_VIEWER))
            ->post(route('licenses.prices.sync'))
            ->assertForbidden();

        $this->assertSame(0, LicenseCatalogItem::query()->count());
    }

    public function test_prices_page_lists_the_catalog_and_offers_a_prefilled_license(): void
    {
        $item = $this->catalogItem();

        $response = $this->actingAs($this->user())->get(route('licenses.prices'));

        $response->assertOk();
        $response->assertSee('cPanel - Cloud');
        $response->assertSee('$ 3,99');
        $response->assertSee('Painéis de controle');
        $response->assertSee(route('licenses.create', ['catalogo' => $item->id]), false);
    }

    public function test_create_form_prefills_from_the_selected_catalog_item(): void
    {
        $item = $this->catalogItem([
            'name' => 'CloudLinux - 2 Core',
            'slug' => 'cloudlinux-2-core',
            'price' => 6.89,
            'billing_cycle' => LicenseService::CYCLE_ANNUAL,
        ]);

        $response = $this->actingAs($this->user())
            ->get(route('licenses.create', ['catalogo' => $item->id]));

        $response->assertOk();
        $response->assertSee('CloudLinux', false);
        $response->assertSee('value="6.89"', false);
        $response->assertSee('value="'.$item->id.'"', false);
    }

    public function test_catalog_wins_over_manipulated_form_values(): void
    {
        $item = $this->catalogItem();

        $this->actingAs($this->user())
            ->post('/licencas', [
                'license_catalog_item_id' => $item->id,
                'provider' => 'Outro fornecedor',
                'product' => 'Manual',
                'name' => 'cPanel · BR-03',
                'quantity' => '1',
                'unit_price' => '99.99',
                'currency' => 'EUR',
                'billing_cycle' => LicenseService::CYCLE_ANNUAL,
                'status' => LicenseService::STATUS_ACTIVE,
                'auto_renew' => '1',
            ])
            ->assertRedirect();

        $license = LicenseService::query()->where('name', 'cPanel · BR-03')->firstOrFail();

        $this->assertSame($item->id, $license->license_catalog_item_id);
        $this->assertSame('LicenseBoy', $license->provider);
        $this->assertSame('cPanel', $license->product);
        $this->assertEquals(3.99, (float) $license->unit_price);
        $this->assertSame('USD', $license->currency);
        $this->assertSame(LicenseService::CYCLE_MONTHLY, $license->billing_cycle);
    }

    public function test_command_syncs_the_catalog(): void
    {
        $this->artisan('licenses:sync-prices')
            ->expectsOutputToContain('Catálogo: 8 produtos')
            ->assertSuccessful();

        $this->assertSame(8, LicenseCatalogItem::query()->count());
        $this->assertNotNull(AppSetting::get('license_prices_synced_at'));
    }
}
