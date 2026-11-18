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

use App\Models\AppSetting;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ovh\Api;
use RuntimeException;
use Throwable;

class OvhService
{
    /**
     * Credenciais descriptografadas, lidas exclusivamente do banco.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        return [
            'endpoint' => (string) AppSetting::get('ovh_endpoint', config('finance.ovh.endpoint', 'ovh-eu')),
            'application_key' => (string) AppSetting::get('ovh_application_key', ''),
            'application_secret' => (string) AppSetting::get('ovh_application_secret', ''),
            'consumer_key' => (string) AppSetting::get('ovh_consumer_key', ''),
            'v2_bearer_token' => (string) AppSetting::get('ovh_v2_bearer_token', ''),
        ];
    }

    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return $credentials['application_key'] !== ''
            && $credentials['application_secret'] !== ''
            && $credentials['consumer_key'] !== '';
    }

    /**
     * Cliente oficial OVHcloud (autenticação AK/AS/CK, API v1).
     */
    public function client(): Api
    {
        $credentials = $this->credentials();

        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Credenciais da OVHcloud não configuradas. Informe Application Key, Application Secret e Consumer Key em Configurações › OVHcloud.'
            );
        }

        $httpClient = new Client([
            'timeout' => (int) config('finance.ovh.timeout', 20),
            'connect_timeout' => 5,
            'verify' => (bool) config('finance.exchange.verify_ssl', true),
        ]);

        return new Api(
            $credentials['application_key'],
            $credentials['application_secret'],
            $credentials['endpoint'] !== '' ? $credentials['endpoint'] : 'ovh-eu',
            $credentials['consumer_key'],
            $httpClient
        );
    }

    /**
     * Valida as credenciais e devolve os dados da conta.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        $me = $this->client()->get('/me');
        $services = $this->client()->get('/service');

        return [
            'nichandle' => data_get($me, 'nichandle'),
            'name' => trim((string) data_get($me, 'firstname').' '.(string) data_get($me, 'name')),
            'email' => data_get($me, 'email'),
            'country' => data_get($me, 'country'),
            'currency' => data_get($me, 'currency.code'),
            'services_count' => is_array($services) ? count($services) : 0,
        ];
    }

    /**
     * Todos os serviços contratados (detalhados) da conta OVHcloud.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveServices(int $limit = 200): array
    {
        $ids = $this->client()->get('/service');

        if (! is_array($ids)) {
            return [];
        }

        $ids = array_slice(array_values($ids), -$limit);

        $services = [];

        foreach ($ids as $id) {
            try {
                $service = Cache::remember(
                    'ovh:service:'.$id,
                    now()->addHour(),
                    fn () => $this->client()->get('/service/'.$id)
                );

                $routePath = (string) data_get($service, 'route.path', '');
                $type = trim(explode('/', trim($routePath, '/'))[0] ?? '', '/');

                $services[] = [
                    'service_id' => (string) $id,
                    'name' => (string) data_get($service, 'resource.name', $id),
                    'display_name' => data_get($service, 'resource.displayName'),
                    'type' => $type !== '' ? $type : null,
                    'route_path' => $routePath !== '' ? $routePath : null,
                    'route_url' => data_get($service, 'route.url'),
                    'state' => data_get($service, 'resource.state'),
                    'quantity' => (int) data_get($service, 'quantity', 1),
                    'renew_mode' => data_get($service, 'renew.mode'),
                    'renew_interval' => data_get($service, 'renew.interval'),
                    'creation_date' => data_get($service, 'creationDate'),
                    'expiration_date' => data_get($service, 'expirationDate'),
                    'next_billing_date' => data_get($service, 'nextBillingDate'),
                    'metadata' => $service,
                ];
            } catch (Throwable $exception) {
                Log::warning('Falha ao detalhar serviço OVHcloud.', [
                    'service' => $id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $services;
    }

    /**
     * Faturas recentes da OVHcloud.
     *
     * A API legada /me/bill devolve apenas os identificadores; o status de
     * pagamento não faz parte do modelo. O sistema considera como "pendentes"
     * as faturas emitidas dentro da janela informada e que ainda não foram
     * conciliadas internamente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentBills(int $days = 365, int $limit = 100): array
    {
        $ids = $this->client()->get('/me/bill');

        if (! is_array($ids)) {
            return [];
        }

        $ids = array_slice(array_values($ids), -$limit);
        $cutoff = now()->subDays($days);
        $bills = [];

        foreach (array_reverse($ids) as $billId) {
            try {
                $bill = $this->getBill((string) $billId);
                $date = Carbon::parse(data_get($bill, 'date', now()));

                if ($date->lt($cutoff)) {
                    continue;
                }

                $bills[] = array_merge($bill, [
                    'billId' => data_get($bill, 'billId', $billId),
                    'parsed_date' => $date,
                ]);
            } catch (Throwable $exception) {
                Log::warning('Falha ao consultar fatura OVHcloud.', [
                    'bill' => $billId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $bills;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPendingInvoices(int $days = 365, int $limit = 100): array
    {
        return $this->getRecentBills($days, $limit);
    }

    /**
     * Itens (linhas) de uma fatura, com valores individuais por serviço.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBillItems(string $billId): array
    {
        $ids = Cache::remember(
            'ovh:bill-items-ids:'.$billId,
            now()->addMinutes(30),
            fn () => $this->client()->get('/me/bill/'.$billId.'/details')
        );

        if (! is_array($ids)) {
            return [];
        }

        $items = [];

        foreach ($ids as $detailId) {
            try {
                $detail = Cache::remember(
                    'ovh:bill-item:'.$billId.':'.$detailId,
                    now()->addMinutes(30),
                    fn () => (array) $this->client()->get('/me/bill/'.$billId.'/details/'.$detailId)
                );

                $items[] = [
                    'external_id' => (string) data_get($detail, 'billDetailId', $detailId),
                    'description' => (string) data_get($detail, 'description', 'Item da fatura'),
                    'domain' => data_get($detail, 'domain'),
                    'quantity' => (float) data_get($detail, 'quantity', 1),
                    'unit_price' => (float) data_get($detail, 'unitPrice.value', 0),
                    'total_price' => (float) data_get($detail, 'totalPrice.value', 0),
                    'currency' => strtoupper((string) data_get($detail, 'unitPrice.currencyCode', 'EUR')),
                    'period_start' => data_get($detail, 'periodStart'),
                    'period_end' => data_get($detail, 'periodEnd'),
                    'metadata' => $detail,
                ];
            } catch (Throwable $exception) {
                Log::warning('Falha ao consultar item de fatura OVHcloud.', [
                    'bill' => $billId,
                    'detail' => $detailId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $items;
    }

    /**
     * Blocos de IP da conta, com detalhes e DNS reverso.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getIps(): array
    {
        $blocks = $this->client()->get('/ip');

        if (! is_array($blocks)) {
            return [];
        }

        $ips = [];

        foreach ($blocks as $block) {
            try {
                $encoded = rawurlencode((string) $block);

                $detail = Cache::remember(
                    'ovh:ip:'.$block,
                    now()->addHour(),
                    fn () => (array) $this->client()->get('/ip/'.$encoded)
                );

                $reverse = [];

                try {
                    $reverse = (array) Cache::remember(
                        'ovh:ip-reverse:'.$block,
                        now()->addHour(),
                        fn () => $this->client()->get('/ip/'.$encoded.'/reverse')
                    );
                } catch (Throwable $exception) {
                    Log::debug('DNS reverso indisponível para o IP OVHcloud.', [
                        'block' => $block,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $ips[] = [
                    'block' => (string) data_get($detail, 'ip', $block),
                    'version' => (int) data_get($detail, 'version', str_contains((string) $block, ':') ? 6 : 4),
                    'type' => data_get($detail, 'type'),
                    'country' => data_get($detail, 'country'),
                    'campus' => data_get($detail, 'campus'),
                    'rir' => data_get($detail, 'rir'),
                    'routed_to' => data_get($detail, 'routedTo.serviceName'),
                    'is_additional' => (bool) data_get($detail, 'isAdditionalIp', false),
                    'can_be_terminated' => (bool) data_get($detail, 'canBeTerminated', false),
                    'description' => data_get($detail, 'description'),
                    'reverse_dns' => array_values($reverse),
                    'metadata' => $detail,
                ];
            } catch (Throwable $exception) {
                Log::warning('Falha ao detalhar bloco de IP OVHcloud.', [
                    'block' => $block,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $ips;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBill(string $billId): array
    {
        return Cache::remember(
            'ovh:bill:'.$billId,
            now()->addMinutes(30),
            fn (): array => (array) $this->client()->get('/me/bill/'.$billId)
        );
    }

    /**
     * Relê a fatura ignorando o cache de 30 minutos.
     *
     * @return array<string, mixed>
     */
    public function refreshBill(string $billId): array
    {
        Cache::forget('ovh:bill:'.$billId);

        return $this->getBill($billId);
    }

    /**
     * Status de pagamento de uma fatura, segundo a OVHcloud.
     *
     * A fatura (/me/bill) não traz campo de pagamento. A OVHcloud expõe o
     * estado real pelo pedido (/me/order/{id}/status) e pelo débito vinculado
     * ao pedido (/me/debtAccount/debt/{id}). "delivered"/"delivering" já
     * significam pedido quitado; débitos só contam quando ainda não foram
     * pagos. Sem resposta confiável, devolve null para o chamador decidir.
     *
     * @param  array<string, mixed>  $bill
     * @param  array<int, string>|null  $debtStatuses
     * @return string|null "paid", "pending" ou null quando indefinido
     */
    public function paymentStatusForBill(array $bill, ?array $debtStatuses = null, bool $fresh = false): ?string
    {
        $orderId = (int) data_get($bill, 'orderId', 0);

        if ($orderId > 0) {
            $orderStatus = $this->orderStatus($orderId, $fresh);

            if ($orderStatus !== null) {
                if (in_array($orderStatus, ['delivered', 'delivering'], true)) {
                    return 'paid';
                }

                if (in_array($orderStatus, ['notPaid', 'documentsRequested'], true)) {
                    return 'pending';
                }
            }
        }

        $debtStatuses ??= $this->debtStatuses($fresh);
        $debt = $orderId > 0 ? ($debtStatuses[$orderId] ?? null) : null;

        if ($debt !== null) {
            return $debt === 'PAID' ? 'paid' : 'pending';
        }

        return null;
    }

    /**
     * Estado do pedido na OVHcloud (delivered, notPaid, delivering etc.).
     */
    public function orderStatus(int $orderId, bool $fresh = false): ?string
    {
        if ($orderId <= 0) {
            return null;
        }

        $key = 'ovh:order-status:'.$orderId;

        if ($fresh) {
            Cache::forget($key);
        }

        try {
            $status = Cache::remember(
                $key,
                now()->addMinutes(30),
                fn () => $this->client()->get('/me/order/'.$orderId.'/status')
            );
        } catch (Throwable $exception) {
            Log::debug('Status do pedido OVHcloud indisponível.', [
                'order' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return is_string($status) && $status !== '' ? $status : null;
    }

    /**
     * Débitos da conta mapeados por pedido, com o status de cada um.
     *
     * @return array<int, string>
     */
    public function debtStatuses(bool $fresh = false): array
    {
        $key = 'ovh:debt-statuses';

        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(15), function (): array {
                $ids = $this->client()->get('/me/debtAccount/debt');

                if (! is_array($ids)) {
                    return [];
                }

                $map = [];

                foreach ($ids as $debtId) {
                    try {
                        $debt = (array) $this->client()->get('/me/debtAccount/debt/'.rawurlencode((string) $debtId));
                        $orderId = (int) data_get($debt, 'orderId', 0);

                        if ($orderId > 0) {
                            $map[$orderId] = strtoupper((string) data_get($debt, 'status', ''));
                        }
                    } catch (Throwable $exception) {
                        Log::debug('Débito OVHcloud indisponível.', [
                            'debt' => $debtId,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                return $map;
            });
        } catch (Throwable $exception) {
            Log::warning('Não foi possível consultar os débitos da OVHcloud.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Converte uma fatura OVHcloud para os atributos do model Invoice.
     *
     * @param  array<string, mixed>  $bill
     * @return array<string, mixed>
     */
    public function billToInvoiceAttributes(array $bill): array
    {
        $currency = strtoupper((string) data_get($bill, 'priceWithTax.currencyCode', 'EUR'));
        $amount = (float) data_get($bill, 'priceWithTax.value', data_get($bill, 'priceWithoutTax.value', 0));
        $date = Carbon::parse(data_get($bill, 'date', now()));

        return [
            'external_reference' => (string) data_get($bill, 'billId'),
            'external_url' => data_get($bill, 'url'),
            'description' => 'OVHcloud · fatura '.data_get($bill, 'billId').' ('.(data_get($bill, 'category') ?: 'serviço').')',
            'currency' => in_array($currency, ['EUR', 'USD'], true) ? $currency : 'EUR',
            'amount_original' => round($amount, 2),
            'due_date' => $date->toDateString(),
            'status' => 'pending',
        ];
    }

    /**
     * Consulta a API v2 (OAuth2/Bearer) — endpoint informado pelo console
     * https://api.eu.ovhcloud.com/console/
     *
     * @return array<string, mixed>
     */
    public function getApiRevisionV2(): array
    {
        $token = $this->credentials()['v2_bearer_token'];

        if ($token === '') {
            throw new RuntimeException(
                'Token Bearer da API v2 não configurado. Gere um token OAuth2 no console da OVHcloud e salve em Configurações › OVHcloud.'
            );
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('finance.ovh.timeout', 20))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get(rtrim((string) config('finance.ovh.v2_base_url'), '/').'/account/api/revision');

        if (! $response->successful()) {
            throw new RuntimeException(
                'API v2 da OVHcloud respondeu HTTP '.$response->status().': '.data_get($response->json(), 'message', 'falha de autenticação.')
            );
        }

        return (array) $response->json();
    }
}
