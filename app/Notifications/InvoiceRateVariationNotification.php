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

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
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
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $direction = $this->variationPercent >= 0 ? 'alta' : 'queda';

        return (new MailMessage)
            ->subject('Variação cambial de '.number_format(abs($this->variationPercent), 2, ',', '.').'% na fatura '.$this->invoice->external_reference)
            ->greeting('Olá, '.$notifiable->name)
            ->line('A fatura "'.$this->invoice->description.'" sofreu '.$direction.' de '.number_format(abs($this->variationPercent), 2, ',', '.').'% após a atualização das cotações.')
            ->line('Valor anterior: R$ '.number_format($this->previousTotal, 2, ',', '.'))
            ->line('Valor atualizado: R$ '.number_format($this->newTotal, 2, ',', '.'))
            ->action('Abrir fatura', route('invoices.show', $this->invoice))
            ->line('Este alerta foi gerado automaticamente pelo monitoramento de câmbio.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(object $notifiable): array
    {
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
