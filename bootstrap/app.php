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

use App\Http\Middleware\ConfigureTrustedProxies;
use App\Http\Middleware\DeveloperSignatureMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HidePublicSegment;
use App\Http\Middleware\RestrictHosts;
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
        // Proxy/CDN e validação de Host vêm primeiro, ambos configurados no
        // painel (app_settings), sem depender do .env.
        $middleware->prepend([
            ConfigureTrustedProxies::class,
            RestrictHosts::class,
        ]);

        // Redireciona para HTTPS, assina as respostas e aplica os cabeçalhos
        // de proteção do navegador (CSP com nonce, nosniff, frame, referrer).
        $middleware->append(ForceHttps::class);
        $middleware->append(DeveloperSignatureMiddleware::class);
        $middleware->append(SecurityHeaders::class);

        // A pasta public/ nunca aparece na URL; cobre NGINX e proxies que não
        // leem o .htaccess do Apache.
        $middleware->append(HidePublicSegment::class);

        // Usuário desativado perde a sessão na requisição seguinte.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
