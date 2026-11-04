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

namespace App\Providers;

use App\Support\Settings\MailSettings;
use App\Support\Settings\SecuritySettings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Carbon::setLocale('pt_BR');
        setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil');

        // Em produção, nunca expõe stack trace nem caminhos do servidor.
        if (app()->isProduction() && config('app.debug')) {
            config(['app.debug' => false]);
            Log::warning('APP_DEBUG estava ligado em produção e foi desativado em tempo de execução.');
        }

        // Segurança, sessão, política de senha e SMTP vêm do banco (app_settings).
        // O .env permanece apenas como último recurso de infraestrutura.
        try {
            SecuritySettings::applySessionConfig();
        } catch (Throwable $exception) {
            SecuritySettings::logBootFailure($exception);
        }

        try {
            SecuritySettings::applyPasswordPolicy();
        } catch (Throwable $exception) {
            SecuritySettings::logBootFailure($exception);
        }

        try {
            MailSettings::apply();
        } catch (Throwable $exception) {
            MailSettings::logBootFailure($exception);
        }
    }
}
