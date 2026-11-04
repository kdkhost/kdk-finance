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
        'panel_service_id',
        'panel_price',
        'panel_currency',
        'panel_billing_cycle',
        'panel_next_due_date',
        'panel_status',
        'panel_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'panel_price' => 'decimal:2',
            'next_due_date' => 'date',
            'panel_next_due_date' => 'date',
            'panel_synced_at' => 'datetime',
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
        return self::monthsForCycle($this->billing_cycle);
    }

    /**
     * Meses de um ciclo informado, incluindo o ciclo lido do painel.
     */
    public static function monthsForCycle(?string $cycle): int
    {
        return match ($cycle) {
            self::CYCLE_QUARTERLY => 3,
            self::CYCLE_SEMIANNUAL => 6,
            self::CYCLE_ANNUAL => 12,
            self::CYCLE_LIFETIME => 0,
            default => 1,
        };
    }

    /**
     * Ciclo que define a cobrança: o do painel quando há valor do painel.
     */
    public function billingCycle(): string
    {
        if ($this->usesPanelPrice() && $this->panel_billing_cycle) {
            return $this->panel_billing_cycle;
        }

        return $this->billing_cycle;
    }

    public function billingCycleMonths(): int
    {
        return self::monthsForCycle($this->billingCycle());
    }

    public function billingCycleLabel(): string
    {
        return self::cycles()[$this->billingCycle()] ?? $this->billingCycle();
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

    public function billingCurrencySymbol(): string
    {
        return config('finance.currencies.'.$this->billingCurrency().'.symbol', $this->billingCurrency());
    }

    /**
     * Preço realmente cobrado: o painel da LicenseBoy prevalece sobre a loja.
     */
    public function billingUnitPrice(): float
    {
        $panel = $this->panel_price;

        if ($panel !== null && (float) $panel > 0) {
            return (float) $panel;
        }

        return (float) $this->unit_price;
    }

    public function usesPanelPrice(): bool
    {
        return $this->panel_price !== null && (float) $this->panel_price > 0;
    }

    public function billingCurrency(): string
    {
        return $this->panel_currency ?: $this->currency;
    }

    /**
     * Diferença percentual entre o valor do painel e o preço atual da loja.
     * Positivo = o painel cobra mais caro que a loja.
     */
    public function panelDivergencePercent(): ?float
    {
        if (! $this->usesPanelPrice() || ! $this->catalogItem) {
            return null;
        }

        $store = (float) $this->catalogItem->price;

        if ($store <= 0) {
            return null;
        }

        return round((($this->billingUnitPrice() - $store) / $store) * 100, 2);
    }

    public function panelDifference(): ?float
    {
        if (! $this->usesPanelPrice() || ! $this->catalogItem) {
            return null;
        }

        return round($this->billingUnitPrice() - (float) $this->catalogItem->price, 2);
    }

    public function totalOriginal(): float
    {
        return round($this->billingUnitPrice() * max(1, (int) $this->quantity), 2);
    }

    public function storeMonthlyEquivalent(): float
    {
        $months = $this->cycleMonths();

        if ($months === 0) {
            return 0.0;
        }

        return round(((float) $this->unit_price * max(1, (int) $this->quantity)) / $months, 2);
    }

    /**
     * Custo mensal equivalente na moeda original.
     */
    public function monthlyEquivalent(): float
    {
        $months = $this->billingCycleMonths();

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
