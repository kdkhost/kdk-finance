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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'external_reference',
        'external_url',
        'description',
        'currency',
        'amount_original',
        'exchange_rate',
        'spread_amount',
        'iof_amount',
        'icms_amount',
        'other_taxes_amount',
        'total_brl',
        'status',
        'due_date',
        'paid_at',
        'rate_locked',
        'calculated_at',
        'license_service_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_original' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'spread_amount' => 'decimal:2',
            'iof_amount' => 'decimal:2',
            'icms_amount' => 'decimal:2',
            'other_taxes_amount' => 'decimal:2',
            'total_brl' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'rate_locked' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function licenseService(): BelongsTo
    {
        return $this->belongsTo(LicenseService::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_PAID => 'Paga',
            self::STATUS_CANCELED => 'Cancelada',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->whereDate('due_date', '<', now()->toDateString());
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'badge-status badge-status--paid',
            self::STATUS_CANCELED => 'badge-status badge-status--canceled',
            default => 'badge-status badge-status--pending',
        };
    }

    public function currencySymbol(): string
    {
        return config('finance.currencies.'.$this->currency.'.symbol', $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_date?->isPast();
    }

    public function taxesTotal(): float
    {
        return round(
            (float) $this->spread_amount
            + (float) $this->iof_amount
            + (float) $this->icms_amount
            + (float) $this->other_taxes_amount,
            2
        );
    }

    public function toLivePayload(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'currency' => $this->currency,
            'amount_original' => (float) $this->amount_original,
            'exchange_rate' => (float) $this->exchange_rate,
            'total_brl' => (float) $this->total_brl,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'due_date' => $this->due_date?->format('d/m/Y'),
            'rate_locked' => $this->rate_locked,
            'variation_percent' => null,
        ];
    }
}
