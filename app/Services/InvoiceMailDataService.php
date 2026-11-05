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
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Monta o bloco financeiro usado nos e-mails de fatura.
 *
 * Todos os alertas (vencimento, atraso, pagamento e variação cambial) leem
 * daqui, para que o valor convertido e as tarifas apareçam sempre iguais.
 * O botão do e-mail aponta para a área oficial de pagamento do fornecedor;
 * nenhum endereço é escrito como texto no corpo da mensagem.
 */
class InvoiceMailDataService
{
    public const LICENSEBOY_INVOICES_URL = 'https://licenseboy.com/panel/clientarea.php?action=invoices';

    public function __construct(private readonly OvhService $ovh) {}

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    public function variables(Invoice $invoice, array $extra = []): array
    {
        $invoice->loadMissing('licenseService');

        $amountOriginal = (float) $invoice->amount_original;
        $rate = (float) $invoice->exchange_rate;
        $base = round($amountOriginal * $rate, 2);
        $spread = round((float) $invoice->spread_amount, 2);
        $iof = round((float) $invoice->iof_amount, 2);
        $icms = round((float) $invoice->icms_amount, 2);
        $others = round((float) $invoice->other_taxes_amount, 2);
        $taxBase = round($base + $spread, 2);
        $total = round((float) $invoice->total_brl, 2);

        $panelUrl = route('invoices.show', $invoice);
        $officialUrl = $this->officialUrl($invoice) ?: $panelUrl;

        return array_merge([
            'usuario' => 'equipe financeira',
            'descricao' => (string) $invoice->description,
            'fatura' => (string) ($invoice->external_reference ?: $invoice->description),
            'fornecedor' => $this->supplier($invoice),
            'moeda' => $this->currencyLabel($invoice),
            'vencimento' => $invoice->due_date?->format('d/m/Y') ?? '—',
            'valor_original' => $invoice->currencySymbol().' '.number_format($amountOriginal, 2, ',', '.'),
            'cotacao' => 'R$ '.number_format($rate, 4, ',', '.'),
            'valor_base' => $this->brl($base),
            'spread' => $this->taxLine($spread, $base),
            'iof' => $this->taxLine($iof, $taxBase),
            'icms' => $this->taxLine($icms, $taxBase),
            'outros_tributos' => $this->taxLine($others, $taxBase),
            'total_tributos' => $this->brl($spread + $iof + $icms + $others),
            'total_brl' => $this->brl($total),
            // Compatibilidade com modelos antigos que ainda usam {{ valor }}.
            'valor' => $this->brl($total),
            'link_painel' => $panelUrl,
            'link_oficial' => $officialUrl,
            'link' => $panelUrl,
        ], $extra);
    }

    /**
     * Endereço oficial da fatura. A OVHcloud devolve o link na própria API;
     * quando ele ainda não está gravado, buscamos uma vez e persistimos.
     */
    public function officialUrl(Invoice $invoice): ?string
    {
        $stored = trim((string) $invoice->external_url);

        if ($stored !== '') {
            return $stored;
        }

        $invoice->loadMissing('licenseService');

        if ($invoice->license_service_id !== null) {
            $provider = mb_strtolower(trim((string) $invoice->licenseService?->provider));

            return $provider === '' || str_contains($provider, 'licenseboy')
                ? self::LICENSEBOY_INVOICES_URL
                : null;
        }

        $reference = trim((string) $invoice->external_reference);

        if ($reference === '' || str_starts_with($reference, 'LIC-') || ! $this->ovh->isConfigured()) {
            return null;
        }

        try {
            $url = trim((string) data_get($this->ovh->getBill($reference), 'url', ''));

            if ($url === '') {
                return null;
            }

            $invoice->forceFill(['external_url' => $url])->save();

            return $url;
        } catch (Throwable $exception) {
            Log::info('Link oficial da fatura indisponível na OVHcloud.', [
                'invoice' => $invoice->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function supplier(Invoice $invoice): string
    {
        if ($invoice->license_service_id !== null) {
            $provider = trim((string) $invoice->licenseService?->provider);

            return $provider !== '' ? $provider : 'LicenseBoy';
        }

        $reference = trim((string) $invoice->external_reference);

        return $reference !== '' && ! str_starts_with($reference, 'LIC-')
            ? 'OVHcloud'
            : 'Painel financeiro';
    }

    private function currencyLabel(Invoice $invoice): string
    {
        $code = strtoupper((string) $invoice->currency);
        $label = (string) config('finance.currencies.'.$code.'.label', $code);

        return $label.' ('.$code.')';
    }

    /**
     * "3,50% · R$ 26,99" — o percentual é o efetivamente aplicado na fatura.
     */
    private function taxLine(float $amount, float $base): string
    {
        $percent = $base > 0 ? round($amount / $base * 100, 2) : 0.0;

        return number_format($percent, 2, ',', '.').'% · '.$this->brl($amount);
    }

    private function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
