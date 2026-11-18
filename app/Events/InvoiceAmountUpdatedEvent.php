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

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceAmountUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $totals
     */
    public function __construct(
        public Invoice $invoice,
        public float $previousTotal,
        public float $variationPercent,
        public array $totals = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('invoices')];
    }

    public function broadcastAs(): string
    {
        return 'invoice.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'invoice' => array_merge($this->invoice->toLivePayload(), [
                'variation_percent' => round($this->variationPercent, 2),
                'previous_total_brl' => round($this->previousTotal, 2),
            ]),
            'totals' => $this->totals,
            'alert' => abs($this->variationPercent) >= (float) config('finance.variation_threshold_percent', 1),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
