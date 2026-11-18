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

use App\Support\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redireciona todo o painel para HTTPS quando a opção está ligada no banco.
     *
     * Fica desligado em desenvolvimento local e em testes, onde não há
     * certificado válido. O instalador grava a preferência em app_settings.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! SecuritySettings::forceHttps()) {
            return $next($request);
        }

        if ($request->isSecure() || app()->environment('local', 'testing')) {
            return $next($request);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }
}
