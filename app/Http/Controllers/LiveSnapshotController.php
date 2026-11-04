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

use App\Models\Invoice;
use App\Services\DashboardService;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSnapshotController extends Controller
{
    /**
     * Instantâneo leve usado pelo navegador para atualizar a tela em segundo
     * plano, sem recarregar a página nem redesenhar componentes.
     */
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        ExchangeRateService $exchange,
    ): JsonResponse {
        $ids = collect(explode(',', (string) $request->query('invoices', '')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->unique()
            ->take(60)
            ->values();

        $invoices = $ids->isEmpty() ? [] : Invoice::query()
            ->whereIn('id', $ids)
            ->get(['id', 'total_brl'])
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $invoice->id => round((float) $invoice->total_brl, 2),
            ])
            ->all();

        return response()
            ->json([
                'server_time' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
                'unread' => $request->user()->unreadNotifications()->count(),
                'rates' => $exchange->rates(),
                'eur_usd' => $exchange->pair('EUR', 'USD'),
                'totals' => $dashboard->totals(),
                'invoices' => $invoices,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
