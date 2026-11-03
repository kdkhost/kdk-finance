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

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Exibe uma frase inspiradora');

/*
|--------------------------------------------------------------------------
| Agendamentos
|--------------------------------------------------------------------------
| Atualiza cotações, importa faturas da OVHcloud e recalcula os valores
| pendentes a cada 15 minutos. Requer o scheduler ativo no servidor:
|   php artisan schedule:work   (local)
|   * * * * * php artisan schedule:run   (produção)
*/
Schedule::command('finance:sync-invoices')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
