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

namespace App\Console\Commands;

use App\Events\InvoiceAmountUpdatedEvent;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceRateVariationNotification;
use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use App\Services\OvhInventoryService;
use App\Services\OvhService;
use App\Services\TaxCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SyncInvoicesCommand extends Command
{
    protected $signature = 'finance:sync-invoices
                            {--import : Importa as faturas recentes da OVHcloud}
                            {--fresh : Ignora o cache de 15 minutos das cotações}';

    protected $description = 'Atualiza as cotações, importa faturas da OVHcloud e recalcula os valores em BRL.';

    public function handle(
        ExchangeRateService $exchange,
        TaxCalculationService $tax,
        OvhService $ovh,
        OvhInventoryService $inventory,
        DashboardService $dashboard
    ): int {
        $this->info('Sincronização financeira iniciada em '.now()->format('d/m/Y H:i:s'));

        $rates = $exchange->rates((bool) $this->option('fresh'));

        foreach ($rates as $currency => $data) {
            $this->line(sprintf(
                '  %s: R$ %s %s',
                $currency,
                number_format($data['rate'], 4, ',', '.'),
                $data['stale'] ? '(última cotação conhecida)' : ''
            ));
        }

        if (collect($rates)->every(fn (array $data): bool => $data['stale'] ?? false)) {
            $this->warn('A AwesomeAPI está indisponível; os valores foram recalculados com a última cotação conhecida.');
        }

        $imported = $this->importFromOvh($ovh, $inventory, $tax, $rates);

        if ($imported['bills'] > 0) {
            $this->info($imported['bills'].' fatura(s) importada(s) da OVHcloud.');
        }

        if ($imported['items'] > 0) {
            $this->info($imported['items'].' item(ns) de fatura detalhado(s).');
        }

        if ($imported['services'] > 0 || $imported['ips'] > 0) {
            $this->info($imported['services'].' serviço(s) e '.$imported['ips'].' bloco(s) de IP sincronizado(s).');
        }

        $threshold = (float) AppSetting::get('rate_variation_threshold', config('finance.variation_threshold_percent', 1));
        $updated = 0;
        $alerts = 0;

        Invoice::query()
            ->pending()
            ->where('rate_locked', false)
            ->chunkById(100, function ($invoices) use ($rates, $tax, $dashboard, $threshold, &$updated, &$alerts): void {
                foreach ($invoices as $invoice) {
                    $rate = (float) ($rates[$invoice->currency]['rate'] ?? 0);

                    if ($rate <= 0) {
                        continue;
                    }

                    $previous = (float) $invoice->total_brl;
                    $result = $tax->applyToInvoice($invoice, $rate);

                    if (abs($result['total_brl'] - $previous) < 0.005) {
                        continue;
                    }

                    $updated++;
                    $variation = $previous > 0 ? (($result['total_brl'] - $previous) / $previous) * 100 : 0;
                    $invoice->refresh();

                    try {
                        InvoiceAmountUpdatedEvent::dispatch($invoice, $previous, $variation, $dashboard->totals());
                    } catch (Throwable $exception) {
                        Log::warning('Falha ao transmitir atualização de fatura em tempo real.', [
                            'invoice' => $invoice->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }

                    if (abs($variation) >= $threshold && $this->shouldNotify($invoice)) {
                        $this->notifyManagers($invoice, $variation, $previous, (float) $result['total_brl']);
                        $alerts++;
                    }
                }
            });

        $this->newLine();
        $this->table(
            ['Indicador', 'Valor'],
            [
                ['Faturas recalculadas', $updated],
                ['Alertas de variação (> '.number_format($threshold, 2, ',', '.').'%)', $alerts],
                ['Total pendente (BRL)', 'R$ '.number_format((float) $dashboard->totals()['pending_total'], 2, ',', '.')],
            ]
        );

        Log::info('Sincronização financeira concluída.', [
            'updated' => $updated,
            'alerts' => $alerts,
            'imported' => $imported,
        ]);

        $this->info('Sincronização concluída.');

        return self::SUCCESS;
    }

    /**
     * Importa faturas (pagas e pendentes), itens individuais, serviços e IPs.
     *
     * @param  array<string, array<string, mixed>>  $rates
     * @return array{bills: int, items: int, services: int, ips: int}
     */
    private function importFromOvh(
        OvhService $ovh,
        OvhInventoryService $inventory,
        TaxCalculationService $tax,
        array $rates
    ): array {
        $result = ['bills' => 0, 'items' => 0, 'services' => 0, 'ips' => 0];
        $autoImport = filter_var(AppSetting::get('ovh_auto_import', '1'), FILTER_VALIDATE_BOOLEAN);

        if (! $this->option('import') && ! $autoImport) {
            return $result;
        }

        if (! $ovh->isConfigured()) {
            $this->warn('Importação OVHcloud ignorada: credenciais não configuradas.');

            return $result;
        }

        try {
            $bills = $ovh->getRecentBills(
                (int) AppSetting::get('ovh_import_days', 365),
                (int) AppSetting::get('ovh_import_limit', 100)
            );
        } catch (Throwable $exception) {
            $this->warn('Não foi possível consultar a OVHcloud: '.$exception->getMessage());
            Log::warning('Falha na importação de faturas OVHcloud.', ['error' => $exception->getMessage()]);

            return $result;
        }

        foreach ($bills as $bill) {
            $attributes = $ovh->billToInvoiceAttributes($bill);

            if (empty($attributes['external_reference'])) {
                continue;
            }

            $invoice = Invoice::query()->firstOrCreate(
                ['external_reference' => $attributes['external_reference']],
                $attributes + ['total_brl' => 0, 'status' => Invoice::STATUS_PENDING]
            );

            $isNew = $invoice->wasRecentlyCreated;

            if ($isNew) {
                $rate = (float) ($rates[$invoice->currency]['rate'] ?? 0);

                if ($rate > 0) {
                    $tax->applyToInvoice($invoice, $rate);
                }

                $result['bills']++;
            }

            $this->markHistoricalBillAsPaid($invoice, $bill);
            $result['items'] += $inventory->importBillItems($invoice, $ovh);
        }

        $result['services'] = $inventory->syncServices($ovh);
        $result['ips'] = $inventory->syncIps($ovh);

        $inventory->refreshServicePrices();

        return $result;
    }

    /**
     * A OVHcloud não expõe um campo de pagamento em /me/bill. Faturas de meses
     * anteriores a este mês são consideradas quitadas e travadas; as do mês
     * corrente entram como pendentes para conciliação interna.
     *
     * @param  array<string, mixed>  $bill
     */
    private function markHistoricalBillAsPaid(Invoice $invoice, array $bill): void
    {
        if ($invoice->status !== Invoice::STATUS_PENDING || $invoice->paid_at !== null) {
            return;
        }

        $date = \Illuminate\Support\Carbon::parse(data_get($bill, 'date', $invoice->due_date));

        if (! $date->lt(now()->startOfMonth())) {
            return;
        }

        $invoice->forceFill([
            'status' => Invoice::STATUS_PAID,
            'paid_at' => $date,
            'rate_locked' => true,
        ])->save();
    }

    private function shouldNotify(Invoice $invoice): bool
    {
        return Cache::add('finance:rate-alert:invoice:'.$invoice->id, true, now()->addHours(6));
    }

    private function notifyManagers(Invoice $invoice, float $variation, float $previousTotal, float $newTotal): void
    {
        $managers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_FINANCIAL])
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        try {
            Notification::send(
                $managers,
                new InvoiceRateVariationNotification($invoice, $variation, $previousTotal, $newTotal)
            );
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar notificação de variação cambial.', [
                'invoice' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
