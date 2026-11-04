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

use Illuminate\Database\Eloquent\Model;

class OvhServiceItem extends Model
{
    protected $table = 'ovh_services';

    protected $fillable = [
        'service_id',
        'name',
        'display_name',
        'type',
        'route_path',
        'route_url',
        'state',
        'quantity',
        'renew_mode',
        'renew_interval',
        'creation_date',
        'expiration_date',
        'next_billing_date',
        'monthly_price_eur',
        'billing_domain',
        'last_bill_id',
        'last_billed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'creation_date' => 'date',
            'expiration_date' => 'date',
            'next_billing_date' => 'date',
            'last_billed_at' => 'date',
            'monthly_price_eur' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function label(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function typeLabel(): string
    {
        $type = (string) $this->type;

        return match (true) {
            str_contains($type, 'dedicated') => 'Servidor dedicado',
            str_contains($type, 'cloud') => 'Public Cloud',
            str_contains($type, 'vrack') => 'vRack',
            str_contains($type, 'ip') => 'Endereço IP',
            $type === '' => 'Serviço',
            default => ucfirst($type),
        };
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            'ok', 'active' => 'Ativo',
            'suspended' => 'Suspenso',
            'expired' => 'Expirado',
            null, '' => '—',
            default => ucfirst((string) $this->state),
        };
    }

    public function renewalLabel(): string
    {
        $date = $this->next_billing_date ?? $this->expiration_date;

        return $date?->format('d/m/Y') ?? '—';
    }

    public function priceLabel(string $currency = 'EUR'): string
    {
        if ($this->monthly_price_eur === null) {
            return '—';
        }

        $symbol = $currency === 'BRL' ? 'R$' : '€';

        return $symbol.' '.number_format((float) $this->monthly_price_eur, 2, ',', '.');
    }
}
