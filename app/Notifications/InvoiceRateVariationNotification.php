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
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): ThemedMail
    {
        $direction = $this->variationPercent >= 0 ? 'alta' : 'queda';
        $variation = number_format(abs($this->variationPercent), 2, ',', '.').'%';

        return (new ThemedMail(
            heading: 'Variação cambial na fatura',
            intro: 'Olá, '.$notifiable->name.'. A fatura "'.$this->invoice->description.'" sofreu '.$direction.' de '.$variation.' após a atualização das cotações.',
            details: [
                'Fatura' => $this->invoice->external_reference,
                'Variação' => ($this->variationPercent >= 0 ? '+' : '−').$variation,
                'Valor anterior' => 'R$ '.number_format($this->previousTotal, 2, ',', '.'),
                'Valor atualizado' => 'R$ '.number_format($this->newTotal, 2, ',', '.'),
            ],
            actionText: 'Abrir fatura',
            actionUrl: route('invoices.show', $this->invoice),
            footnote: 'Este alerta foi gerado automaticamente pelo monitoramento de câmbio.',
            preheader: 'A fatura '.$this->invoice->external_reference.' variou '.$variation.'.',
            subject: 'Variação cambial de '.$variation.' na fatura '.$this->invoice->external_reference,
        ));
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
