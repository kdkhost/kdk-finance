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
     * Serviços ativos (detalhados) da conta OVHcloud.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveServices(int $limit = 30): array
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

                $services[] = [
                    'id' => $id,
                    'name' => data_get($service, 'resource.name'),
                    'display_name' => data_get($service, 'resource.displayName'),
                    'state' => data_get($service, 'resource.state'),
                    'route' => data_get($service, 'route.path'),
                    'expiration_date' => data_get($service, 'expirationDate'),
                    'next_billing_date' => data_get($service, 'nextBillingDate'),
                    'renew_mode' => data_get($service, 'renew.mode'),
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
    public function getRecentBills(int $days = 60, int $limit = 25): array
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
    public function getPendingInvoices(int $days = 60, int $limit = 25): array
    {
        return $this->getRecentBills($days, $limit);
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
