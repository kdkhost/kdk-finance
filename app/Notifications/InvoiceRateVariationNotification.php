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
use App\Services\MailTemplateService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InvoiceRateVariationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public float $variationPercent,
        public float $previousTotal,
        public float $newTotal,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if (app(MailTemplateService::class)->template('invoice_rate')?->enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): ThemedMail
    {
        $direction = $this->variationPercent >= 0 ? 'alta' : 'queda';
        $variation = number_format(abs($this->variationPercent), 2, ',', '.').'%';
        $payload = app(MailTemplateService::class)->payload('invoice_rate', [
            'usuario' => (string) $notifiable->name,
            'descricao' => (string) $this->invoice->description,
            'fatura' => (string) ($this->invoice->external_reference ?: $this->invoice->description),
            'direcao' => $direction,
            'variacao' => $variation,
            'variacao_sinal' => ($this->variationPercent >= 0 ? '+' : '−').$variation,
            'valor_anterior' => 'R$ '.number_format($this->previousTotal, 2, ',', '.'),
            'valor_atual' => 'R$ '.number_format($this->newTotal, 2, ',', '.'),
            'link' => route('invoices.show', $this->invoice),
        ]) ?? [
            'subject' => 'Variação cambial na fatura '.$this->invoice->description,
            'heading' => 'Variação cambial na fatura',
            'intro' => 'A fatura '.$this->invoice->description.' variou '.$variation.' e agora totaliza R$ '.number_format($this->newTotal, 2, ',', '.').'.',
            'details' => [],
            'action_text' => 'Abrir fatura',
            'action_url' => route('invoices.show', $this->invoice),
            'footnote' => '',
            'preheader' => 'Variação cambial detectada.',
        ];

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
        return [
            'title' => 'Variação cambial detectada',
            'message' => 'A fatura '.$this->invoice->description.' variou '.number_format($this->variationPercent, 2, ',', '.').'% e agora totaliza R$ '.number_format($this->newTotal, 2, ',', '.').'.',
            'invoice_id' => $this->invoice->id,
            'invoice_reference' => $this->invoice->external_reference,
            'variation_percent' => round($this->variationPercent, 2),
            'previous_total_brl' => round($this->previousTotal, 2),
            'new_total_brl' => round($this->newTotal, 2),
            'url' => route('invoices.show', $this->invoice),
            'icon' => $this->variationPercent >= 0 ? 'trending-up' : 'trending-down',
        ];
    }
}
