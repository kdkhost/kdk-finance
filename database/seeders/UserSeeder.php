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

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\ReadsLocalCredentials;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    use ReadsLocalCredentials;

    public function run(): void
    {
        $profiles = [
            'developer' => [
                'role' => User::ROLE_DEVELOPER,
                'name' => 'Marcelo Brad - RJ',
                'email' => 'george@kdkhost.com.br',
            ],
            'financial' => [
                'role' => User::ROLE_FINANCIAL,
                'name' => 'Equipe Financeira KDKHost',
                'email' => 'financeiro@kdkhost.com.br',
            ],
            'viewer' => [
                'role' => User::ROLE_VIEWER,
                'name' => 'Consulta KDKHost',
                'email' => 'consulta@kdkhost.com.br',
            ],
        ];

        $localUsers = $this->localCredentials()['users'] ?? [];
        $report = [];

        foreach ($profiles as $key => $profile) {
            $local = $localUsers[$key] ?? [];
            $email = $local['email'] ?? $profile['email'];
            $password = $local['password'] ?? '';

            $user = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;

            $user->fill([
                'name' => $local['name'] ?? $profile['name'],
                'role' => $profile['role'],
                'is_active' => true,
            ]);

            if ($isNew || $password !== '') {
                $user->password = $password !== '' ? $password : Str::password(16);
            }

            $user->save();

            $report[] = [
                $user->email,
                $user->roleLabel(),
                $isNew ? ($password !== '' ? $password : '(senha aleatória — redefina no painel)') : 'senha preservada',
            ];
        }

        $this->command?->info('Usuários garantidos:');
        $this->command?->table(['E-mail', 'Perfil', 'Senha'], $report);
    }
}
