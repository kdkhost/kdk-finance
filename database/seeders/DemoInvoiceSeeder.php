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

namespace Database\Seeders;

use App\Models\Invoice;
use App\Services\ExchangeRateService;
use App\Services\TaxCalculationService;
use Illuminate\Database\Seeder;

class DemoInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $rates = app(ExchangeRateService::class)->rates();
        $tax = app(TaxCalculationService::class);

        $invoices = [
            [
                'external_reference' => 'DEMO-OVH-1001',
                'description' => 'OVHcloud · Servidor dedicado Nano RJ',
                'currency' => 'EUR',
                'amount_original' => 43.96,
                'due_date' => now()->addDays(5)->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ],
            [
                'external_reference' => 'DEMO-OVH-1002',
                'description' => 'OVHcloud · VPS Value 2 vCore',
                'currency' => 'EUR',
                'amount_original' => 12.99,
                'due_date' => now()->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ],
            [
                'external_reference' => 'DEMO-OVH-1003',
                'description' => 'Licença cPanel Solo Cloud',
                'currency' => 'USD',
                'amount_original' => 29.90,
                'due_date' => now()->subDays(3)->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ],
            [
                'external_reference' => 'DEMO-OVH-1004',
                'description' => 'OVHcloud · Servidor dedicado (setembro)',
                'currency' => 'EUR',
                'amount_original' => 43.96,
                'due_date' => now()->subMonth()->toDateString(),
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now()->subMonth()->addDay(),
            ],
            [
                'external_reference' => 'DEMO-OVH-1005',
                'description' => 'Licença Windows Server 2025 Standard',
                'currency' => 'USD',
                'amount_original' => 89.00,
                'due_date' => now()->addDays(12)->toDateString(),
                'status' => Invoice::STATUS_PENDING,
            ],
            [
                'external_reference' => 'DEMO-OVH-1006',
                'description' => 'OVHcloud · Serviço cancelado',
                'currency' => 'EUR',
                'amount_original' => 5.99,
                'due_date' => now()->subDays(10)->toDateString(),
                'status' => Invoice::STATUS_CANCELED,
            ],
        ];

        foreach ($invoices as $data) {
            $invoice = Invoice::query()->firstOrCreate(
                ['external_reference' => $data['external_reference']],
                $data
            );

            if (! $invoice->wasRecentlyCreated) {
                continue;
            }

            $rate = (float) ($rates[$invoice->currency]['rate'] ?? 0);

            if ($rate > 0) {
                $tax->applyToInvoice($invoice, $rate);
            }
        }

        $this->command?->info('Faturas de demonstração criadas para o ambiente local.');
    }
}
