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

use App\Events\InvoiceAmountUpdatedEvent;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use App\Services\InvoiceMailDataService;
use App\Services\MailTemplateService;
use App\Services\OvhService;
use App\Services\TaxCalculationService;
use App\Support\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchange,
        private readonly TaxCalculationService $tax,
    ) {}

    public function index(Request $request): View
    {
        $invoices = $this->filtered($request)->paginate(12)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->only(['q', 'status', 'currency', 'overdue', 'sort', 'dir']),
            'summary' => [
                'pending' => Invoice::query()->pending()->count(),
                'paid' => Invoice::query()->paid()->count(),
                'canceled' => Invoice::query()->where('status', Invoice::STATUS_CANCELED)->count(),
                'overdue' => Invoice::query()->overdue()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('invoices.create', [
            'invoice' => new Invoice([
                'currency' => 'EUR',
                'due_date' => now()->addDays(7),
                'status' => Invoice::STATUS_PENDING,
            ]),
            'rates' => $this->exchange->rates(),
            'taxes' => $this->tax->percentages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $applyServiceTaxes = $this->applyServiceTaxes($request);

        $invoice = Invoice::query()->create([
            'external_reference' => $data['external_reference'] ?? null,
            'description' => $data['description'],
            'currency' => $data['currency'],
            'amount_original' => $data['amount_original'],
            'due_date' => $data['due_date'],
            'status' => Invoice::STATUS_PENDING,
            'apply_service_taxes' => $applyServiceTaxes,
            'tax_nature' => $data['tax_nature'] ?? 'service',
        ]);

        $rate = isset($data['exchange_rate'])
            ? (float) $data['exchange_rate']
            : $this->exchange->rate($invoice->currency);

        $this->tax->applyToInvoice($invoice, $rate, [], $applyServiceTaxes);

        if (($data['status'] ?? Invoice::STATUS_PENDING) === Invoice::STATUS_PAID) {
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
                'rate_locked' => true,
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fatura criada e calculada com a cotação de '.number_format($rate, 4, ',', '.').'.');
    }

    public function show(Invoice $invoice): View
    {
        return view('invoices.show', [
            'invoice' => $invoice->load('items'),
            'rates' => $this->exchange->rates(),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        return view('invoices.edit', [
            'invoice' => $invoice,
            'rates' => $this->exchange->rates(),
            'taxes' => $this->tax->percentages(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $this->validated($request, $invoice);

        $invoice->fill([
            'external_reference' => $data['external_reference'] ?? null,
            'description' => $data['description'],
            'due_date' => $data['due_date'],
            'tax_nature' => $data['tax_nature'] ?? ($invoice->tax_nature ?: 'service'),
            'apply_service_taxes' => $request->has('apply_service_taxes')
                ? $request->boolean('apply_service_taxes')
                : (bool) $invoice->apply_service_taxes,
        ]);

        if (! $invoice->rate_locked) {
            $invoice->currency = $data['currency'];
            $invoice->amount_original = $data['amount_original'];

            $rate = isset($data['exchange_rate'])
                ? (float) $data['exchange_rate']
                : $this->exchange->rate($invoice->currency);

            $this->tax->applyToInvoice($invoice, $rate, [], $invoice->apply_service_taxes);
        }

        $desiredStatus = $data['status'] ?? $invoice->status;

        if ($desiredStatus === Invoice::STATUS_PAID && $invoice->status !== Invoice::STATUS_PAID) {
            $invoice->status = Invoice::STATUS_PAID;
            $invoice->paid_at = now();
            $invoice->rate_locked = true;
        } elseif ($desiredStatus !== Invoice::STATUS_PAID) {
            $invoice->status = $desiredStatus;
            $invoice->paid_at = null;
        }

        $invoice->save();

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Fatura atualizada com sucesso.');
    }

    public function pay(Invoice $invoice, MailTemplateService $mail, InvoiceMailDataService $invoiceData): RedirectResponse
    {
        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return back()->with('warning', 'Somente faturas pendentes podem ser marcadas como pagas.');
        }

        if (! $invoice->rate_locked) {
            $this->tax->applyToInvoice($invoice, $this->exchange->rate($invoice->currency), [], (bool) $invoice->apply_service_taxes);
        }

        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
            'rate_locked' => true,
        ]);

        $mail->sendToAlerts('invoice_paid', $invoiceData->variables($invoice, [
            'data_pagamento' => now()->format('d/m/Y H:i'),
        ]));

        return back()->with('success', 'Fatura '.$invoice->external_reference.' marcada como paga e câmbio travado.');
    }

    public function syncPayment(Invoice $invoice, OvhService $ovh): RedirectResponse
    {
        if ($invoice->status !== Invoice::STATUS_PENDING) {
            return back()->with('warning', 'Somente faturas pendentes precisam de conciliação de pagamento.');
        }

        $reference = trim((string) $invoice->external_reference);

        if ($reference === '') {
            return back()->with('warning', 'Esta fatura não possui referência da OVHcloud para consultar.');
        }

        if (! $ovh->isConfigured()) {
            return back()->with('error', 'As credenciais da OVHcloud não estão configuradas no painel.');
        }

        try {
            $bill = $ovh->refreshBill($reference);
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar a fatura na OVHcloud.', [
                'invoice' => $invoice->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível consultar a fatura '.$reference.' na OVHcloud agora.');
        }

        $status = $ovh->paymentStatusForBill($bill, null, true);

        if ($status === 'paid') {
            $paidAt = Carbon::parse(data_get($bill, 'date', now()));

            if (! $invoice->rate_locked) {
                $this->tax->applyToInvoice($invoice, $this->exchange->rate($invoice->currency), [], (bool) $invoice->apply_service_taxes);
            }

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => $paidAt,
                'rate_locked' => true,
            ]);

            return back()->with('success', 'Pagamento confirmado na OVHcloud: fatura '.$reference.' marcada como paga e câmbio travado.');
        }

        if ($status === 'pending') {
            return back()->with('warning', 'A OVHcloud ainda registra a fatura '.$reference.' como não paga.');
        }

        return back()->with('warning', 'A OVHcloud não informou o status de pagamento desta fatura.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $invoice->update([
            'status' => Invoice::STATUS_CANCELED,
            'paid_at' => null,
        ]);

        return back()->with('success', 'Fatura cancelada.');
    }

    public function reopen(Invoice $invoice): RedirectResponse
    {
        $invoice->update([
            'status' => Invoice::STATUS_PENDING,
            'paid_at' => null,
            'rate_locked' => false,
        ]);

        return back()->with('success', 'Fatura reaberta como pendente.');
    }

    public function recalculate(Invoice $invoice): RedirectResponse
    {
        if ($invoice->rate_locked) {
            return back()->with('warning', 'O câmbio está travado. Destrave antes de recalcular.');
        }

        $previous = (float) $invoice->total_brl;
        $result = $this->tax->applyToInvoice($invoice, $this->exchange->rate($invoice->currency), [], (bool) $invoice->apply_service_taxes);
        $invoice->refresh();

        $variation = $previous > 0 ? (($result['total_brl'] - $previous) / $previous) * 100 : 0;
        $this->broadcast($invoice, $previous, $variation);

        return back()->with('success', 'Valores recalculados: R$ '.number_format($result['total_brl'], 2, ',', '.').'.');
    }

    public function toggleLock(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['rate_locked' => ! $invoice->rate_locked]);

        return back()->with(
            'success',
            $invoice->rate_locked ? 'Câmbio travado nesta fatura.' : 'Câmbio destravado; a fatura volta a acompanhar a cotação.'
        );
    }

    public function export(Request $request, CsvExporter $exporter): StreamedResponse
    {
        $invoices = $this->filtered($request)->limit(5000)->get();

        $rows = $invoices->map(fn (Invoice $invoice): array => [
            'amount_original' => number_format((float) $invoice->amount_original, 2, ',', ''),
            'calculated_at' => $invoice->calculated_at?->format('d/m/Y H:i') ?? '',
            'card_total' => number_format($invoice->cardTotal(), 2, ',', ''),
            'currency' => $invoice->currency,
            'description' => $invoice->description,
            'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
            'exchange_rate' => number_format((float) $invoice->exchange_rate, 4, ',', ''),
            'external_reference' => $invoice->external_reference ?? '',
            'icms' => number_format((float) $invoice->icms_amount, 2, ',', ''),
            'import_taxes' => number_format($invoice->importTaxesTotal(), 2, ',', ''),
            'iof' => number_format((float) $invoice->iof_amount, 2, ',', ''),
            'other_taxes' => number_format((float) $invoice->other_taxes_amount, 2, ',', ''),
            'paid_at' => $invoice->paid_at?->format('d/m/Y H:i') ?? '',
            'rate_locked' => $invoice->rate_locked ? 'Sim' : 'Não',
            'spread' => number_format((float) $invoice->spread_amount, 2, ',', ''),
            'status' => $invoice->statusLabel(),
            'tax_nature' => $invoice->tax_nature === 'goods' ? 'Produto' : 'Serviço',
            'total_brl' => number_format((float) $invoice->total_brl, 2, ',', ''),
        ]);

        return $exporter->download(
            'faturas-'.now()->format('Y-m-d-His').'.csv',
            $rows->all(),
            [
                'amount_original' => 'Valor original',
                'calculated_at' => 'Calculado em',
                'card_total' => 'Total no cartão (R$)',
                'currency' => 'Moeda',
                'description' => 'Descrição',
                'due_date' => 'Vencimento',
                'exchange_rate' => 'Cotação',
                'external_reference' => 'Referência OVH',
                'icms' => 'ICMS (R$)',
                'import_taxes' => 'Tributos da importação (R$)',
                'iof' => 'IOF (R$)',
                'other_taxes' => 'Outros tributos (R$)',
                'paid_at' => 'Pago em',
                'rate_locked' => 'Câmbio travado',
                'spread' => 'Spread (R$)',
                'status' => 'Status',
                'tax_nature' => 'Natureza',
                'total_brl' => 'Total a reservar (R$)',
            ]
        );
    }

    /**
     * Sem o campo no formulário, segue a configuração global de tributos.
     * O formulário sempre envia o valor quando o usuário usa a tela.
     */
    private function applyServiceTaxes(Request $request): bool
    {
        if ($request->has('apply_service_taxes')) {
            return $request->boolean('apply_service_taxes');
        }

        return filter_var(AppSetting::get('service_taxes_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Invoice $invoice = null): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'external_reference' => [
                'nullable', 'string', 'max:100',
                Rule::unique('invoices', 'external_reference')->ignore($invoice?->id),
            ],
            'currency' => ['required', Rule::in(['EUR', 'USD'])],
            'amount_original' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001', 'max:999.9999'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(array_keys(Invoice::statuses()))],
            'tax_nature' => ['nullable', Rule::in(array_keys(Invoice::natures()))],
            'apply_service_taxes' => ['nullable', 'boolean'],
        ], [], [
            'description' => 'descrição',
            'external_reference' => 'referência externa',
            'currency' => 'moeda',
            'amount_original' => 'valor original',
            'exchange_rate' => 'cotação',
            'due_date' => 'vencimento',
            'status' => 'status',
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = Invoice::query();

        if (in_array($request->query('status'), array_keys(Invoice::statuses()), true)) {
            $query->where('status', $request->query('status'));
        }

        if (in_array($request->query('currency'), ['EUR', 'USD'], true)) {
            $query->where('currency', $request->query('currency'));
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('description', 'like', '%'.$term.'%')
                    ->orWhere('external_reference', 'like', '%'.$term.'%');
            });
        }

        $sort = (string) $request->query('sort', 'due_date');
        $direction = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['due_date', 'total_brl', 'amount_original', 'created_at', 'status'], true)) {
            $sort = 'due_date';
        }

        return $query->orderBy($sort, $direction)->orderBy('id', 'desc');
    }

    private function broadcast(Invoice $invoice, float $previous, float $variation): void
    {
        try {
            InvoiceAmountUpdatedEvent::dispatch($invoice, $previous, $variation, app(DashboardService::class)->totals());
        } catch (Throwable $exception) {
            Log::warning('Falha ao transmitir atualização em tempo real.', [
                'invoice' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
