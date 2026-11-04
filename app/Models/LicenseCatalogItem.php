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
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseCatalogItem extends Model
{
    protected $fillable = [
        'provider',
        'external_id',
        'category',
        'category_name',
        'name',
        'slug',
        'price',
        'price_is_from',
        'currency',
        'billing_cycle',
        'setup_fee',
        'stock',
        'url',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_is_from' => 'boolean',
            'setup_fee' => 'decimal:2',
            'stock' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(LicenseService::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull('stock')
            ->orWhere('stock', '>', 0));
    }

    public function isAvailable(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function availabilityLabel(): string
    {
        if (! $this->isAvailable()) {
            return 'Esgotado';
        }

        return $this->stock === null ? 'Disponível' : $this->stock.' em estoque';
    }

    public function cycleLabel(): string
    {
        return LicenseService::cycles()[$this->billing_cycle] ?? 'Sob consulta';
    }

    /**
     * Nome curto do produto (cPanel, LiteSpeed, WHMCS…) a partir do nome da loja.
     */
    public function productLabel(): string
    {
        $candidates = collect(LicenseService::products())
            ->reject(fn (string $product): bool => $product === 'Outro')
            ->sortByDesc(fn (string $product): int => mb_strlen($product));

        foreach ($candidates as $product) {
            if (mb_stripos($this->name, $product) !== false) {
                return $product;
            }
        }

        return mb_substr($this->name, 0, 80);
    }

    public function currencySymbol(): string
    {
        return config('finance.currencies.'.$this->currency.'.symbol', $this->currency);
    }

    public function priceLabel(): string
    {
        if ((float) $this->price <= 0) {
            return 'Grátis';
        }

        return $this->currencySymbol().' '.number_format((float) $this->price, 2, ',', '.');
    }
}
