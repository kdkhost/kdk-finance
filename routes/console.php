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
| A Central de cron do painel é a fonte única dos agendamentos de negócio
| (cotações, faturas, licenças e alertas). A hospedagem executa apenas uma
| linha por minuto:
|   * * * * * php artisan schedule:run   (produção)
|   php artisan schedule:work            (local)
|
| Os crons de negócio são cadastrados em /crons e executados por cron:run-due.
| O queue:work continua sendo uma linha própria da hospedagem.
*/

Schedule::command('cron:run-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
