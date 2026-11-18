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

/**
 * Confia em proxy/CDN configurado no painel para recuperar o IP real do
 * visitante e o esquema HTTPS. Roda no início da pilha, antes do ForceHttps.
 */
class ConfigureTrustedProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        $proxies = SecuritySettings::trustedProxies();

        if ($proxies !== []) {
            $request->setTrustedProxies($proxies, Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT);
        }

        return $next($request);
    }
}
