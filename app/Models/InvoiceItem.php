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

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'external_id',
        'description',
        'domain',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function quantityLabel(): string
    {
        $quantity = (float) $this->quantity;

        return rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',') ?: '0';
    }

    public function periodLabel(): string
    {
        if (! $this->period_start && ! $this->period_end) {
            return '—';
        }

        return ($this->period_start?->format('d/m/Y') ?? '—').' a '.($this->period_end?->format('d/m/Y') ?? '—');
    }
}
