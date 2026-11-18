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

namespace App\Notifications;

use App\Mail\ThemedMail;
use App\Models\Invoice;
use App\Services\InvoiceMailDataService;
use App\Services\MailTemplateService;
use App\Support\Settings\MailSettings;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação unificada do ciclo de vida das faturas e serviços:
 *  - created    : na criação/emissão da fatura
 *  - before_due : dias configurados antes do vencimento
 *  - due_today  : no dia do vencimento
 *  - suspension : atraso crítico / risco de suspensão do serviço
 */
class InvoiceLifecycleNotification extends Notification
{
    use Queueable;

    public const STAGE_CREATED = 'created';
    public const STAGE_BEFORE_DUE = 'before_due';
    public const STAGE_DUE_TODAY = 'due_today';
    public const STAGE_SUSPENSION = 'suspension';

    public function __construct(
        public Invoice $invoice,
        public string $stage,
        public int $days = 0,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (MailSettings::configured()) {
            $templateKey = $this->mailTemplateKey();
            $template = app(MailTemplateService::class)->template($templateKey);
            if ($template === null || $template->enabled) {
                $channels[] = 'mail';
            }
        }

        return $channels;
    }

    public function toMail($notifiable): ThemedMail
    {
        $templateKey = $this->mailTemplateKey();
        $extra = [
            'usuario' => (string) ($notifiable->name ?? 'equipe financeira'),
            'dias' => (string) max(0, $this->days),
            'dias_atraso' => (string) max(1, abs($this->days)),
        ];

        $variables = app(InvoiceMailDataService::class)->variables($this->invoice, $extra);
        $payload = app(MailTemplateService::class)->payload($templateKey, $variables);

        if ($payload === null) {
            $meta = $this->payload();
            $payload = [
                'subject' => $meta['title'].' · '.$this->invoice->description,
                'heading' => $meta['title'],
                'intro' => $meta['message'],
                'details' => [
                    'Fatura' => (string) ($this->invoice->external_reference ?: $this->invoice->description),
                    'Vencimento' => $this->invoice->due_date?->format('d/m/Y') ?? '—',
                    'Total a reservar' => 'R$ '.number_format((float) $this->invoice->total_brl, 2, ',', '.'),
                ],
                'action_text' => 'Abrir fatura no painel',
                'action_url' => route('invoices.show', $this->invoice),
                'footnote' => 'Alerta automático do sistema financeiro.',
                'preheader' => $meta['title'],
            ];
        } elseif ($this->stage === self::STAGE_CREATED) {
            $payload['subject'] = 'Nova fatura emitida: '.($this->invoice->external_reference ?: $this->invoice->description).' · R$ '.number_format((float) $this->invoice->total_brl, 2, ',', '.');
            $payload['heading'] = 'Nova fatura registrada no painel';
            $payload['intro'] = 'Olá, '.($notifiable->name ?? 'equipe financeira').'. A fatura "'.$this->invoice->description.'" foi registrada com vencimento em '.($this->invoice->due_date?->format('d/m/Y') ?? '—').'. Confira abaixo o valor convertido e os tributos para programação financeira.';
        } elseif ($this->stage === self::STAGE_DUE_TODAY) {
            $payload['subject'] = 'VENCE HOJE: Fatura '.($this->invoice->external_reference ?: $this->invoice->description).' · R$ '.number_format((float) $this->invoice->total_brl, 2, ',', '.');
            $payload['heading'] = 'Fatura vence hoje ('.now()->format('d/m/Y').')';
        } elseif ($this->stage === self::STAGE_SUSPENSION) {
            $payload['subject'] = 'ALERTA DE SUSPENSÃO: Fatura '.($this->invoice->external_reference ?: $this->invoice->description).' vencida há '.abs($this->days).' dia(s)';
            $payload['heading'] = 'Risco de suspensão de serviço por fatura em aberto';
        }

        return new ThemedMail(
            heading: $payload['heading'],
            intro: $payload['intro'],
            details: $payload['details'],
            actionText: $payload['action_text'],
            actionUrl: $payload['action_url'],
            footnote: $payload['footnote'],
            preheader: $payload['preheader'],
            subject: $payload['subject'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn($notifiable = null): array
    {
        if ($notifiable === null) {
            return [];
        }

        return [new PrivateChannel('App.Models.User.'.$notifiable->id)];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $ref = $this->invoice->external_reference ?: ('#'.$this->invoice->id);
        $brl = 'R$ '.number_format((float) $this->invoice->total_brl, 2, ',', '.');
        $due = $this->invoice->due_date?->format('d/m/Y') ?? '—';

        [$title, $message, $icon] = match ($this->stage) {
            self::STAGE_CREATED => [
                'Nova fatura registrada ('.$ref.')',
                'A fatura "'.$this->invoice->description.'" foi criada com vencimento em '.$due.' no total de '.$brl.'.',
                'receipt',
            ],
            self::STAGE_BEFORE_DUE => [
                'Fatura vence em '.$this->days.' dia(s) ('.$ref.')',
                'A fatura "'.$this->invoice->description.'" vence em '.$due.' (faltam '.$this->days.' dia(s)) · Total: '.$brl.'.',
                'alarm',
            ],
            self::STAGE_DUE_TODAY => [
                'Fatura vence HOJE ('.$ref.')',
                'Atenção: a fatura "'.$this->invoice->description.'" vence hoje ('.$due.') · Total a pagar/reservar: '.$brl.'.',
                'exclamation',
            ],
            default => [
                'Risco de suspensão · Fatura vencida ('.$ref.')',
                'Urgente: a fatura "'.$this->invoice->description.'" venceu em '.$due.' (há '.abs($this->days).' dia(s)) · Total: '.$brl.'. Regularize para evitar suspensão do serviço.',
                'suspension',
            ],
        };

        return [
            'title' => $title,
            'message' => $message,
            'stage' => $this->stage,
            'days' => $this->days,
            'invoice_id' => $this->invoice->id,
            'invoice_reference' => $this->invoice->external_reference,
            'new_total_brl' => round((float) $this->invoice->total_brl, 2),
            'url' => route('invoices.show', $this->invoice),
            'icon' => $icon,
        ];
    }

    private function mailTemplateKey(): string
    {
        return match ($this->stage) {
            self::STAGE_SUSPENSION => 'invoice_overdue',
            default => 'invoice_due',
        };
    }
}
