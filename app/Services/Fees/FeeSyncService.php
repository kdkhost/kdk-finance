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

namespace App\Services\Fees;

use App\Models\AppSetting;
use App\Models\CurrencyConversion;
use App\Models\FeeSyncRun;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestra a leitura das tarifas e grava o resultado em app_settings.
 *
 * Fontes e limites conhecidos:
 * - Visa: markup público da bandeira (consulta diária).
 * - Mastercard: endpoint protegido; a falha é registrada, sem quebrar o cron.
 * - BCB PTAX: cotação oficial de fechamento (USD e EUR).
 * - PayPal: não publica taxa por API. O spread é estimado a partir das
 *   conversões já registradas (provider_rate x market_rate).
 * - Bancos: não publicam API de tarifas. A PTAX cobre a referência oficial;
 *   a tarifa fixa continua manual no painel.
 *
 * Nenhuma tarifa substitui o valor da fatura sem opt-in: o administrador
 * liga "card_spread_auto" e escolhe a fonte em card_spread_source.
 */
class FeeSyncService
{
    public const SOURCE_VISA = 'visa';
    public const SOURCE_MASTERCARD = 'mastercard';
    public const SOURCE_BCB = 'bcb';
    public const SOURCE_PAYPAL = 'paypal';
    public const SOURCE_BANKS = 'bancos';

    public function __construct(
        private readonly CardBrandRateService $brands,
        private readonly BcbPtaxService $ptax,
        private readonly ExchangeRateService $exchange
    ) {
    }

    /**
     * Executa uma ou todas as fontes e devolve o que foi registrado.
     *
     * @return list<FeeSyncRun>
     */
    public function run(string $source = 'all'): array
    {
        $source = strtolower(trim($source));
        $sources = $source === 'all'
            ? [self::SOURCE_VISA, self::SOURCE_MASTERCARD, self::SOURCE_BCB, self::SOURCE_PAYPAL, self::SOURCE_BANKS]
            : [$source];

        $runs = [];

        foreach ($sources as $item) {
            try {
                $runs = array_merge($runs, match ($item) {
                    self::SOURCE_VISA => $this->runVisa(),
                    self::SOURCE_MASTERCARD => $this->runMastercard(),
                    self::SOURCE_BCB => $this->runBcb(),
                    self::SOURCE_PAYPAL => $this->runPaypal(),
                    self::SOURCE_BANKS => [$this->record(
                        self::SOURCE_BANKS,
                        FeeSyncRun::STATUS_SKIPPED,
                        null,
                        'Bancos não publicam API de tarifas. A PTAX do BCB cobre a referência oficial; a tarifa fixa é mantida no painel.'
                    )],
                    default => [$this->record(
                        $item,
                        FeeSyncRun::STATUS_SKIPPED,
                        null,
                        'Fonte desconhecida. Use visa, mastercard, bcb, paypal, bancos ou all.'
                    )],
                });
            } catch (Throwable $exception) {
                Log::warning('Falha ao sincronizar tarifas: '.$item, ['error' => $exception->getMessage()]);
                $runs[] = $this->record($item, FeeSyncRun::STATUS_UNAVAILABLE, null, $exception->getMessage());
            }
        }

        $this->applyAutomaticSpread();
        $this->stampLastRun($runs);

        return $runs;
    }

    /**
     * @return list<FeeSyncRun>
     */
    private function runVisa(): array
    {
        $usd = $this->brands->visa('USD', 'BRL');
        $eur = $this->brands->visa('EUR', 'BRL');
        $run = $this->recordBrand($usd, 'card_brand_visa_markup_percent', 'card_brand_visa_at');

        AppSetting::set('card_brand_visa_rate_usd', $usd['rate'], 'fees');
        AppSetting::set('card_brand_visa_markup_eur_percent', $eur['markup_percent'], 'fees');

        return [$run];
    }

    /**
     * @return list<FeeSyncRun>
     */
    private function runMastercard(): array
    {
        $usd = $this->brands->mastercard('USD', 'BRL', $this->exchange->rate('USD'));

        if ($usd['error'] === null && $usd['markup_percent'] === null) {
            $usd['error'] = 'Markup não publicado; referência indisponível.';
        }

        $run = $this->recordBrand($usd, 'card_brand_mastercard_markup_percent', 'card_brand_mastercard_at');
        AppSetting::set('card_brand_mastercard_rate_usd', $usd['rate'], 'fees');

        return [$run];
    }

