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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Restringe a rota a um ou mais perfis de acesso.
     * Uso: ->middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Acesso não autorizado.');
        }

        if (! empty($roles)) {
            $userRole = mb_strtoupper((string) $user->role);
            $normalizedRoles = array_map('mb_strtoupper', $roles);

            $isAuthorized = in_array($userRole, $normalizedRoles, true);

            // Desenvolvedor/Administrador tem acesso a todas as rotas operacionais e administrativas
            if (! $isAuthorized && $user->isDeveloperAdmin()) {
                $isAuthorized = true;
            }

            // Gestor financeiro tem acesso às rotas financeiras
            if (! $isAuthorized && in_array(\App\Models\User::ROLE_FINANCIAL, $normalizedRoles, true) && $user->canManageFinance()) {
                $isAuthorized = true;
            }

            if (! $isAuthorized) {
                abort(403, 'Seu perfil não possui permissão para esta operação.');
            }
        }

        return $next($request);
    }
}
