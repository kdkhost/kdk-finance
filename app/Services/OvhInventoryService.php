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

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OvhIp;
use App\Models\OvhServiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class OvhInventoryService
{
    /**
     * Sincroniza todos os serviços contratados.
     */
    public function syncServices(OvhService $ovh): int
    {
        $services = $ovh->getActiveServices();
        $count = 0;

        foreach ($services as $service) {
            $item = OvhServiceItem::query()->updateOrCreate(
                ['service_id' => $service['service_id']],
                [
                    'name' => $service['name'],
                    'display_name' => $service['display_name'],
                    'type' => $service['type'],
                    'route_path' => $service['route_path'],
                    'route_url' => $service['route_url'],
                    'state' => $service['state'],
                    'quantity' => $service['quantity'],
                    'renew_mode' => $service['renew_mode'],
                    'renew_interval' => $service['renew_interval'],
                    'creation_date' => $this->date($service['creation_date']),
                    'expiration_date' => $this->date($service['expiration_date']),
                    'next_billing_date' => $this->date($service['next_billing_date']),
                    'metadata' => $service['metadata'] ?? null,
                ]
            );

            $count += $item->wasRecentlyCreated || $item->wasChanged() ? 1 : 0;
        }

        return $count;
    }

    /**
     * Sincroniza os blocos de IP e o DNS reverso.
     */
    public function syncIps(OvhService $ovh): int
    {
        $ips = $ovh->getIps();
        $count = 0;

        foreach ($ips as $ip) {
            $item = OvhIp::query()->updateOrCreate(
                ['block' => $ip['block']],
                [
                    'version' => $ip['version'],
                    'type' => $ip['type'],
                    'country' => $ip['country'],
                    'campus' => $ip['campus'],
                    'rir' => $ip['rir'],
                    'routed_to' => $ip['routed_to'],
                    'is_additional' => $ip['is_additional'],
                    'can_be_terminated' => $ip['can_be_terminated'],
                    'description' => $ip['description'],
                    'reverse_dns' => $ip['reverse_dns'],
                    'metadata' => $ip['metadata'] ?? null,
                ]
            );

            $count += $item->wasRecentlyCreated || $item->wasChanged() ? 1 : 0;
        }

        return $count;
    }

    /**
     * Importa os itens individuais de uma fatura OVHcloud.
     */
    public function importBillItems(Invoice $invoice, OvhService $ovh, bool $force = false): int
    {
        if (! $invoice->external_reference) {
            return 0;
        }

        if (! $force && $invoice->items()->exists()) {
            return 0;
        }

        try {
            $items = $ovh->getBillItems((string) $invoice->external_reference);
        } catch (Throwable $exception) {
            Log::warning('Falha ao importar itens da fatura OVHcloud.', [
                'invoice' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }

        if ($items === []) {
            return 0;
        }

        $count = 0;

        foreach ($items as $item) {
            InvoiceItem::query()->updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'external_id' => $item['external_id'],
                ],
                [
                    'description' => mb_substr($item['description'], 0, 250),
                    'domain' => $item['domain'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'currency' => in_array($item['currency'], ['EUR', 'USD', 'BRL'], true) ? $item['currency'] : 'EUR',
                    'period_start' => $this->date($item['period_start']),
                    'period_end' => $this->date($item['period_end']),
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Recalcula o valor mensal de cada serviço a partir da fatura mais recente
     * em que ele aparece, somando todas as linhas do mesmo domínio.
     */
    public function refreshServicePrices(): void
    {
        $services = OvhServiceItem::query()->get();

        foreach ($services as $service) {
            $latestItem = InvoiceItem::query()
                ->where('domain', $service->name)
                ->orderByDesc('period_start')
                ->orderByDesc('id')
                ->first();

            if (! $latestItem) {
                $service->forceFill([
                    'monthly_price_eur' => null,
                    'billing_domain' => null,
                ])->save();

                continue;
            }

            $total = (float) InvoiceItem::query()
                ->where('invoice_id', $latestItem->invoice_id)
                ->where('domain', $service->name)
                ->sum('total_price');

            $invoice = Invoice::query()->find($latestItem->invoice_id);

            $service->forceFill([
                'monthly_price_eur' => round($total, 2),
                'billing_domain' => $service->name,
                'last_bill_id' => $invoice?->external_reference,
                'last_billed_at' => $invoice?->due_date,
            ])->save();
        }
    }

    /**
     * @return array{services: int, ips: int, monthly_eur: float, monthly_brl: float, next_renewal: ?string}
     */
    public function totals(float $eurRate): array
    {
        $monthlyEur = (float) OvhServiceItem::query()->whereNotNull('monthly_price_eur')->sum('monthly_price_eur');

        $nextRenewal = OvhServiceItem::query()
            ->whereNotNull('expiration_date')
            ->orderBy('expiration_date')
            ->value('expiration_date');

        return [
            'services' => OvhServiceItem::query()->count(),
            'ips' => OvhIp::query()->count(),
            'monthly_eur' => round($monthlyEur, 2),
            'monthly_brl' => round($monthlyEur * $eurRate, 2),
            'next_renewal' => $nextRenewal,
        ];
    }

    /**
     * Serviços com valor mensal e conversão para BRL.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pricedServices(float $eurRate): Collection
    {
        return OvhServiceItem::query()
            ->orderByRaw('monthly_price_eur IS NULL')
            ->orderByDesc('monthly_price_eur')
            ->orderBy('name')
            ->get()
            ->map(fn (OvhServiceItem $service): array => [
                'service' => $service,
                'monthly_brl' => $service->monthly_price_eur !== null
                    ? round((float) $service->monthly_price_eur * $eurRate, 2)
                    : null,
            ]);
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
