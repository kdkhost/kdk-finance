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
use App\Models\LicenseService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Leitura do painel de cliente da LicenseBoy (WHMCS).
 *
 * A LicenseBoy não publica API. O painel autentica por formulário com token
 * CSRF e mantém a sessão em cookie; a leitura é feita somente em GET, sem
 * alterar nada na conta do cliente.
 */
class LicenseBoyPanelService
{
    public const BASE_URL = 'https://licenseboy.com/panel';

    private ?CookieJar $cookies = null;

    private bool $authenticated = false;

    public function isConfigured(): bool
    {
        return trim((string) AppSetting::get('licenseboy_panel_email', '')) !== ''
            && trim((string) AppSetting::get('licenseboy_panel_password', '')) !== '';
    }

    /**
     * @return list<array{service_id: string, product: string, price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}>
     */
    public function services(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Informe o e-mail e a senha do painel da LicenseBoy em Configurações.');
        }

        $this->login();

        $html = $this->get('/clientarea.php?action=services')->body();
        $xpath = $this->xpath($html);

        $rows = $xpath->query("//table[contains(@id,'ServicesList')]//tbody/tr");

        if ($rows === false || $rows->length === 0) {
            $rows = $xpath->query("//table//tr[.//a[contains(@href,'action=productdetails')]]");
        }

        $services = [];

