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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_DEVELOPER = 'DEVELOPER_ADMIN';
    public const ROLE_FINANCIAL = 'FINANCIAL_MANAGER';
    public const ROLE_VIEWER = 'VIEWER';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public static function roles(): array
    {
        return [
            self::ROLE_DEVELOPER => 'Desenvolvedor / Administrador',
            self::ROLE_FINANCIAL => 'Gestor financeiro',
            self::ROLE_VIEWER => 'Consulta (somente leitura)',
        ];
    }

    public function roleLabel(): string
    {
        return self::roles()[$this->role] ?? $this->role;
    }

    public function isDeveloperAdmin(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    public function canManageFinance(): bool
    {
        return in_array($this->role, [self::ROLE_DEVELOPER, self::ROLE_FINANCIAL], true);
    }

    public function canManageSettings(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? (string) end($parts) : '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }
}
