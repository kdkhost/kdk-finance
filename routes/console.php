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

/*
| Central de cron do painel: o único agendamento da hospedagem é o
| `php artisan schedule:run`. Este comando verifica a cada minuto quais
| crons cadastrados em /crons estão vencidos e executa cada um.
*/
Schedule::command('cron:run-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

/*
| Tabela de preços da LicenseBoy: a loja pública não oferece API, então o
| catálogo é lido diariamente e as licenças vinculadas recebem o valor atual.
*/
Schedule::command('licenses:sync-prices')
    ->dailyAt('05:10')
    ->withoutOverlapping()
    ->onOneServer();

/*
| Painel da LicenseBoy: o valor recorrente do cliente só existe dentro do
| painel. A leitura diária mantém o custo real das licenças (que prevalece
| sobre o preço da loja) e sinaliza divergências.
*/
Schedule::command('licenses:sync-panel')
    ->dailyAt('05:25')
    ->withoutOverlapping()
    ->onOneServer();