    /**
     * @return list<FeeSyncRun>
     */
    private function runBcb(): array
    {
        $usd = $this->ptax->dollar();
        $eur = $this->ptax->euro();

        if ($usd['rate'] !== null) {
            AppSetting::set('bcb_ptax_usd', $usd['rate'], 'fees');
            AppSetting::set('bcb_ptax_usd_date', $usd['date'], 'fees');
        }

        if ($eur['rate'] !== null) {
            AppSetting::set('bcb_ptax_eur', $eur['rate'], 'fees');
            AppSetting::set('bcb_ptax_eur_date', $eur['date'], 'fees');
        }

        AppSetting::set('bcb_ptax_at', now()->toIso8601String(), 'fees');

        $ok = $usd['rate'] !== null && $eur['rate'] !== null;
        $note = $ok
            ? 'Fechamento de '.$usd['date'].' — US$ '.number_format((float) $usd['rate'], 4, ',', '.').' e € '.number_format((float) $eur['rate'], 4, ',', '.')
            : trim(($usd['error'] ?? '').' '.($eur['error'] ?? ''));

        return [$this->record(
            self::SOURCE_BCB,
            $ok ? FeeSyncRun::STATUS_OK : FeeSyncRun::STATUS_UNAVAILABLE,
            $usd['rate'],
            $note !== '' ? $note : null,
            ['usd' => $usd, 'eur' => $eur]
        )];
    }

    /**
     * @return list<FeeSyncRun>
     */
    private function runPaypal(): array
    {
        $observed = $this->paypalObserved();

        if ($observed['samples'] === 0) {
            return [$this->record(
                self::SOURCE_PAYPAL,
                FeeSyncRun::STATUS_SKIPPED,
                null,
                'Nenhuma conversão com cotação do provedor registrada nos últimos '.(int) config('finance.fees.paypal_window_days', 120).' dias.'
            )];
        }

        if ($observed['spread'] === null) {
            return [$this->record(
                self::SOURCE_PAYPAL,
                FeeSyncRun::STATUS_UNAVAILABLE,
                null,
                'Amostras insuficientes para estimar o spread (mínimo '.(int) config('finance.fees.paypal_min_samples', 3).').',
                $observed
            )];
        }

        AppSetting::set('paypal_observed_spread_percent', $observed['spread'], 'fees');
        AppSetting::set('paypal_observed_samples', $observed['samples'], 'fees');
        AppSetting::set('paypal_observed_at', now()->toIso8601String(), 'fees');

        return [$this->record(
            self::SOURCE_PAYPAL,
            FeeSyncRun::STATUS_OK,
            $observed['spread'],
            $observed['samples'].' conversão(ões) entre '.$observed['from'].' e '.$observed['to'].' — spread mediano de '.number_format((float) $observed['spread'], 4, ',', '.').'%.',
            $observed
        )];
    }

    /**
     * Spread efetivo do PayPal: compara a taxa registrada no recibo com a
     * cotação de mercado do mesmo dia. Usa a mediana para ignorar outliers.
     *
     * @return array{samples: int, spread: float|null, from: string|null, to: string|null, min: float|null, max: float|null}
     */
    public function paypalObserved(): array
    {
        $window = (int) config('finance.fees.paypal_window_days', 120);
        $minimum = max(1, (int) config('finance.fees.paypal_min_samples', 3));

        $rows = CurrencyConversion::query()
            ->whereNotNull('provider_rate')
            ->where('market_rate', '>', 0)
            ->where('created_at', '>=', now()->subDays($window))
            ->get(['from_currency', 'to_currency', 'provider_rate', 'provider_rate_inverted', 'market_rate', 'created_at']);

        $spreads = [];
        $pairs = [];

        foreach ($rows as $row) {
            $market = (float) $row->market_rate;
            $provider = (float) $row->provider_rate;

            if ($market <= 0 || $provider <= 0) {
                continue;
            }

            if ($row->provider_rate_inverted) {
                $provider = 1 / $provider;
            }

            $spread = (($provider / $market) - 1) * 100;

            // Descarta leituras absurdas (recibo de outra moeda, digitação).
            if ($spread < 0 || $spread > 15) {
                continue;
            }

            $spreads[] = $spread;
            $pairs[$row->from_currency.'-'.$row->to_currency] = true;
        }

        sort($spreads);
        $count = count($spreads);
        $pairLabel = array_key_first($pairs);

        return [
            'samples' => $count,
            'spread' => $count >= $minimum ? round($this->median($spreads), 4) : null,
            'from' => $pairLabel ? explode('-', $pairLabel)[0] : null,
            'to' => $pairLabel ? explode('-', $pairLabel)[1] : null,
            'min' => $count > 0 ? round($spreads[0], 4) : null,
            'max' => $count > 0 ? round($spreads[$count - 1], 4) : null,
        ];
    }

