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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange:rates:USD-BRL,EUR-BRL';
    private const LAST_KNOWN_KEY = 'exchange:last-known';

    /**
     * Retorna as cotações USD/BRL e EUR/BRL (cache de 15 minutos).
     *
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    public function rates(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        try {
            return Cache::remember(
                self::CACHE_KEY,
                now()->addMinutes((int) config('finance.exchange.cache_minutes', 15)),
                fn (): array => $this->fetchRates()
            );
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar a AwesomeAPI. Usando a última cotação conhecida.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->lastKnownRates($exception->getMessage());
        }
    }

    public function rate(string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === 'BRL') {
            return 1.0;
        }

        return (float) ($this->rates()[$currency]['rate'] ?? 0.0);
    }

    /**
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    private function fetchRates(): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('finance.exchange.timeout', 12))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get((string) config('finance.exchange.api_url'));

        if (! $response->successful()) {
            throw new \RuntimeException('AwesomeAPI respondeu HTTP '.$response->status());
        }

        $payload = $response->json();

        $rates = [];

        foreach (['USD' => 'USDBRL', 'EUR' => 'EURBRL'] as $currency => $pair) {
            $rate = (float) data_get($payload, $pair.'.bid');

            if ($rate <= 0) {
                throw new \RuntimeException('Cotação inválida para '.$currency);
            }

            $rates[$currency] = [
                'rate' => round($rate, 4),
                'pct_change' => round((float) data_get($payload, $pair.'.pctChange', 0), 4),
                'source' => 'awesomeapi',
                'updated_at' => now()->toIso8601String(),
                'stale' => false,
            ];
        }

        $this->rememberLastKnown($rates);

        return $rates;
    }

    /**
     * Mantém a última cotação válida mesmo se a API sair do ar.
     *
     * @param  array<string, array<string, mixed>>  $rates
     */
    private function rememberLastKnown(array $rates): void
    {
        Cache::forever(self::LAST_KNOWN_KEY, $rates);

        try {
            foreach ($rates as $currency => $data) {
                AppSetting::set('last_rate_'.$currency, $data['rate'], 'exchange');
            }

            AppSetting::set('last_rate_at', now()->toIso8601String(), 'exchange');
        } catch (Throwable $exception) {
            // O banco pode não estar disponível em comandos de instalação.
            Log::debug('Não foi possível persistir a última cotação.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string}>
     */
    private function lastKnownRates(string $error): array
    {
        $cached = Cache::get(self::LAST_KNOWN_KEY);

        if (is_array($cached) && $cached !== []) {
            return collect($cached)
                ->map(fn (array $item): array => array_merge($item, [
                    'source' => ($item['source'] ?? 'awesomeapi').' (última conhecida)',
                    'stale' => true,
                    'error' => $error,
                ]))
                ->all();
        }

        $rates = [];

        foreach (['USD', 'EUR'] as $currency) {
            $fallback = (float) AppSetting::get(
                'last_rate_'.$currency,
                config('finance.exchange.default_rates.'.$currency, 0)
            );

            $rates[$currency] = [
                'rate' => round($fallback, 4),
                'pct_change' => 0.0,
                'source' => 'padrão do sistema',
                'updated_at' => (string) AppSetting::get('last_rate_at', now()->toIso8601String()),
                'stale' => true,
                'error' => $error,
            ];
        }

        return $rates;
    }
}
