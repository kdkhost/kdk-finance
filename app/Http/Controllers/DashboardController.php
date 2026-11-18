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

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\LicenseService;
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
        $usdRate = (float) ($rates['USD']['rate'] ?? 0);
        $dueWindowDays = max(1, (int) AppSetting::get('notify_days_before_due', 7));

        $upcomingInvoices = Invoice::query()
            ->pending()
            ->whereDate('due_date', '<=', now()->addDays(max(15, $dueWindowDays))->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $licenseServices = LicenseService::query()
            ->with(['lastInvoice', 'catalogItem'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END")
            ->orderBy('next_due_date')
            ->get()
            ->map(function (LicenseService $service) use ($eurRate, $usdRate): array {
                $rate = $service->billingCurrency() === 'EUR' ? $eurRate : $usdRate;

                return [
                    'service' => $service,
                    'rate' => $rate,
                    'monthly_brl' => $service->monthlyBrl($rate),
                ];
            });

        return view('dashboard', [
            'totals' => $dashboard->totals(),
            'series' => $dashboard->monthlySeries(),
            'statusDistribution' => $dashboard->statusDistribution(),
            'rates' => $rates,
            'nextDueDate' => $dashboard->nextDueDate(),
            'eurRate' => $eurRate,
            'usdRate' => $usdRate,
            'services' => $inventory->pricedServices($eurRate),
            'licenseServices' => $licenseServices,
            'upcomingInvoices' => $upcomingInvoices,
            'dueWindowDays' => $dueWindowDays,
            'ips' => OvhIp::query()->orderBy('version')->orderBy('block')->get(),
            'infrastructure' => $inventory->totals($eurRate),
            'recentInvoices' => Invoice::query()
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'paid' THEN 1 ELSE 2 END")
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'overdueInvoices' => Invoice::query()->overdue()->orderBy('due_date')->limit(5)->get(),
            'unreadNotifications' => auth()->user()?->unreadNotifications()->limit(6)->get() ?? collect(),
        ]);
    }
}
