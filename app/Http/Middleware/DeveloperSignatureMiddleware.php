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

class DeveloperSignatureMiddleware
{
    /**
     * Injeta os cabeçalhos de autoria técnica em todas as respostas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Developer-Name', 'George Marcelo de Oliveira Pimentel');
        $response->headers->set('X-Developer-Company', 'KDKHost Solucoes');
        $response->headers->set('X-Developer-CNPJ', '33.986.975/0001-90');
        $response->headers->set('X-System-Author', 'George Marcelo');

        return $response;
    }
}
