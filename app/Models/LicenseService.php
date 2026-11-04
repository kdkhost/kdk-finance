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

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseService extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_QUARTERLY = 'quarterly';
    public const CYCLE_SEMIANNUAL = 'semiannual';
    public const CYCLE_ANNUAL = 'annual';
    public const CYCLE_LIFETIME = 'lifetime';

    protected $fillable = [
        'provider',
        'product',
        'name',
        'server_name',
        'license_key',
        'quantity',
        'unit_price',
        'currency',
        'billing_cycle',
        'next_due_date',
        'status',
        'auto_renew',
        'notes',
        'last_invoice_id',
        'license_catalog_item_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'next_due_date' => 'date',
            'auto_renew' => 'boolean',
            'license_key' => 'encrypted',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function cycles(): array
    {
        return [
            self::CYCLE_MONTHLY => 'Mensal',
            self::CYCLE_QUARTERLY => 'Trimestral',
            self::CYCLE_SEMIANNUAL => 'Semestral',
            self::CYCLE_ANNUAL => 'Anual',
            self::CYCLE_LIFETIME => 'Vitalícia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Ativa',
            self::STATUS_EXPIRED => 'Expirada',
            self::STATUS_CANCELED => 'Cancelada',
        ];
    }

    /**
     * Produtos mais comuns da LicenseBoy (o campo aceita qualquer nome).
     *
     * @return list<string>
     */
    public static function products(): array
    {
        return [
            'cPanel',
            'DirectAdmin',
            'Plesk',
            'CloudLinux',
            'LiteSpeed',
            'Virtualizor',
            'Imunify360',
            'KernelCare',
            'Softaculous',
            'JetBackup',
            'CXS',
            'OSM',
            'SitePad',
            'WHMReseller',
            'CPNginx',
            'WHMCS',
            'SolusVM',
            'FleetSSL',
            'MailScanner',
            'cPGuard',
            'DA Reseller',
            'WHM Sonic',
            'LiteSpeed Web ADC',
            'Outro',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function lastInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'last_invoice_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(LicenseCatalogItem::class, 'license_catalog_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query->active()->whereNotNull('next_due_date')->whereDate('next_due_date', '<=', now()->addDays($days));
    }

    public function cycleMonths(): int
    {
        return match ($this->billing_cycle) {
            self::CYCLE_QUARTERLY => 3,
            self::CYCLE_SEMIANNUAL => 6,
            self::CYCLE_ANNUAL => 12,
            self::CYCLE_LIFETIME => 0,
            default => 1,
        };
    }

    public function cycleLabel(): string
    {
        return self::cycles()[$this->billing_cycle] ?? $this->billing_cycle;
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'badge-status badge-status--paid',
            self::STATUS_EXPIRED => 'badge-status badge-status--pending',
            default => 'badge-status badge-status--canceled',
        };
    }

    public function currencySymbol(): string
    {
        return config('finance.currencies.'.$this->currency.'.symbol', $this->currency);
    }

    public function totalOriginal(): float
    {
        return round((float) $this->unit_price * max(1, (int) $this->quantity), 2);
    }

    /**
     * Custo mensal equivalente na moeda original.
     */
    public function monthlyEquivalent(): float
    {
        $months = $this->cycleMonths();

        if ($months === 0) {
            return 0.0;
        }

        return round($this->totalOriginal() / $months, 2);
    }

    public function monthlyBrl(float $rate): float
    {
        return round($this->monthlyEquivalent() * $rate, 2);
    }

    public function isDueSoon(int $days = 7): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->next_due_date !== null
            && $this->next_due_date->lte(now()->addDays($days));
    }

    public function maskedKey(): string
    {
        $key = (string) $this->license_key;

        if ($key === '') {
            return '—';
        }

        return str_repeat('•', 8).mb_substr($key, -4);
    }
}
