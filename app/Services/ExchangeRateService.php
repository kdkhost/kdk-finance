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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange:rates:USD-BRL,EUR-BRL';
    private const LAST_KNOWN_KEY = 'exchange:last-known';
    private const PAIR_LAST_KNOWN_PREFIX = 'exchange:pair-last-known:';

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

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $rates = $this->fetchRates();
            Cache::put(
                self::CACHE_KEY,
                $rates,
                now()->addMinutes((int) config('finance.exchange.cache_minutes', 15))
            );

            return $rates;
        } catch (Throwable $exception) {
            Log::warning('Nenhum provedor de câmbio respondeu. Usando a última cotação conhecida.', [
                'error' => $exception->getMessage(),
            ]);

            $rates = $this->lastKnownRates($exception->getMessage());

            // Guarda também o resultado degradado por alguns minutos para não
            // repetir a consulta (e o bloqueio por excesso de requisições) a
            // cada carregamento de página.
            Cache::put(
                self::CACHE_KEY,
                $rates,
                now()->addMinutes((int) config('finance.exchange.failure_cache_minutes', 2))
            );

            return $rates;
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
     * Cotação de um par livre (EUR-USD, USD-EUR, EUR-BRL, USD-EUR etc.).
     *
     * Usa o par direto da AwesomeAPI (gratuito, sem chave) com cache curto.
     * Se o par direto falhar, deriva o valor pelo cruzamento com o BRL e,
     * em último caso, devolve a última cotação conhecida do par.
     *
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}
     */
    public function pair(string $from, string $to, bool $fresh = false): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $this->pairPayload($from, $to, 1.0, 0.0, 'identidade', false, null);
        }

        $cacheKey = 'exchange:pair:'.$from.'-'.$to;

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('finance.exchange.pair_cache_minutes', 5)),
            fn (): array => $this->resolvePair($from, $to, $fresh)
        );
    }

    /**
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}
     */
    private function resolvePair(string $from, string $to, bool $fresh = false): array
    {
        try {
            return $this->fetchPair($from, $to);
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar o par '.$from.'-'.$to.' na AwesomeAPI.', [
                'error' => $exception->getMessage(),
            ]);

            $crossed = $this->crossPair($from, $to, $fresh);

            if ($crossed !== null) {
                return $crossed;
            }

            return $this->lastKnownPair($from, $to, $exception->getMessage());
        }
    }

    /**
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}
     */
    private function fetchPair(string $from, string $to): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('finance.exchange.timeout', 12))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get(rtrim((string) config('finance.exchange.pair_api_url'), '/').'/'.$from.'-'.$to);

        if (! $response->successful()) {
            throw new \RuntimeException('AwesomeAPI respondeu HTTP '.$response->status());
        }

        $payload = $response->json();
        $node = data_get($payload, $from.$to) ?? data_get($payload, 0) ?? [];

        $rate = (float) data_get($node, 'bid');

        if ($rate <= 0) {
            throw new \RuntimeException('Cotação inválida para '.$from.'-'.$to);
        }

        $payloadOut = $this->pairPayload(
            $from,
            $to,
            $rate,
            (float) data_get($node, 'pctChange', 0),
            'awesomeapi',
            false,
            null
        );

        $this->rememberPair($from, $to, $payloadOut);

        return $payloadOut;
    }

    /**
     * Deriva o par pelas duas pontas em BRL (ex.: EUR-USD = EURBRL / USDBRL).
     *
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}|null
     */
    private function crossPair(string $from, string $to, bool $fresh = false): ?array
    {
        try {
            $rates = $this->rates($fresh);
        } catch (Throwable) {
            return null;
        }

        $fromRate = $from === 'BRL' ? 1.0 : (float) ($rates[$from]['rate'] ?? 0.0);
        $toRate = $to === 'BRL' ? 1.0 : (float) ($rates[$to]['rate'] ?? 0.0);

        if ($fromRate <= 0 || $toRate <= 0) {
            return null;
        }

        $payload = $this->pairPayload(
            $from,
            $to,
            $fromRate / $toRate,
            0.0,
            'cruzamento BRL',
            (bool) ($rates[$to]['stale'] ?? false),
            null
        );

        $this->rememberPair($from, $to, $payload);

        return $payload;
    }

    /**
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}
     */
    private function pairPayload(string $from, string $to, float $rate, float $pctChange, string $source, bool $stale, ?string $error): array
    {
        $rate = round($rate, 6);

        return [
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'inverse' => round(1 / $rate, 6),
            'pct_change' => round($pctChange, 4),
            'source' => $source,
            'updated_at' => now()->toIso8601String(),
            'stale' => $stale,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberPair(string $from, string $to, array $payload): void
    {
        Cache::forever(self::PAIR_LAST_KNOWN_PREFIX.$from.'-'.$to, $payload);

        try {
            AppSetting::set('last_pair_'.$from.'_'.$to, $payload['rate'], 'exchange');
            AppSetting::set('last_pair_'.$from.'_'.$to.'_at', $payload['updated_at'], 'exchange');
        } catch (Throwable $exception) {
            Log::debug('Não foi possível persistir a última cotação do par.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array{from: string, to: string, rate: float, inverse: float, pct_change: float, source: string, updated_at: string, stale: bool, error: string|null}
     */
    private function lastKnownPair(string $from, string $to, string $error): array
    {
        $key = self::PAIR_LAST_KNOWN_PREFIX.$from.'-'.$to;
        $cached = Cache::get($key);

        if (is_array($cached) && (float) ($cached['rate'] ?? 0) > 0) {
            return array_merge($cached, [
                'source' => $this->staleSource((string) ($cached['source'] ?? 'awesomeapi')),
                'stale' => true,
                'error' => $error,
            ]);
        }

        $fallback = (float) AppSetting::get(
            'last_pair_'.$from.'_'.$to,
            config('finance.conversion.fallback_pair_rates.'.$from.'-'.$to, 1.0)
        );

        return $this->pairPayload($from, $to, $fallback, 0.0, 'padrão do sistema', true, $error);
    }

    /**
     * Consulta os provedores na ordem configurada e usa o primeiro que
     * responder com cotações válidas.
     *
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    private function fetchRates(): array
    {
        $providers = (array) config('finance.exchange.providers', ['awesomeapi', 'er-api', 'frankfurter']);
        $errors = [];

        foreach ($providers as $provider) {
            try {
                $rates = $this->ratesFromProvider((string) $provider);
                $this->rememberLastKnown($rates);

                return $rates;
            } catch (Throwable $exception) {
                $errors[] = $provider.': '.$exception->getMessage();
                Log::warning('Provedor de câmbio indisponível: '.$provider, [
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException('Nenhum provedor respondeu ('.implode('; ', $errors).')');
    }

    /**
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    private function ratesFromProvider(string $provider): array
    {
        return match ($provider) {
            'awesomeapi' => $this->ratesFromAwesomeApi(),
            'er-api' => $this->ratesFromUsdBase(
                (string) config('finance.exchange.er_api_url'),
                'open.er-api'
            ),
            'frankfurter' => $this->ratesFromUsdBase(
                (string) config('finance.exchange.frankfurter_url'),
                'frankfurter'
            ),
            default => throw new \RuntimeException('Provedor de câmbio desconhecido: '.$provider),
        };
    }

    /**
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    private function ratesFromAwesomeApi(): array
    {
        $response = Http::acceptJson()
            ->timeout((int) config('finance.exchange.timeout', 12))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get((string) config('finance.exchange.api_url'));

        if (! $response->successful()) {
            throw new \RuntimeException('AwesomeAPI respondeu HTTP '.$response->status());
        }

        $payload = $response->json();

        return [
            'USD' => $this->rateEntry($payload, 'USDBRL', 'awesomeapi'),
            'EUR' => $this->rateEntry($payload, 'EURBRL', 'awesomeapi'),
        ];
    }

    /**
     * Provedores que devolvem cotações com base no dólar (1 USD = X).
     * O euro em reais é derivado de BRL/EUR.
     *
     * @return array<string, array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}>
     */
    private function ratesFromUsdBase(string $url, string $source): array
    {
        if ($url === '') {
            throw new \RuntimeException('Endereço do provedor '.$source.' não configurado');
        }

        $response = Http::acceptJson()
            ->timeout((int) config('finance.exchange.timeout', 12))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException($source.' respondeu HTTP '.$response->status());
        }

        $payload = $response->json();
        $usdBrl = (float) data_get($payload, 'rates.BRL');
        $usdEur = (float) data_get($payload, 'rates.EUR');

        if ($usdBrl <= 0 || $usdEur <= 0) {
            throw new \RuntimeException('Cotação inválida em '.$source);
        }

        return [
            'USD' => $this->rateValue($usdBrl, $source),
            'EUR' => $this->rateValue($usdBrl / $usdEur, $source),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}
     */
    private function rateEntry(array $payload, string $pair, string $source): array
    {
        $rate = (float) data_get($payload, $pair.'.bid');

        if ($rate <= 0) {
            throw new \RuntimeException('Cotação inválida para '.$pair);
        }

        return [
            'rate' => round($rate, 4),
            'pct_change' => round((float) data_get($payload, $pair.'.pctChange', 0), 4),
            'source' => $source,
            'updated_at' => now()->toIso8601String(),
            'stale' => false,
        ];
    }

    /**
     * Evita acumular o sufixo "última conhecida" quando uma cotação já
     * degradada volta a ser usada.
     */
    private function staleSource(string $source): string
    {
        $source = (string) preg_replace('/\s*\(última conhecida\)\s*$/u', '', $source);

        return ($source !== '' ? $source : 'awesomeapi').' (última conhecida)';
    }

    /**
     * @return array{rate: float, pct_change: float, source: string, updated_at: string, stale: bool}
     */
    private function rateValue(float $rate, string $source): array
    {
        return [
            'rate' => round($rate, 4),
            'pct_change' => 0.0,
            'source' => $source,
            'updated_at' => now()->toIso8601String(),
            'stale' => false,
        ];
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
                    'source' => $this->staleSource((string) ($item['source'] ?? 'awesomeapi')),
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