    /**
     * Aplica o spread observado quando a atualização automática está ligada.
     *
     * @return array{applied: bool, source: string, value: float|null}
     */
    public function applyAutomaticSpread(): array
    {
        if (! filter_var(AppSetting::get('card_spread_auto', '0'), FILTER_VALIDATE_BOOLEAN)) {
            return ['applied' => false, 'source' => 'manual', 'value' => null];
        }

        $source = (string) AppSetting::get('card_spread_source', self::SOURCE_PAYPAL);
        $value = null;

        if ($source === self::SOURCE_VISA) {
            $value = AppSetting::get('card_brand_visa_markup_percent');
        } elseif ($source === self::SOURCE_PAYPAL) {
            $value = AppSetting::get('paypal_observed_spread_percent');
        }

        if ($value === null || (float) $value <= 0) {
            return ['applied' => false, 'source' => $source, 'value' => null];
        }

        AppSetting::set('spread_percent', round((float) $value, 4), 'taxes');
        AppSetting::set('card_spread_source', $source, 'fees');
        AppSetting::set('card_spread_updated_at', now()->toIso8601String(), 'fees');

        return ['applied' => true, 'source' => $source, 'value' => round((float) $value, 4)];
    }

    /**
     * Histórico recente para a tela de configurações.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FeeSyncRun>
     */
    public function history(int $limit = 12): \Illuminate\Database\Eloquent\Collection
    {
        return FeeSyncRun::query()->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * @param  array{brand: string, pair: string, markup_percent: float|null, rate: float|null, error: string|null, url: string}  $result
     * @return FeeSyncRun
     */
    private function recordBrand(array $result, string $settingKey, string $settingAt): FeeSyncRun
    {
        if ($result['markup_percent'] !== null) {
            AppSetting::set($settingKey, $result['markup_percent'], 'fees');
            AppSetting::set($settingAt, now()->toIso8601String(), 'fees');
        }

        $status = $result['markup_percent'] !== null
            ? FeeSyncRun::STATUS_OK
            : FeeSyncRun::STATUS_UNAVAILABLE;

        $note = $status === FeeSyncRun::STATUS_OK
            ? 'Markup de '.number_format((float) $result['markup_percent'], 4, ',', '.').'% em '.$result['pair'].'.'
            : (string) ($result['error'] ?? 'Sem resposta');

        return $this->record(
            strtolower($result['brand']),
            $status,
            $result['markup_percent'],
            $note,
            $result
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function record(string $source, string $status, ?float $value, ?string $note, ?array $payload = null): FeeSyncRun
    {
        return FeeSyncRun::query()->create([
            'source' => $source,
            'status' => $status,
            'value' => $value,
            'note' => $note,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<FeeSyncRun>  $runs
     */
    private function stampLastRun(array $runs): void
    {
        $failed = collect($runs)->contains(fn (FeeSyncRun $run): bool => $run->status === FeeSyncRun::STATUS_UNAVAILABLE);

        AppSetting::set('fee_sync_last_at', now()->toIso8601String(), 'fees');
        AppSetting::set('fee_sync_last_status', $failed ? FeeSyncRun::STATUS_UNAVAILABLE : FeeSyncRun::STATUS_OK, 'fees');
    }

    /**
     * @param  list<float>  $values  Já ordenado.
     */
    private function median(array $values): float
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }
}