        foreach ($rows ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $link = $xpath->query(".//a[contains(@href,'action=productdetails')]", $row)->item(0);

            if (! $link instanceof DOMElement) {
                continue;
            }

            parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $id = (string) ($query['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $product = trim(preg_replace('/\s+/', ' ', $row->textContent) ?? '');

            $services[] = array_merge(
                ['service_id' => $id, 'product' => $product],
                $this->details($id),
            );
        }

        return $services;
    }

    /**
     * @return array{price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}
     */
    public function details(string $serviceId): array
    {
        $html = $this->get('/clientarea.php?action=productdetails&id='.rawurlencode($serviceId))->body();
        $xpath = $this->xpath($html);

        $price = $this->infoValue($xpath, 'Valor Reincidente');
        $currency = 'USD';

        if (preg_match('/[A-Z]{3}/', (string) $price, $match)) {
            $currency = $match[0];
        }

        return [
            'price' => $this->money($price),
            'currency' => $currency,
            'cycle' => $this->cycle($this->infoValue($xpath, 'Ciclo de Pagamento')),
            'next_due' => $this->date($this->infoValue($xpath, 'Próximo Vencimento')),
            'status' => $this->status($this->infoValue($xpath, 'Status')),
        ];
    }

    /**
     * Compara o nome local da licença com o produto do painel e grava o que casar.
     *
     * @param  list<array{service_id: string, product: string, price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}>  $services
     * @return array{updated: int, unmatched: list<string>}
     */
    public function apply(array $services): array
    {
        $updated = 0;
        $unmatched = [];

        $licenses = LicenseService::query()->get();

        foreach ($licenses as $license) {
            $match = $this->match($license, $services);

            if (! $match) {
                $unmatched[] = $license->name ?: $license->product;

                continue;
            }

            $license->fill([
                'panel_service_id' => $match['service_id'],
                'panel_price' => $match['price'] > 0
                    ? number_format($match['price'], 2, '.', '')
                    : $license->panel_price,
                'panel_currency' => $match['price'] > 0 ? $match['currency'] : $license->panel_currency,
                'panel_billing_cycle' => $match['cycle'] ?? $license->panel_billing_cycle,
                'panel_next_due_date' => $match['next_due'] ?? $license->panel_next_due_date,
                'panel_status' => $match['status'] ?? $license->panel_status,
                'panel_synced_at' => now(),
            ]);

            if ($match['status'] === LicenseService::STATUS_ACTIVE) {
                $license->status = LicenseService::STATUS_ACTIVE;
            } elseif ($match['status'] !== null && in_array($match['status'], [LicenseService::STATUS_EXPIRED, LicenseService::STATUS_CANCELED], true)) {
                $license->status = $match['status'];
            }

            if ($match['next_due']) {
                $license->next_due_date = $match['next_due'];
            }

            $license->save();
            $updated++;
        }

        AppSetting::set('licenseboy_panel_synced_at', now()->toIso8601String(), 'licenseboy');

        return ['updated' => $updated, 'unmatched' => $unmatched];
    }

    /**
     * @param  list<array{service_id: string, product: string, price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}>  $services
     * @return array{service_id: string, product: string, price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}|null
     */
    private function match(LicenseService $license, array $services): ?array
    {
        if ($license->panel_service_id) {
            foreach ($services as $service) {
                if ($service['service_id'] === (string) $license->panel_service_id) {
                    return $service;
                }
            }
        }

        // Primeiro tenta o nome completo (normalmente inclui produto + servidor).
        $byName = $this->candidates($services, $this->normalize((string) $license->name));

        if (count($byName) === 1) {
            return $byName[0];
        }

        // Sem correspondência única pelo nome, tenta o produto — mas só quando
        // houver exatamente um serviço com aquele produto, para não trocar valores.
        return $this->candidates($services, $this->normalize((string) $license->product))[0] ?? null;
    }

    /**
     * @param  list<array{product: string}>  $services
     * @return list<array{service_id: string, product: string, price: float, currency: string, cycle: ?string, next_due: ?string, status: ?string}>
     */
    private function candidates(array $services, string $needle): array
    {
        if ($needle === '') {
            return [];
        }

        $matches = array_values(array_filter(
            $services,
            fn (array $service): bool => str_contains($this->normalize($service['product']), $needle),
        ));

        if (count($matches) > 1) {
            // Vários históricos do mesmo produto: a licença ativa é a cobrança atual.
            $active = array_values(array_filter(
                $matches,
                fn (array $service): bool => ($service['status'] ?? null) === LicenseService::STATUS_ACTIVE,
            ));

            if (count($active) === 1) {
                return $active;
            }
        }

        return count($matches) === 1 ? $matches : [];
    }

    private function login(): void
    {
        if ($this->authenticated) {
            return;
        }

        $this->cookies = new CookieJar();

        $page = $this->client()->get(self::BASE_URL.'/login');

        if (! $page->successful()) {
            throw new RuntimeException('O painel da LicenseBoy não respondeu (HTTP '.$page->status().').');
        }

        if (! preg_match('/name="token"\s+value="([^"]+)"/i', $page->body(), $token)) {
            throw new RuntimeException('Não foi possível localizar o token de login do painel da LicenseBoy.');
        }

        $response = $this->client()->asForm()->post(self::BASE_URL.'/login', [
            'token' => $token[1],
            'username' => (string) AppSetting::get('licenseboy_panel_email', ''),
            'password' => (string) AppSetting::get('licenseboy_panel_password', ''),
            'rememberme' => 'on',
        ]);

        $body = $response->body();

        // Quando as credenciais são recusadas, o WHMCS reexibe o formulário
        // de login (com os campos de senha); o painel autenticado não os tem.
        $stillOnLogin = str_contains($body, 'name="password"') && str_contains($body, 'name="username"');

        if (! $response->successful() || $stillOnLogin || ! str_contains($body, 'clientarea')) {
            throw new RuntimeException('E-mail ou senha do painel da LicenseBoy foram recusados.');
        }

        $this->authenticated = true;
    }

    private function get(string $path): Response
    {
        $response = $this->client()->get(self::BASE_URL.$path);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao ler '.$path.' no painel da LicenseBoy (HTTP '.$response->status().').');
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $this->cookies ??= new CookieJar(),
            'verify' => (bool) config('finance.exchange.verify_ssl', true),
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
        ])->timeout(25);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function infoValue(DOMXPath $xpath, string $label): ?string
    {
        $node = $xpath->query(
            "//li[span[contains(@class,'list-info-title') and normalize-space(.)='".$label."']]/span[contains(@class,'list-info-text')]"
        )->item(0);

        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '') : null;
    }

    private function money(?string $value): float
    {
        if (! $value || ! preg_match('/[\d.,]+/', $value, $match)) {
            return 0.0;
        }

        $raw = $match[0];

        // Padrão brasileiro usa vírgula decimal (1.234,56); o painel também
        // exibe dólar com ponto decimal (7.99), que não deve virar 799.
        $number = str_contains($raw, ',')
            ? str_replace(['.', ','], ['', '.'], $raw)
            : str_replace(',', '', $raw);

        return round((float) $number, 2);
    }

    private function cycle(?string $label): ?string
    {
        $label = mb_strtolower((string) $label);

        return match (true) {
            str_contains($label, 'trimestre') => LicenseService::CYCLE_QUARTERLY,
            str_contains($label, 'semestre') => LicenseService::CYCLE_SEMIANNUAL,
            str_contains($label, 'ano') || str_contains($label, 'anual') => LicenseService::CYCLE_ANNUAL,
            str_contains($label, 'vital') => LicenseService::CYCLE_LIFETIME,
            str_contains($label, 'mensal') || str_contains($label, 'mês') || str_contains($label, 'mes') => LicenseService::CYCLE_MONTHLY,
            default => null,
        };
    }

    private function status(?string $label): ?string
    {
        $label = mb_strtolower((string) $label);

        return match (true) {
            str_contains($label, 'ativo') => LicenseService::STATUS_ACTIVE,
            str_contains($label, 'suspens'), str_contains($label, 'expirad') => LicenseService::STATUS_EXPIRED,
            str_contains($label, 'cancelad') => LicenseService::STATUS_CANCELED,
            default => null,
        };
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (! preg_match('/,\s*([[:alpha:]çãéíóúâêô]+)\s+(\d{1,2})[º°]?,\s*(\d{4})/u', $value, $match)) {
            return null;
        }

        $months = [
            'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3, 'abril' => 4,
            'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8, 'setembro' => 9,
            'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
        ];

        $month = $months[mb_strtolower($match[1])] ?? null;

        if (! $month) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $match[3], $month, (int) $match[2]);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
