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

class DeveloperSignatureMiddleware
{
    /**
     * Injeta os cabeçalhos de autoria técnica em todas as respostas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Developer-Name', (string) config('finance.developer.name'));
        $response->headers->set('X-Developer-Company', (string) config('finance.developer.company_ascii'));
        $response->headers->set('X-Developer-CNPJ', (string) config('finance.developer.cnpj'));
        $response->headers->set('X-System-Author', (string) config('finance.developer.short_name'));

        return $response;
    }
}
