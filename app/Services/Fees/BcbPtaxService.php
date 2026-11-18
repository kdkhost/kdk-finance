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

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta a PTAX do Banco Central (Olinda), a referência oficial usada
 * pelos bancos brasileiros para câmbio de fechamento.
 *
 * O Boletim PTAX não é publicado em fins de semana e feriados; por isso o
 * serviço retrocede até sete dias até encontrar uma cotação válida.
 */
class BcbPtaxService
{
    /**
     * @return array{currency: string, rate: float|null, date: string|null, bulletin: string|null, error: string|null}
     */
    public function dollar(?CarbonInterface $date = null): array
    {
        return $this->fetch('USD', $date);
    }

    /**
     * @return array{currency: string, rate: float|null, date: string|null, bulletin: string|null, error: string|null}
     */
    public function euro(?CarbonInterface $date = null): array
    {
        return $this->fetch('EUR', $date);
    }

    /**
     * @return array{currency: string, rate: float|null, date: string|null, bulletin: string|null, error: string|null}
     */
    private function fetch(string $currency, ?CarbonInterface $date = null): array
    {
        $base = rtrim((string) config('finance.fees.bcb_url'), '/');
        $day = $date ? $date->copy() : now('America/Sao_Paulo');
        $lastError = null;

        for ($attempt = 0; $attempt < 7; $attempt++) {
            $query = $day->format('m-d-Y');

            try {
                if ($currency === 'USD') {
                    $url = $base.'/CotacaoDolarDia(dataCotacao=@dataCotacao)';
                    $response = $this->request(
                        $url,
                        ['@dataCotacao' => "'".$query."'"],
                        'cotacaoCompra,cotacaoVenda,dataHoraCotacao'
                    );
                    $node = (array) data_get((array) $response->json(), 'value.0', []);
                } else {
                    $url = $base.'/CotacaoMoedaDia(moeda=@moeda,dataCotacao=@dataCotacao)';
                    $response = $this->request(
                        $url,
                        [
                            '@moeda' => "'".$currency."'",
                            '@dataCotacao' => "'".$query."'",
                        ],
                        'cotacaoCompra,cotacaoVenda,dataHoraCotacao,tipoBoletim'
                    );

                    $items = (array) data_get((array) $response->json(), 'value', []);
                    $node = $this->closingBulletin($items);
                }

                $rate = (float) ($node['cotacaoVenda'] ?? $node['cotacaoCompra'] ?? 0);

                if ($rate > 0) {
                    return [
                        'currency' => $currency,
                        'rate' => round($rate, 4),
                        'date' => substr((string) ($node['dataHoraCotacao'] ?? $day->format('Y-m-d')), 0, 10),
                        'bulletin' => $node['tipoBoletim'] ?? null,
                        'error' => null,
                    ];
                }

                $lastError = 'Sem cotação publicada em '.$day->format('d/m/Y');
            } catch (Throwable $exception) {
                $lastError = $exception->getMessage();
            }

            $day = $day->copy()->subDay();
        }

        return [
            'currency' => $currency,
            'rate' => null,
            'date' => null,
            'bulletin' => null,
            'error' => $lastError ?? 'PTAX indisponível',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function closingBulletin(array $items): array
    {
        foreach ($items as $item) {
            if (($item['tipoBoletim'] ?? '') === 'Fechamento') {
                return $item;
            }
        }

        return (array) (end($items) ?: []);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function request(string $url, array $query, string $select): \Illuminate\Http\Client\Response
    {
        $response = Http::acceptJson()
            ->timeout((int) config('finance.fees.timeout', 15))
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get($url, $query + ['$format' => 'json', '$select' => $select]);

        if (! $response->successful()) {
            throw new \RuntimeException('BCB respondeu HTTP '.$response->status());
        }

        return $response;
    }
}
