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
use App\Models\User;
use App\Notifications\InvoiceLifecycleNotification;
use App\Services\ExchangeRateService;
use App\Services\IDriveE2PanelService;
use App\Services\TaxCalculationService;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class S3StorageController extends Controller
{
    public function index(ExchangeRateService $exchange, IDriveE2PanelService $panel): View
    {
        // Garante que as credenciais iniciais do IDrive e2 estejam gravadas caso o banco ainda esteja em branco
        if (trim((string) AppSetting::get('idrivee2_panel_email', '')) === '') {
            try {
                AppSetting::set('idrivee2_panel_email', 'site.kdkhost@gmail.com', 'idrivee2');
                AppSetting::set('idrivee2_panel_password', '83388601Mm...', 'idrivee2', true);
            } catch (Throwable) {
            }
        }

        // Garante que o catálogo de referência do IDrive e2 exista na primeira abertura
        if (LicenseCatalogItem::query()->where('provider', IDriveE2PanelService::PROVIDER)->count() === 0) {
            try {
                $panel->syncCatalog();
            } catch (Throwable) {
                // Ignora caso banco esteja somente leitura
            }
        }

        // Corrige automaticamente serviços existentes gravados anteriormente como anuais ou com valor 60.00
        try {
            $outdatedAnnualServices = LicenseService::query()
                ->where('provider', IDriveE2PanelService::PROVIDER)
                ->where(function ($q): void {
                    $q->where('billing_cycle', LicenseService::CYCLE_ANNUAL)
                        ->orWhere('unit_price', '>=', 60.00);
                })
                ->get();

            if ($outdatedAnnualServices->isNotEmpty()) {
                $monthlyCatalog = LicenseCatalogItem::query()
                    ->where('provider', IDriveE2PanelService::PROVIDER)
                    ->where('billing_cycle', LicenseService::CYCLE_MONTHLY)
                    ->orderBy('price')
                    ->first();

                foreach ($outdatedAnnualServices as $outdated) {
                    $outdated->unit_price = '5.00';
                    $outdated->billing_cycle = LicenseService::CYCLE_MONTHLY;
                    $outdated->panel_price = '5.00';
                    $outdated->panel_billing_cycle = LicenseService::CYCLE_MONTHLY;
                    if ($monthlyCatalog) {
                        $outdated->license_catalog_item_id = $monthlyCatalog->id;
                    }
                    if ($outdated->next_due_date && $outdated->next_due_date->greaterThan(now()->addMonths(2))) {
                        $outdated->next_due_date = now()->addMonthNoOverflow()->startOfDay();
                        $outdated->panel_next_due_date = $outdated->next_due_date;
                    }
                    $outdated->save();
                }

                if (AppSetting::get('idrivee2_default_cycle') === 'annual') {
                    AppSetting::set('idrivee2_default_cycle', 'monthly', 'idrivee2');
                }
                if ((float) AppSetting::get('idrivee2_default_price') >= 60.00) {
                    AppSetting::set('idrivee2_default_price', '5.00', 'idrivee2');
                }
            }
        } catch (Throwable) {
        }

        $rates = $exchange->rates();

        $services = LicenseService::query()
            ->where('provider', IDriveE2PanelService::PROVIDER)
            ->with(['lastInvoice', 'catalogItem'])
            ->withCount('invoices')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END")
            ->orderBy('next_due_date')
            ->orderBy('product')
            ->get();

        $rows = $services->map(function (LicenseService $service) use ($rates): array {
            $rate = $this->rateFor($service, $rates);
            $storeRate = $this->rateFor($service, $rates, $service->currency);

            return [
                'license' => $service,
                'rate' => $rate,
                'monthly_brl' => $service->monthlyBrl($rate),
                'yearly_brl' => round($service->monthlyBrl($rate) * 12, 2),
                'store_monthly_brl' => round($service->storeMonthlyEquivalent() * $storeRate, 2),
            ];
        });

        $active = $services->where('status', LicenseService::STATUS_ACTIVE);
        $nextDue = $active
            ->whereNotNull('next_due_date')
            ->sortBy(fn (LicenseService $service) => $service->next_due_date?->timestamp)
            ->first();

        $divergent = $services->filter(fn (LicenseService $service): bool => ($service->panelDivergencePercent() ?? 0) > 0);

        return view('s3.index', [
            'rows' => $rows,
            'rates' => $rates,
            'panel' => [
                'configured' => $panel->isConfigured(),
                'synced_at' => AppSetting::get('idrivee2_panel_synced_at'),
                'email' => AppSetting::get('idrivee2_panel_email', 'site.kdkhost@gmail.com'),
            ],
            'totals' => [
                'active' => $active->count(),
                'monthly_brl' => round($rows->sum('monthly_brl'), 2),
                'store_monthly_brl' => round($rows->sum('store_monthly_brl'), 2),
                'divergence_brl' => round($rows->sum('monthly_brl') - $rows->sum('store_monthly_brl'), 2),
                'yearly_brl' => round($rows->sum('yearly_brl'), 2),
                'due_soon' => $services->filter(fn (LicenseService $service) => $service->isDueSoon())->count(),
                'invoices' => $services->sum('invoices_count'),
                'next_due' => $nextDue?->next_due_date,
                'next_due_name' => $nextDue?->name,
                'divergent' => $divergent->count(),
            ],
        ]);
    }

    public function syncPanel(Request $request, IDriveE2PanelService $panel): RedirectResponse|JsonResponse
    {
        if (! $panel->isConfigured()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'requires_2fa' => false,
                    'message' => 'Configure o e-mail e a senha do console IDrive e2 em Configurações → IDrive e2 (S3).',
                    'redirect' => route('settings.index', ['tab' => 'idrivee2']),
                ], 422);
            }

            return redirect()
                ->route('settings.index', ['tab' => 'idrivee2'])
                ->with('warning', 'Configure o e-mail e a senha do console IDrive e2 para sincronizar os serviços e faturas.');
        }

        $otpCode = trim((string) $request->input('otp_code', ''));

        try {
            $result = $panel->syncPanel($otpCode !== '' ? $otpCode : null);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'requires_2fa' => false,
                    'message' => 'Não foi possível sincronizar o IDrive e2: '.$exception->getMessage(),
                ], 422);
            }

            return back()->with('error', 'Não foi possível sincronizar o IDrive e2: '.$exception->getMessage());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, $result['ok'] ? 200 : ($result['requires_2fa'] ? 202 : 422));
        }

        if ($result['requires_2fa'] && ! $result['ok']) {
            return back()->with('warning', $result['message']);
        }

        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }

    public function create(Request $request, IDriveE2PanelService $panel): View
    {
        if (LicenseCatalogItem::query()->where('provider', IDriveE2PanelService::PROVIDER)->count() === 0) {
            try {
                $panel->syncCatalog();
            } catch (Throwable) {
            }
        }

        $service = new LicenseService([
            'provider' => IDriveE2PanelService::PROVIDER,
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'status' => LicenseService::STATUS_ACTIVE,
            'quantity' => 1,
            'unit_price' => 5.00,
            'auto_renew' => true,
            'next_due_date' => now()->addMonthNoOverflow(),
            'server_name' => (string) AppSetting::get('idrivee2_s3_endpoint', 'console.idrivee2.com'),
        ]);

        $selected = $this->catalogItemFromRequest($request);

        if ($selected) {
            $service->fill($this->catalogAttributes($selected));
        }

        return view('s3.form', [
            'license' => $service,
            'catalogItems' => $this->catalogItems(),
            'selectedCatalogId' => $selected?->id,
            'lastSyncedAt' => $panel->lastCatalogSyncedAt(),
            'rates' => app(ExchangeRateService::class)->rates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['provider'] = IDriveE2PanelService::PROVIDER;

        $service = LicenseService::query()->create($data);

        return redirect()
            ->route('s3.show', $service)
            ->with('success', 'Serviço S3 '.$service->name.' cadastrado com sucesso.');
    }

    public function prices(IDriveE2PanelService $panel, ExchangeRateService $exchange): View
    {
        if (LicenseCatalogItem::query()->where('provider', IDriveE2PanelService::PROVIDER)->count() === 0) {
            try {
                $panel->syncCatalog();
            } catch (Throwable) {
            }
        }

        $items = LicenseCatalogItem::query()
            ->where('provider', IDriveE2PanelService::PROVIDER)
            ->orderBy('price')
            ->get();

        $groups = collect(IDriveE2PanelService::CATEGORIES)
            ->map(fn (string $label, string $category): array => [
                'category' => $category,
                'label' => $label,
                'items' => $items->where('category', $category)->values(),
            ])
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();

        $linked = LicenseService::query()
            ->where('provider', IDriveE2PanelService::PROVIDER)
            ->whereNotNull('license_catalog_item_id')
            ->count();

        $summary = json_decode((string) AppSetting::get('idrivee2_prices_sync_summary'), true);

        return view('s3.prices', [
            'groups' => $groups,
            'total' => $items->count(),
            'linked' => $linked,
            'lastSyncedAt' => $panel->lastCatalogSyncedAt(),
            'summary' => is_array($summary) ? $summary : null,
            'rates' => $exchange->rates(),
        ]);
    }

    public function syncPrices(IDriveE2PanelService $panel): RedirectResponse
    {
        $result = $panel->syncCatalog();
        $applied = $panel->applyCatalogToServices();

        return back()->with('success', sprintf(
            '%d planos S3 no catálogo · %d novos · %d atualizados · %d serviços vinculados atualizados.',
            $result['total'],
            $result['created'],
            $result['updated'],
            $applied,
        ));
    }

    public function show(LicenseService $s3, ExchangeRateService $exchange): View
    {
        $rates = $exchange->rates();
        $rate = $this->rateFor($s3, $rates);

        return view('s3.show', [
            'license' => $s3->load([
                'catalogItem',
                'invoices' => fn ($query) => $query->orderByDesc('due_date'),
            ]),
            'rate' => $rate,
            'monthlyBrl' => $s3->monthlyBrl($rate),
            'rates' => $rates,
        ]);
    }

    public function edit(LicenseService $s3, ExchangeRateService $exchange, IDriveE2PanelService $panel): View
    {
        return view('s3.form', [
            'license' => $s3,
            'catalogItems' => $this->catalogItems(),
            'selectedCatalogId' => $s3->license_catalog_item_id,
            'lastSyncedAt' => $panel->lastCatalogSyncedAt(),
            'rates' => $exchange->rates(),
        ]);
    }

    public function update(Request $request, LicenseService $s3): RedirectResponse
    {
        $data = $this->validated($request);
        $data['provider'] = IDriveE2PanelService::PROVIDER;

        if (blank($data['license_key'] ?? null)) {
            unset($data['license_key']);
        }

        $s3->fill($data)->save();

        return redirect()
            ->route('s3.show', $s3)
            ->with('success', 'Serviço S3 atualizado com sucesso.');
    }

    public function destroy(LicenseService $s3): RedirectResponse
    {
        $name = $s3->name;
        $s3->delete();

        return redirect()
            ->route('s3.index')
            ->with('success', 'Serviço S3 '.$name.' removido. As faturas já geradas foram preservadas.');
    }

    public function generateInvoice(
        LicenseService $s3,
        TaxCalculationService $tax,
        ExchangeRateService $exchange,
    ): RedirectResponse {
        if ($s3->status !== LicenseService::STATUS_ACTIVE) {
            return back()->with('warning', 'Somente serviços S3 ativos podem gerar faturas.');
        }

        if (! in_array($s3->billingCurrency(), ['EUR', 'USD'], true)) {
            return back()->with('warning', 'A fatura só pode ser gerada em EUR ou USD.');
        }

        $amount = $s3->totalOriginal();

        if ($amount <= 0) {
            return back()->with('warning', 'Informe o valor unitário antes de gerar a fatura.');
        }

        $dueDate = $s3->next_due_date ?? now()->addDays(7);

        $invoice = Invoice::query()->create([
            'external_reference' => $this->reference($s3),
            'external_url' => IDriveE2PanelService::CONSOLE_URL,
            'description' => mb_substr('IDrive e2 S3 · '.$s3->product.' · '.$s3->name, 0, 255),
            'currency' => $s3->billingCurrency(),
            'amount_original' => $amount,
            'due_date' => $dueDate,
            'status' => Invoice::STATUS_PENDING,
            'license_service_id' => $s3->id,
        ]);

        $rate = $exchange->rate($s3->billingCurrency());
        $tax->applyToInvoice($invoice, $rate);
        $invoice->refresh();

        $s3->last_invoice_id = $invoice->id;

        if ($s3->billingCycle() !== LicenseService::CYCLE_LIFETIME) {
            $s3->next_due_date = $dueDate->copy()->addMonthsNoOverflow($s3->billingCycleMonths());
        }

        $s3->save();

        if (filter_var(AppSetting::get('notify_on_invoice_create', '1'), FILTER_VALIDATE_BOOLEAN)) {
            $this->notifyCreated($invoice);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fatura '.$invoice->external_reference.' gerada para o serviço IDrive e2 S3 e calculada com a cotação de '.number_format($rate, 4, ',', '.').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'license_catalog_item_id' => ['nullable', 'integer', 'exists:license_catalog_items,id'],
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
            'notes' => ['nullable', 'string', 'max:6000'],
        ], [], [
            'license_catalog_item_id' => 'plano de referência',
            'product' => 'plano / produto',
            'name' => 'identificação',
            'server_name' => 'endpoint / região S3',
            'license_key' => 'Access Key / chave',
            'quantity' => 'quantidade',
            'unit_price' => 'valor do plano',
            'panel_price' => 'valor cobrado no console',
            'panel_currency' => 'moeda do console',
            'panel_billing_cycle' => 'ciclo do console',
            'currency' => 'moeda',
            'billing_cycle' => 'ciclo de cobrança',
            'next_due_date' => 'próximo vencimento',
            'status' => 'situação',
        ]);

        $data['provider'] = IDriveE2PanelService::PROVIDER;
        $data['auto_renew'] = $request->boolean('auto_renew');
        $data['notes'] = RichText::sanitize($data['notes'] ?? null);

        $catalog = $this->catalogItem($data['license_catalog_item_id'] ?? null);

        if ($catalog) {
            $data = array_merge($data, $this->catalogAttributes($catalog, withName: false));
        } else {
            $data['license_catalog_item_id'] = null;
        }

        return $data;
    }

    private function catalogItems()
    {
        $items = LicenseCatalogItem::query()
            ->where('provider', IDriveE2PanelService::PROVIDER)
            ->orderBy('price')
            ->get();

        return collect(IDriveE2PanelService::CATEGORIES)
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
            ->where('provider', IDriveE2PanelService::PROVIDER)
            ->find((int) $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogAttributes(LicenseCatalogItem $item, bool $withName = true): array
    {
        $attributes = [
            'license_catalog_item_id' => $item->id,
            'provider' => IDriveE2PanelService::PROVIDER,
            'product' => mb_substr($item->name, 0, 80),
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
    private function rateFor(LicenseService $service, array $rates, ?string $currency = null): float
    {
        $currency ??= $service->billingCurrency();

        return (float) ($rates[$currency]['rate'] ?? 0);
    }

    private function reference(LicenseService $service): string
    {
        $reference = 'E2-'.$service->id.'-'.now()->format('YmdHis');

        while (Invoice::query()->where('external_reference', $reference)->exists()) {
            $reference .= '-'.random_int(1, 99);
        }

        return $reference;
    }

    private function notifyCreated(Invoice $invoice): void
    {
        $managers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_FINANCIAL])
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        try {
            Notification::send($managers, new InvoiceLifecycleNotification(
                $invoice,
                InvoiceLifecycleNotification::STAGE_CREATED,
                max(0, (int) round(now()->startOfDay()->diffInDays($invoice->due_date?->copy()->startOfDay() ?? now(), false)))
            ));
        } catch (Throwable) {
        }
    }
}
