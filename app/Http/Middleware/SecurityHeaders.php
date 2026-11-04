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

use App\Support\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Aplica os cabeçalhos de proteção do navegador e a política de conteúdo.
     *
     * O que enviar é decidido pelo grupo "security" em app_settings. A CSP usa
     * um nonce por requisição: apenas os scripts da própria aplicação (arquivos
     * do Vite) e os blocos inline marcados com o nonce executam.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        View::share('cspNonce', $nonce);

        $response = $next($request);

        if (SecuritySettings::headersEnabled()) {
            $frameOptions = SecuritySettings::frameOptions();

            $headers = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => $frameOptions,
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=()',
                'X-Permitted-Cross-Domain-Policies' => 'none',
                'Cross-Origin-Opener-Policy' => 'same-origin',
                'Cross-Origin-Resource-Policy' => 'same-origin',
            ];

            foreach ($headers as $name => $value) {
                if (! $response->headers->has($name)) {
                    $response->headers->set($name, $value);
                }
            }

            if (SecuritySettings::cspEnabled()) {
                $response->headers->set('Content-Security-Policy', implode('; ', [
                    "default-src 'self'",
                    "base-uri 'self'",
                    "form-action 'self'",
                    'frame-ancestors '.($frameOptions === 'DENY' ? "'none'" : "'self'"),
                    "frame-src 'self'",
                    "object-src 'none'",
                    "img-src 'self' data: blob:",
                    "font-src 'self' data:",
                    "style-src 'self' 'unsafe-inline'",
                    "script-src 'self' 'nonce-{$nonce}'",
                    "connect-src 'self' ws: wss:",
                    "manifest-src 'self'",
                    "worker-src 'self' blob:",
                ]));
            }

            if ($request->isSecure() && SecuritySettings::hstsEnabled()) {
                $response->headers->set(
                    'Strict-Transport-Security',
                    'max-age='.(SecuritySettings::hstsMaxAge() * 86400).'; includeSubDomains'
                );
            }
        }

        // Dados financeiros não devem ficar em cache de navegador ou proxy.
        if ($request->user() && SecuritySettings::noStore()) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
