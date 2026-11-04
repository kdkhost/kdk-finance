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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia Host header spoofing comparando o host da requisição com a lista
 * aprovada no painel. Aceita curingas no formato *.seudominio.com.
 */
class RestrictHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SecuritySettings::trustHosts()) {
            return $next($request);
        }

        $allowed = SecuritySettings::allowedHosts();

        if ($allowed === []) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        foreach ($allowed as $pattern) {
            if ($host === $pattern || Str::is($pattern, $host)) {
                return $next($request);
            }
        }

        abort(400, 'Host não permitido.');
    }
}
