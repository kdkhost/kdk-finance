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

/**
 * Uma leitura de tarifa feita pelos adaptadores (Visa, Mastercard, BCB,
 * PayPal). Guarda o valor observado e o motivo quando a fonte não respondeu,
 * para a tela de configurações mostrar a procedência de cada número.
 */
class FeeSyncRun extends Model
{
    public const STATUS_OK = 'ok';
    public const STATUS_UNAVAILABLE = 'indisponivel';
    public const STATUS_SKIPPED = 'ignorado';

    public $timestamps = false;

    protected $fillable = [
        'source',
        'status',
        'value',
        'note',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
