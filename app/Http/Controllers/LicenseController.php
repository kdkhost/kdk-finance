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

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\LicenseService;
use App\Services\ExchangeRateService;
use App\Services\TaxCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(ExchangeRateService $exchange): View
    {
        $rates = $exchange->rates();

        $licenses = LicenseService::query()
            ->with('lastInvoice')
            ->withCount('invoices')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END")
            ->orderBy('next_due_date')
            ->orderBy('product')
            ->get();

        $rows = $licenses->map(function (LicenseService $license) use ($rates): array {
            $rate = $this->rateFor($license, $rates);

            return [
                'license' => $license,
                'rate' => $rate,
                'monthly_brl' => $license->monthlyBrl($rate),
                'yearly_brl' => round($license->monthlyBrl($rate) * 12, 2),
            ];
        });

        $active = $licenses->where('status', LicenseService::STATUS_ACTIVE);
        $nextDue = $active
            ->whereNotNull('next_due_date')
            ->sortBy(fn (LicenseService $license) => $license->next_due_date?->timestamp)
            ->first();

        return view('licenses.index', [
            'rows' => $rows,
            'rates' => $rates,
            'totals' => [
                'active' => $active->count(),
                'monthly_brl' => round($rows->sum('monthly_brl'), 2),
                'yearly_brl' => round($rows->sum('yearly_brl'), 2),
                'due_soon' => $licenses->filter(fn (LicenseService $license) => $license->isDueSoon())->count(),
                'invoices' => $licenses->sum('invoices_count'),
                'next_due' => $nextDue?->next_due_date,
                'next_due_name' => $nextDue?->name,
            ],
        ]);
    }

    public function create(): View
    {
        return view('licenses.form', [
            'license' => new LicenseService([
                'provider' => 'LicenseBoy',
                'currency' => 'USD',
                'billing_cycle' => LicenseService::CYCLE_MONTHLY,
                'status' => LicenseService::STATUS_ACTIVE,
                'quantity' => 1,
                'auto_renew' => true,
                'next_due_date' => now()->addMonthNoOverflow(),
            ]),
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

    public function show(LicenseService $license, ExchangeRateService $exchange): View
    {
        $rates = $exchange->rates();
        $rate = $this->rateFor($license, $rates);

        return view('licenses.show', [
            'license' => $license->load(['invoices' => fn ($query) => $query->orderByDesc('due_date')]),
            'rate' => $rate,
            'monthlyBrl' => $license->monthlyBrl($rate),
            'rates' => $rates,
        ]);
    }

    public function edit(LicenseService $license, ExchangeRateService $exchange): View
    {
        return view('licenses.form', [
            'license' => $license,
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

        if (! in_array($license->currency, ['EUR', 'USD'], true)) {
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
            'currency' => $license->currency,
            'amount_original' => $amount,
            'due_date' => $dueDate,
            'status' => Invoice::STATUS_PENDING,
            'license_service_id' => $license->id,
        ]);

        $rate = $exchange->rate($license->currency);
        $tax->applyToInvoice($invoice, $rate);

        $license->last_invoice_id = $invoice->id;

        if ($license->billing_cycle !== LicenseService::CYCLE_LIFETIME) {
            $license->next_due_date = $dueDate->copy()->addMonthsNoOverflow($license->cycleMonths());
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
            'provider' => ['required', 'string', 'max:80'],
            'product' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'server_name' => ['nullable', 'string', 'max:190'],
            'license_key' => ['nullable', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['required', Rule::in(['USD', 'EUR'])],
            'billing_cycle' => ['required', Rule::in(array_keys(LicenseService::cycles()))],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(LicenseService::statuses()))],
            'auto_renew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'provider' => 'fornecedor',
            'product' => 'produto',
            'name' => 'identificação',
            'server_name' => 'servidor',
            'license_key' => 'chave da licença',
            'quantity' => 'quantidade',
            'unit_price' => 'valor unitário',
            'currency' => 'moeda',
            'billing_cycle' => 'ciclo de cobrança',
            'next_due_date' => 'próximo vencimento',
            'status' => 'situação',
        ]);

        $data['auto_renew'] = $request->boolean('auto_renew');

        return $data;
    }

    /**
     * @param  array<string, array{rate: float}>  $rates
     */
    private function rateFor(LicenseService $license, array $rates): float
    {
        return (float) ($rates[$license->currency]['rate'] ?? 0);
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
