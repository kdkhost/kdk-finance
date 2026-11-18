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

use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\LicenseService;
use App\Models\User;
use App\Notifications\InvoiceLifecycleNotification;
use App\Services\InvoiceMailDataService;
use App\Services\MailTemplateService;
use App\Support\Settings\MailSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotifyAlertsCommand extends Command
{
    protected $signature = 'finance:notify-alerts
                            {--invoice-days= : Dias de antecedência para faturas (padrão lido de app_settings)}
                            {--license-days=7 : Dias de antecedência para licenças e serviços S3}';

    protected $description = 'Envia alertas no painel e por e-mail (dias antes do vencimento, no vencimento e risco de suspensão)';

    public function handle(MailTemplateService $mail, InvoiceMailDataService $invoiceData): int
    {
        $configuredDaysBefore = (int) ($this->option('invoice-days') !== null && $this->option('invoice-days') !== ''
            ? $this->option('invoice-days')
            : AppSetting::get('notify_days_before_due', 7));

        $invoiceDays = max(1, $configuredDaysBefore);
        $licenseDays = max(1, (int) $this->option('license-days'));
        $notifyOnDueDate = filter_var(AppSetting::get('notify_on_due_date', '1'), FILTER_VALIDATE_BOOLEAN);
        $suspensionDays = max(1, (int) AppSetting::get('notify_suspension_days_overdue', 3));

        $managers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_FINANCIAL])
            ->get();

        $smtpReady = MailSettings::configured() && MailSettings::alertRecipients() !== [];

        if (! $smtpReady) {
            $this->warn('SMTP ou destinatários não configurados: os alertas serão gravados na Central de Notificações do painel.');
        }

        $sent = 0;
        $skipped = 0;

        // 1. Faturas próximas do vencimento (dias antes configurados + no dia do vencimento)
        $due = Invoice::query()
            ->pending()
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($invoiceDays)->toDateString())
            ->orderBy('due_date')
            ->get();

        foreach ($due as $invoice) {
            $days = max(0, (int) round(now()->startOfDay()->diffInDays($invoice->due_date?->copy()->startOfDay() ?? now())));

            if ($days === 0 && ! $notifyOnDueDate) {
                $skipped++;

                continue;
            }

            $stage = $days === 0
                ? InvoiceLifecycleNotification::STAGE_DUE_TODAY
                : InvoiceLifecycleNotification::STAGE_BEFORE_DUE;

            $key = 'finance:alert:invoice-'.$stage.':'.$invoice->id.':'.($invoice->due_date?->toDateString() ?? '');

            if (! Cache::add($key, true, now()->addHours(20))) {
                $skipped++;

                continue;
            }

            if ($managers->isNotEmpty()) {
                try {
                    Notification::send($managers, new InvoiceLifecycleNotification($invoice, $stage, $days));
                } catch (Throwable) {
                }
            }

            if ($smtpReady) {
                $mail->sendToAlerts('invoice_due', $invoiceData->variables($invoice, [
                    'dias' => (string) $days,
                ]));
            }

            $sent++;
        }

        // 2. Faturas vencidas e em risco de suspensão
        $overdue = Invoice::query()
            ->pending()
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->get();

        foreach ($overdue as $invoice) {
            $daysOverdue = max(1, (int) round(($invoice->due_date?->copy()->startOfDay() ?? now())->diffInDays(now()->startOfDay())));
            $isSuspensionRisk = $daysOverdue >= $suspensionDays;

            $key = $isSuspensionRisk
                ? 'finance:alert:invoice-suspension:'.$invoice->id.':'.now()->toDateString()
                : 'finance:alert:invoice-overdue:'.$invoice->id;

            if (! Cache::add($key, true, $isSuspensionRisk ? now()->addDays(3) : now()->addDays(15))) {
                $skipped++;

                continue;
            }

            if ($managers->isNotEmpty()) {
                try {
                    Notification::send($managers, new InvoiceLifecycleNotification(
                        $invoice,
                        InvoiceLifecycleNotification::STAGE_SUSPENSION,
                        -$daysOverdue
                    ));
                } catch (Throwable) {
                }
            }

            if ($smtpReady) {
                $mail->sendToAlerts('invoice_overdue', $invoiceData->variables($invoice, [
                    'dias_atraso' => (string) $daysOverdue,
                ]));
            }

            $sent++;
        }

        // 3. Licenças (LicenseBoy) e Serviços S3 (IDrive e2) próximos da renovação/vencimento
        $licenses = LicenseService::query()
            ->active()
            ->dueSoon($licenseDays)
            ->orderBy('next_due_date')
            ->get();

        foreach ($licenses as $license) {
            $key = 'finance:alert:license:'.$license->id.':'.($license->next_due_date?->toDateString() ?? '');

            if (! Cache::add($key, true, now()->addDays(7))) {
                $skipped++;

                continue;
            }

            $days = max(0, (int) round(now()->startOfDay()->diffInDays($license->next_due_date?->copy()->startOfDay() ?? now())));
            $isS3 = str_contains(mb_strtolower((string) $license->provider), 'idrive');
            $link = $isS3 ? route('s3.show', $license) : route('licenses.show', $license);

            if ($smtpReady) {
                $mail->sendToAlerts('license_expiring', [
                    'usuario' => 'equipe financeira',
                    'licenca' => (string) ($license->name ?: $license->product),
                    'produto' => (string) ($license->provider.' · '.$license->product),
                    'vencimento' => $license->next_due_date?->format('d/m/Y') ?? '—',
                    'dias' => (string) $days,
                    'valor' => $license->billingCurrencySymbol().' '.number_format($license->totalOriginal(), 2, ',', '.'),
                    'link' => $link,
                ]);
            }

            $sent++;
        }

        $this->info('Alertas processados: '.$sent.' enviado(s)/registrado(s) · '.$skipped.' já avisado(s).');

        return self::SUCCESS;
    }
}
