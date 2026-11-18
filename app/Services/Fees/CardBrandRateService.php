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

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lê o markup cambial publicado pelas bandeiras.
 *
 * - Visa: endpoint público do simulador de câmbio (visa.com.br/cmsapi/fx).
 *   Devolve o "markupWithoutAdditionalFee" em fração — 0,003121 = 0,3121%.
 * - Mastercard: endpoint de settlements, protegido por Akamai. Quando a
 *   resposta é 403 o serviço devolve a falha sem derrubar o cron; o markup
 *   é derivado comparando a taxa informada com a cotação de mercado.
 *
 * Nenhuma credencial é necessária. O resultado é apenas observado e gravado
 * em app_settings pelo FeeSyncService.
 */
class CardBrandRateService
{
    /**
     * @return array{brand: string, pair: string, markup_percent: float|null, rate: float|null, error: string|null, url: string}
     */
    public function visa(string $from = 'USD', string $to = 'BRL'): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $url = (string) config('finance.fees.visa_url');
        $today = now('America/Sao_Paulo');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('finance.fees.timeout', 15))
                ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
                ->get($url, [
                    'amount' => 1,
                    'fee' => 0,
                    'utcConvertedDate' => $today->format('Y-m-d'),
                    'exchangedate' => $today->format('m/d/Y'),
                    'fromCurr' => $from,
                    'toCurr' => $to,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Visa respondeu HTTP '.$response->status());
            }

            $payload = (array) $response->json();
            $markup = data_get($payload, 'originalValues.benchmarks.0.markupWithoutAdditionalFee');

            if ($markup === null) {
                throw new \RuntimeException('Resposta da Visa sem o markup esperado');
            }

            // A Visa devolve a direção que quiser (às vezes inverte o par),
            // por isso normalizamos a taxa para from → to.
            $responseFrom = strtoupper((string) data_get($payload, 'originalValues.fromCurrency', $from));
            $fx = (float) data_get($payload, 'originalValues.fxRateVisa', 0);
            $rate = $fx > 0 ? ($responseFrom === $from ? $fx : 1 / $fx) : null;

            return $this->result('Visa', $from, $to, (float) $markup * 100, $rate, null, $url);
        } catch (Throwable $exception) {
            return $this->result('Visa', $from, $to, null, null, $exception->getMessage(), $url);
        }
    }

    /**
     * @return array{brand: string, pair: string, markup_percent: float|null, rate: float|null, error: string|null, url: string}
     */
    public function mastercard(string $from = 'USD', string $to = 'BRL', ?float $marketRate = null): array
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $url = (string) config('finance.fees.mastercard_url');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('finance.fees.timeout', 15))
                ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
                ->get($url, [
                    'fxDate' => now('America/Sao_Paulo')->format('Y-m-d'),
                    'transCurr' => $from,
                    'crdhldBillCurr' => $to,
                    'bankFee' => 0,
                    'transAmt' => 1,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Mastercard respondeu HTTP '.$response->status());
            }

            $payload = (array) $response->json();
            $rate = (float) data_get($payload, 'data.conversionRate', data_get($payload, 'conversionRate', 0));

            if ($rate <= 0) {
                throw new \RuntimeException('Resposta da Mastercard sem taxa de conversão');
            }

            // A Mastercard não publica o markup; ele sai da comparação entre a
            // taxa praticada e a cotação de mercado do mesmo momento.
            $markup = $marketRate !== null && $marketRate > 0
                ? (($rate / $marketRate) - 1) * 100
                : null;

            return $this->result('Mastercard', $from, $to, $markup, $rate, null, $url);
        } catch (Throwable $exception) {
            return $this->result('Mastercard', $from, $to, null, null, $exception->getMessage(), $url);
        }
    }

    /**
     * @return array{brand: string, pair: string, markup_percent: float|null, rate: float|null, error: string|null, url: string}
     */
    private function result(
        string $brand,
        string $from,
        string $to,
        ?float $markupPercent,
        ?float $rate,
        ?string $error,
        string $url
    ): array {
        return [
            'brand' => $brand,
            'pair' => $from.'-'.$to,
            'markup_percent' => $markupPercent === null ? null : round($markupPercent, 4),
            'rate' => $rate === null ? null : round($rate, 6),
            'error' => $error,
            'url' => $url,
        ];
    }
}
