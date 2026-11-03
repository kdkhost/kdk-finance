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

namespace App\Console\Commands;

use App\Events\InvoiceAmountUpdatedEvent;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceRateVariationNotification;
use App\Services\DashboardService;
use App\Services\ExchangeRateService;
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

        $imported = $this->importFromOvh($ovh, $tax, $rates);

        if ($imported > 0) {
            $this->info($imported.' fatura(s) importada(s) da OVHcloud.');
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
     * @param  array<string, array<string, mixed>>  $rates
     */
    private function importFromOvh(OvhService $ovh, TaxCalculationService $tax, array $rates): int
    {
        $autoImport = filter_var(AppSetting::get('ovh_auto_import', '1'), FILTER_VALIDATE_BOOLEAN);

        if (! $this->option('import') && ! $autoImport) {
            return 0;
        }

        if (! $ovh->isConfigured()) {
            $this->warn('Importação OVHcloud ignorada: credenciais não configuradas.');

            return 0;
        }

        try {
            $bills = $ovh->getRecentBills(
                (int) AppSetting::get('ovh_import_days', 60),
                (int) AppSetting::get('ovh_import_limit', 25)
            );
        } catch (Throwable $exception) {
            $this->warn('Não foi possível consultar a OVHcloud: '.$exception->getMessage());
            Log::warning('Falha na importação de faturas OVHcloud.', ['error' => $exception->getMessage()]);

            return 0;
        }

        $imported = 0;

        foreach ($bills as $bill) {
            $attributes = $ovh->billToInvoiceAttributes($bill);

            if (empty($attributes['external_reference'])) {
                continue;
            }

            $invoice = Invoice::query()->firstOrCreate(
                ['external_reference' => $attributes['external_reference']],
                $attributes + ['total_brl' => 0, 'status' => Invoice::STATUS_PENDING]
            );

            if (! $invoice->wasRecentlyCreated) {
                continue;
            }

            $rate = (float) ($rates[$invoice->currency]['rate'] ?? 0);

            if ($rate > 0) {
                $tax->applyToInvoice($invoice, $rate);
            }

            $imported++;
        }

        return $imported;
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
