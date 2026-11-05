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

use App\Models\Invoice;
use App\Models\LicenseService;
use App\Services\InvoiceMailDataService;
use App\Services\MailTemplateService;
use App\Support\Settings\MailSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyAlertsCommand extends Command
{
    protected $signature = 'finance:notify-alerts
                            {--invoice-days=3 : Dias de antecedência para faturas}
                            {--license-days=7 : Dias de antecedência para licenças}';

    protected $description = 'Envia por e-mail os alertas de faturas e licenças próximas do vencimento';

    public function handle(MailTemplateService $mail, InvoiceMailDataService $invoiceData): int
    {
        if (! MailSettings::configured()) {
            $this->warn('SMTP não configurado: nenhum alerta foi enviado.');

            return self::SUCCESS;
        }

        if (MailSettings::alertRecipients() === []) {
            $this->warn('Nenhum destinatário de alerta ativo: nenhum e-mail foi enviado.');

            return self::SUCCESS;
        }

        $invoiceDays = max(0, (int) $this->option('invoice-days'));
        $licenseDays = max(0, (int) $this->option('license-days'));
        $sent = 0;
        $skipped = 0;

        $due = Invoice::query()
            ->pending()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($invoiceDays)->toDateString())
            ->orderBy('due_date')
            ->get();

        foreach ($due as $invoice) {
            $key = 'finance:alert:invoice-due:'.$invoice->id.':'.($invoice->due_date?->toDateString() ?? '');

            if (! Cache::add($key, true, now()->addDays(20))) {
                $skipped++;

                continue;
            }

            $days = max(0, (int) round(now()->startOfDay()->diffInDays($invoice->due_date?->copy()->startOfDay() ?? now())));

            if (! $mail->sendToAlerts('invoice_due', $invoiceData->variables($invoice, [
                'dias' => (string) $days,
            ]))) {
                Cache::forget($key);
                $skipped++;

                continue;
            }

            $sent++;
        }

        $overdue = Invoice::query()
            ->pending()
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->get();

        foreach ($overdue as $invoice) {
            $key = 'finance:alert:invoice-overdue:'.$invoice->id;

            if (! Cache::add($key, true, now()->addDays(30))) {
                $skipped++;

                continue;
            }

            $days = max(1, (int) round(($invoice->due_date?->copy()->startOfDay() ?? now())->diffInDays(now()->startOfDay())));

            if (! $mail->sendToAlerts('invoice_overdue', $invoiceData->variables($invoice, [
                'dias_atraso' => (string) $days,
            ]))) {
                Cache::forget($key);
                $skipped++;

                continue;
            }

            $sent++;
        }

        $licenses = LicenseService::query()
            ->active()
            ->dueSoon($licenseDays)
            ->orderBy('next_due_date')
            ->get();

        foreach ($licenses as $license) {
            $key = 'finance:alert:license:'.$license->id.':'.($license->next_due_date?->toDateString() ?? '');

            if (! Cache::add($key, true, now()->addDays(20))) {
                $skipped++;

                continue;
            }

            $days = max(0, (int) round(now()->startOfDay()->diffInDays($license->next_due_date?->copy()->startOfDay() ?? now())));

            if (! $mail->sendToAlerts('license_expiring', [
                'usuario' => 'equipe financeira',
                'licenca' => (string) ($license->name ?: $license->product),
                'produto' => (string) $license->product,
                'vencimento' => $license->next_due_date?->format('d/m/Y') ?? '—',
                'dias' => (string) $days,
                'valor' => $license->billingCurrencySymbol().' '.number_format($license->totalOriginal(), 2, ',', '.'),
                'link' => route('licenses.show', $license),
            ])) {
                Cache::forget($key);
                $skipped++;

                continue;
            }

            $sent++;
        }

        $this->info('Alertas enviados: '.$sent.' · já avisados ou sem envio: '.$skipped.'.');

        return self::SUCCESS;
    }
}
