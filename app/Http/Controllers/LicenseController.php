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

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\LicenseCatalogItem;
use App\Models\LicenseService;
use App\Services\ExchangeRateService;
use App\Services\LicenseBoyPanelService;
use App\Services\LicenseBoyPriceService;
use App\Services\TaxCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class LicenseController extends Controller
{
    public function index(ExchangeRateService $exchange): View
    {
        $rates = $exchange->rates();

        $licenses = LicenseService::query()
            ->with(['lastInvoice', 'catalogItem'])
            ->withCount('invoices')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END")
            ->orderBy('next_due_date')
            ->orderBy('product')
            ->get();

        $rows = $licenses->map(function (LicenseService $license) use ($rates): array {
            $rate = $this->rateFor($license, $rates);
            $storeRate = $this->rateFor($license, $rates, $license->currency);

            return [
                'license' => $license,
                'rate' => $rate,
                'monthly_brl' => $license->monthlyBrl($rate),
                'yearly_brl' => round($license->monthlyBrl($rate) * 12, 2),
                'store_monthly_brl' => round($license->storeMonthlyEquivalent() * $storeRate, 2),
            ];
        });

        $active = $licenses->where('status', LicenseService::STATUS_ACTIVE);
        $nextDue = $active
            ->whereNotNull('next_due_date')
            ->sortBy(fn (LicenseService $license) => $license->next_due_date?->timestamp)
            ->first();

        $divergent = $licenses->filter(fn (LicenseService $license): bool => ($license->panelDivergencePercent() ?? 0) > 0);

        return view('licenses.index', [
            'rows' => $rows,
            'rates' => $rates,
            'panel' => [
                'configured' => app(LicenseBoyPanelService::class)->isConfigured(),
                'synced_at' => AppSetting::get('licenseboy_panel_synced_at'),
            ],
            'totals' => [
                'active' => $active->count(),
                'monthly_brl' => round($rows->sum('monthly_brl'), 2),
                'store_monthly_brl' => round($rows->sum('store_monthly_brl'), 2),
                'divergence_brl' => round($rows->sum('monthly_brl') - $rows->sum('store_monthly_brl'), 2),
                'yearly_brl' => round($rows->sum('yearly_brl'), 2),
                'due_soon' => $licenses->filter(fn (LicenseService $license) => $license->isDueSoon())->count(),
                'invoices' => $licenses->sum('invoices_count'),
                'next_due' => $nextDue?->next_due_date,
                'next_due_name' => $nextDue?->name,
                'divergent' => $divergent->count(),
            ],
        ]);
    }

    public function syncPanel(LicenseBoyPanelService $panel): RedirectResponse
    {
        if (! $panel->isConfigured()) {
            return redirect()
                ->route('settings.index', ['tab' => 'licenseboy'])
                ->with('warning', 'Informe o e-mail e a senha do painel da LicenseBoy para sincronizar os valores reais.');
        }

        try {
            $services = $panel->services();
            $result = $panel->apply($services);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Não foi possível ler o painel da LicenseBoy: '.$exception->getMessage());
        }

        $message = sprintf('%d serviços lidos no painel · %d licenças com o valor real atualizado.', count($services), $result['updated']);

        if ($result['unmatched'] !== []) {
            return back()->with('warning', $message.' Sem correspondência: '.implode(', ', $result['unmatched']).'.');
        }

        return back()->with('success', $message);
    }

    public function create(Request $request): View
    {
        $license = new LicenseService([
            'provider' => LicenseBoyPriceService::PROVIDER,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'status' => LicenseService::STATUS_ACTIVE,
            'quantity' => 1,
            'auto_renew' => true,
            'next_due_date' => now()->addMonthNoOverflow(),
        ]);

        $selected = $this->catalogItemFromRequest($request);

        if ($selected) {
            $license->fill($this->catalogAttributes($selected));
        }

        return view('licenses.form', [
            'license' => $license,
            'catalogItems' => $this->catalogItems(),
            'selectedCatalogId' => $selected?->id,
            'lastSyncedAt' => app(LicenseBoyPriceService::class)->lastSyncedAt(),
            'rates' => app(ExchangeRateService::class)->rates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $license = LicenseService::query()->create($data);

        return redirect()
            ->route('licenses.show', $license)
            ->with('success', 'Licença '.$license->product.' cadastrada com sucesso.');
    }

    public function prices(LicenseBoyPriceService $prices, ExchangeRateService $exchange): View
    {
        $items = LicenseCatalogItem::query()
            ->where('provider', LicenseBoyPriceService::PROVIDER)
            ->orderBy('name')
            ->get();

        $groups = collect(LicenseBoyPriceService::CATEGORIES)
            ->map(fn (string $label, string $category): array => [
                'category' => $category,
                'label' => $label,
                'items' => $items->where('category', $category)->values(),
            ])
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();

        $linked = LicenseService::query()->whereNotNull('license_catalog_item_id')->count();
        $summary = json_decode((string) AppSetting::get('license_prices_sync_summary'), true);

        return view('licenses.prices', [
            'groups' => $groups,
            'total' => $items->count(),
            'soldOut' => $items->reject(fn (LicenseCatalogItem $item): bool => $item->isAvailable())->count(),
            'linked' => $linked,
            'lastSyncedAt' => $prices->lastSyncedAt(),
            'summary' => is_array($summary) ? $summary : null,
            'rates' => $exchange->rates(),
        ]);
    }

    public function syncPrices(LicenseBoyPriceService $prices): RedirectResponse
    {
        try {
            $result = $prices->sync();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Não foi possível consultar a loja da LicenseBoy: '.$exception->getMessage());
        }

        $applied = $prices->applyToLicenses();

        if (count($result['failed']) === count(LicenseBoyPriceService::CATEGORIES)) {
            return back()->with('warning', 'A loja da LicenseBoy não respondeu. Nenhum preço foi alterado.');
        }

        $message = sprintf(
            '%d produtos no catálogo · %d novos · %d atualizados · %d removidos · %d licenças com preço atualizado.',
            $result['total'],
            $result['created'],
            $result['updated'],
            $result['removed'],
            $applied,
        );

        if ($result['failed'] !== []) {
            return back()->with('warning', $message.' Falhas: '.implode(', ', array_keys($result['failed'])).'.');
        }

        return back()->with('success', $message);
    }

    public function show(LicenseService $license, ExchangeRateService $exchange): View
    {
        $rates = $exchange->rates();
        $rate = $this->rateFor($license, $rates);

        return view('licenses.show', [
            'license' => $license->load([
                'catalogItem',
                'invoices' => fn ($query) => $query->orderByDesc('due_date'),
            ]),
            'rate' => $rate,
            'monthlyBrl' => $license->monthlyBrl($rate),
            'rates' => $rates,
        ]);
    }

    public function edit(LicenseService $license, ExchangeRateService $exchange): View
    {
        return view('licenses.form', [
            'license' => $license,
            'catalogItems' => $this->catalogItems(),
            'selectedCatalogId' => $license->license_catalog_item_id,
            'lastSyncedAt' => app(LicenseBoyPriceService::class)->lastSyncedAt(),
            'rates' => $exchange->rates(),
        ]);
    }

    public function update(Request $request, LicenseService $license): RedirectResponse
    {
        $data = $this->validated($request);

        if (blank($data['license_key'] ?? null)) {
            unset($data['license_key']);
        }

        $license->fill($data)->save();

        return redirect()
            ->route('licenses.show', $license)
            ->with('success', 'Licença atualizada.');
    }

    public function destroy(LicenseService $license): RedirectResponse
    {
        $product = $license->product;
        $license->delete();

        return redirect()
            ->route('licenses.index')
            ->with('success', 'Licença '.$product.' removida. As faturas já geradas foram preservadas.');
    }

    public function generateInvoice(
        LicenseService $license,
        TaxCalculationService $tax,
        ExchangeRateService $exchange,
    ): RedirectResponse {
        if ($license->status !== LicenseService::STATUS_ACTIVE) {
            return back()->with('warning', 'Somente licenças ativas podem gerar faturas.');
        }

        if (! in_array($license->billingCurrency(), ['EUR', 'USD'], true)) {
            return back()->with('warning', 'A fatura só pode ser gerada em EUR ou USD.');
        }

        $amount = $license->totalOriginal();

        if ($amount <= 0) {
            return back()->with('warning', 'Informe o valor unitário antes de gerar a fatura.');
        }

        $dueDate = $license->next_due_date ?? now()->addDays(7);

        $invoice = Invoice::query()->create([
            'external_reference' => $this->reference($license),
            'description' => mb_substr('Licença '.$license->product.' · '.$license->name, 0, 255),
            'currency' => $license->billingCurrency(),
            'amount_original' => $amount,
            'due_date' => $dueDate,
            'status' => Invoice::STATUS_PENDING,
            'license_service_id' => $license->id,
        ]);

        $rate = $exchange->rate($license->billingCurrency());
        $tax->applyToInvoice($invoice, $rate);

        $license->last_invoice_id = $invoice->id;

        if ($license->billingCycle() !== LicenseService::CYCLE_LIFETIME) {
            $license->next_due_date = $dueDate->copy()->addMonthsNoOverflow($license->billingCycleMonths());
        }

        $license->save();

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fatura '.$invoice->external_reference.' gerada para a licença e calculada com a cotação de '.number_format($rate, 4, ',', '.').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'license_catalog_item_id' => ['nullable', 'integer', 'exists:license_catalog_items,id'],
            'provider' => ['required', 'string', 'max:80'],
            'product' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'server_name' => ['nullable', 'string', 'max:190'],
            'license_key' => ['nullable', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'panel_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'panel_currency' => ['nullable', Rule::in(['USD', 'EUR'])],
            'panel_billing_cycle' => ['nullable', Rule::in(array_keys(LicenseService::cycles()))],
            'currency' => ['required', Rule::in(['USD', 'EUR'])],
            'billing_cycle' => ['required', Rule::in(array_keys(LicenseService::cycles()))],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(LicenseService::statuses()))],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'license_catalog_item_id' => 'preço do catálogo',
            'provider' => 'fornecedor',
            'product' => 'produto',
            'name' => 'identificação',
            'server_name' => 'servidor',
            'license_key' => 'chave da licença',
            'quantity' => 'quantidade',
            'unit_price' => 'valor unitário',
            'panel_price' => 'valor cobrado no painel',
            'panel_currency' => 'moeda do painel',
            'panel_billing_cycle' => 'ciclo do painel',
            'currency' => 'moeda',
            'billing_cycle' => 'ciclo de cobrança',
            'next_due_date' => 'próximo vencimento',
            'status' => 'situação',
        ]);

        $data['auto_renew'] = $request->boolean('auto_renew');

        $catalog = $this->catalogItem($data['license_catalog_item_id'] ?? null);

        if ($catalog) {
            // O catálogo é a fonte da verdade do preço: sobrescreve o que veio do formulário.
            $data = array_merge($data, $this->catalogAttributes($catalog, withName: false));
        } else {
            $data['license_catalog_item_id'] = null;
        }

        return $data;
    }

    /**
     * Catálogo agrupado na mesma ordem da loja da LicenseBoy.
     *
     * @return \Illuminate\Support\Collection<int, array{category: string, label: string, items: \Illuminate\Support\Collection<int, LicenseCatalogItem>}>
     */
    private function catalogItems()
    {
        $items = LicenseCatalogItem::query()
            ->where('provider', LicenseBoyPriceService::PROVIDER)
            ->orderBy('name')
            ->get();

        return collect(LicenseBoyPriceService::CATEGORIES)
            ->map(fn (string $label, string $category): array => [
                'category' => $category,
                'label' => $label,
                'items' => $items->where('category', $category)->values(),
            ])
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();
    }

    private function catalogItemFromRequest(Request $request): ?LicenseCatalogItem
    {
        return $this->catalogItem($request->input('catalogo')) ?? $this->catalogItem($request->old('license_catalog_item_id'));
    }

    private function catalogItem(mixed $id): ?LicenseCatalogItem
    {
        if (! is_numeric($id)) {
            return null;
        }

        return LicenseCatalogItem::query()
            ->where('provider', LicenseBoyPriceService::PROVIDER)
            ->find((int) $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogAttributes(LicenseCatalogItem $item, bool $withName = true): array
    {
        $attributes = [
            'license_catalog_item_id' => $item->id,
            'provider' => $item->provider,
            'product' => $item->productLabel(),
            'unit_price' => number_format((float) $item->price, 2, '.', ''),
            'currency' => $item->currency,
        ];

        if ($item->billing_cycle && array_key_exists($item->billing_cycle, LicenseService::cycles())) {
            $attributes['billing_cycle'] = $item->billing_cycle;
        }

        if ($withName) {
            $attributes['name'] = mb_substr($item->name, 0, 150);
        }

        return $attributes;
    }

    /**
     * @param  array<string, array{rate: float}>  $rates
     */
    private function rateFor(LicenseService $license, array $rates, ?string $currency = null): float
    {
        $currency ??= $license->billingCurrency();

        return (float) ($rates[$currency]['rate'] ?? 0);
    }

    private function reference(LicenseService $license): string
    {
        $reference = 'LIC-'.$license->id.'-'.now()->format('YmdHis');

        while (Invoice::query()->where('external_reference', $reference)->exists()) {
            $reference .= '-'.random_int(1, 99);
        }

        return $reference;
    }
}
