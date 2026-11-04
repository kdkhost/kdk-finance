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

/**
 * A pasta public/ nunca aparece na URL.
 *
 * No Apache o .htaccess da raiz já resolve isso antes do PHP. Este middleware
 * cobre os servidores que entregam o caminho original ao Laravel (NGINX,
 * proxy reverso, servidor embutido do PHP): qualquer requisição cujo caminho
 * comece com "public/" é redirecionada para a URL limpa, mantendo o restante
 * do caminho e a query string.
 */
class HidePublicSegment
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim(str_replace('\\', '/', $request->path()), '/');

        if ($path === 'public' || str_starts_with($path, 'public/')) {
            $remainder = ltrim(substr($path, strlen('public')), '/');
            $target = '/'.preg_replace('#/+#', '/', $remainder);

            return redirect()->to($target.$this->queryString($request), 301);
        }

        return $next($request);
    }

    private function queryString(Request $request): string
    {
        $query = $request->getQueryString();

        return is_string($query) && $query !== '' ? '?'.$query : '';
    }
}
