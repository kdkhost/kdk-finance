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

class OvhIp extends Model
{
    protected $table = 'ovh_ips';

    protected $fillable = [
        'block',
        'version',
        'type',
        'country',
        'campus',
        'rir',
        'routed_to',
        'is_additional',
        'can_be_terminated',
        'description',
        'reverse_dns',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_additional' => 'boolean',
            'can_be_terminated' => 'boolean',
            'reverse_dns' => 'array',
            'metadata' => 'array',
        ];
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'failover' => 'Failover',
            'dedicated' => 'Dedicado',
            'additional' => 'Adicional',
            'public' => 'Público',
            default => $this->type ? ucfirst($this->type) : '—',
        };
    }

    public function versionLabel(): string
    {
        return 'IPv'.$this->version;
    }

    public function primaryReverse(): ?string
    {
        $reverse = $this->reverse_dns;

        if (! is_array($reverse) || $reverse === []) {
            return null;
        }

        return (string) reset($reverse);
    }
}
