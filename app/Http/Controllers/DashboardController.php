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

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\OvhIp;
use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use App\Services\OvhInventoryService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        DashboardService $dashboard,
        ExchangeRateService $exchange,
        OvhInventoryService $inventory,
    ): View {
        $rates = $exchange->rates();
        $eurRate = (float) ($rates['EUR']['rate'] ?? 0);

        return view('dashboard', [
            'totals' => $dashboard->totals(),
            'series' => $dashboard->monthlySeries(),
            'statusDistribution' => $dashboard->statusDistribution(),
            'rates' => $rates,
            'nextDueDate' => $dashboard->nextDueDate(),
            'eurRate' => $eurRate,
            'services' => $inventory->pricedServices($eurRate),
            'ips' => OvhIp::query()->orderBy('version')->orderBy('block')->get(),
            'infrastructure' => $inventory->totals($eurRate),
            'recentInvoices' => Invoice::query()
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'paid' THEN 1 ELSE 2 END")
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'overdueInvoices' => Invoice::query()->overdue()->orderBy('due_date')->limit(5)->get(),
            'unreadNotifications' => auth()->user()->unreadNotifications()->limit(6)->get(),
        ]);
    }
}
