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

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class DashboardService
{
    /**
     * @return array<string, float|int>
     */
    public function totals(): array
    {
        $monthStart = now()->startOfMonth();

        return [
            'pending_total' => round((float) Invoice::query()->pending()->sum('total_brl'), 2),
            'pending_count' => Invoice::query()->pending()->count(),
            'paid_month_total' => round(
                (float) Invoice::query()->paid()->where('paid_at', '>=', $monthStart)->sum('total_brl'),
                2
            ),
            'paid_month_count' => Invoice::query()->paid()->where('paid_at', '>=', $monthStart)->count(),
            'overdue_count' => Invoice::query()->overdue()->count(),
            'overdue_total' => round((float) Invoice::query()->overdue()->sum('total_brl'), 2),
            'taxes_pending' => round(
                (float) Invoice::query()->pending()
                    ->selectRaw('COALESCE(SUM(spread_amount + iof_amount + icms_amount + other_taxes_amount), 0) as total')
                    ->value('total'),
                2
            ),
        ];
    }

    /**
     * Série dos últimos 6 meses para o gráfico do dashboard.
     *
     * @return array{labels: array<int, string>, paid: array<int, float>, pending: array<int, float>}
     */
    public function monthlySeries(int $months = 6): array
    {
        $labels = [];
        $paid = [];
        $pending = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = now()->startOfMonth()->subMonths($offset);
            $end = (clone $start)->endOfMonth();

            $labels[] = ucfirst($start->translatedFormat('M/y'));
            $paid[] = round(
                (float) Invoice::query()->paid()
                    ->whereBetween('paid_at', [$start, $end])
                    ->sum('total_brl'),
                2
            );
            $pending[] = round(
                (float) Invoice::query()->pending()
                    ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('total_brl'),
                2
            );
        }

        return [
            'labels' => $labels,
            'paid' => $paid,
            'pending' => $pending,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusDistribution(): array
    {
        return [
            'pending' => Invoice::query()->pending()->count(),
            'paid' => Invoice::query()->paid()->count(),
            'canceled' => Invoice::query()->where('status', Invoice::STATUS_CANCELED)->count(),
        ];
    }

    public function nextDueDate(): ?Carbon
    {
        $date = Invoice::query()->pending()->min('due_date');

        return $date ? Carbon::parse($date) : null;
    }
}
