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

use App\Http\Middleware\DeveloperSignatureMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Redireciona para HTTPS, assina as respostas e aplica os cabeçalhos
        // de proteção do navegador (CSP com nonce, nosniff, frame, referrer).
        $middleware->append(ForceHttps::class);
        $middleware->append(DeveloperSignatureMiddleware::class);
        $middleware->append(SecurityHeaders::class);

        // Usuário desativado perde a sessão na requisição seguinte.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);

        // Opcional: aceitar apenas o host do APP_URL (contra Host header spoofing).
        if (filter_var(env('TRUST_HOSTS', false), FILTER_VALIDATE_BOOL)) {
            $middleware->trustHosts(at: function (): array {
                $host = parse_url((string) env('APP_URL'), PHP_URL_HOST);

                return array_values(array_filter([$host, $host ? 'www.'.$host : null]));
            });
        }

        // Opcional: confiar em proxy/CDN (informe os IPs em TRUSTED_PROXIES).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
