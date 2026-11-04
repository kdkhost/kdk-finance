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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redireciona todo o painel para HTTPS quando configurado.
     *
     * Fica desligado em desenvolvimento local e em testes, onde não há
     * certificado válido. Em produção o instalador grava FORCE_HTTPS=true.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('finance.security.force_https')) {
            return $next($request);
        }

        if ($request->isSecure() || app()->environment('local', 'testing')) {
            return $next($request);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }
}
