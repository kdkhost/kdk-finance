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
use App\Models\LicenseCatalogItem;
use App\Models\LicenseService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LicenseBoyPriceService
{
    public const PROVIDER = 'LicenseBoy';

    public const BASE_URL = 'https://licenseboy.com/panel/store/';

    /**
     * Categorias públicas da loja, na ordem exibida no painel.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'control-panel-licenses' => 'Painéis de controle',
        'shared-cloudlinux-licenses' => 'CloudLinux',
        'shared-litespeed-licenses' => 'LiteSpeed',
        'whmcs-license' => 'WHMCS',
        'shared-licenses' => 'Addons e extensões',
        'bundles' => 'Bundles',
        'server-config-and-management' => 'Configuração e gestão',
        'reseller-packages' => 'Revenda',
    ];

    /**
     * Lê todas as categorias e atualiza o catálogo local.
     *
     * Categorias que falharem são preservadas como estão; somente categorias
     * lidas com sucesso têm itens removidos quando deixam de existir na loja.
     *
     * @return array{created: int, updated: int, removed: int, total: int, failed: array<string, string>}
     */
    public function sync(): array
    {
        $created = 0;
        $updated = 0;
        $removed = 0;
        $failed = [];

        foreach (self::CATEGORIES as $category => $label) {
            try {
                $items = $this->fetchCategory($category, $label);
            } catch (Throwable $exception) {
                $failed[$category] = $exception->getMessage();

                Log::warning('Falha ao sincronizar preços da LicenseBoy.', [
                    'category' => $category,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($items === []) {
                $failed[$category] = 'Nenhum produto encontrado na página.';

                continue;
            }

            $seen = [];

            foreach ($items as $item) {
                $seen[] = $item['slug'];

                $existing = LicenseCatalogItem::query()
                    ->where('provider', self::PROVIDER)
                    ->where('slug', $item['slug'])
                    ->first();

                if ($existing) {
                    $existing->fill($item)->save();
                    $updated++;
                } else {
                    LicenseCatalogItem::query()->create($item);
                    $created++;
                }
            }

            $removed += LicenseCatalogItem::query()
                ->where('provider', self::PROVIDER)
                ->where('category', $category)
                ->whereNotIn('slug', $seen)
                ->delete();
        }

        $total = LicenseCatalogItem::query()->where('provider', self::PROVIDER)->count();

        AppSetting::set('license_prices_synced_at', now()->toIso8601String(), 'licenses');
        AppSetting::set('license_prices_sync_summary', json_encode([
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $total,
            'failed' => array_keys($failed),
        ], JSON_UNESCAPED_UNICODE), 'licenses');

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $total,
            'failed' => $failed,
        ];
    }

    /**
     * Aplica o preço atual do catálogo às licenças vinculadas.
     */
    public function applyToLicenses(): int
    {
        $updated = 0;

        LicenseService::query()
            ->whereNotNull('license_catalog_item_id')
            ->with('catalogItem')
            ->each(function (LicenseService $license) use (&$updated): void {
                $item = $license->catalogItem;

                if (! $item) {
                    return;
                }

                $attributes = [];

                if ((float) $license->unit_price !== (float) $item->price) {
                    $attributes['unit_price'] = $item->price;
                }

                if ($license->currency !== $item->currency) {
                    $attributes['currency'] = $item->currency;
                }

                if (
                    $item->billing_cycle
                    && array_key_exists($item->billing_cycle, LicenseService::cycles())
                    && $license->billing_cycle !== $item->billing_cycle
                ) {
                    $attributes['billing_cycle'] = $item->billing_cycle;
                }

                if ($attributes !== []) {
                    $license->forceFill($attributes)->save();
                    $updated++;
                }
            });

        return $updated;
    }

    public function lastSyncedAt(): ?Carbon
    {
        $value = AppSetting::get('license_prices_synced_at');

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCategory(string $category, string $label): array
    {
        $response = Http::withUserAgent('KDK Finance price sync (+https://kdkhost.com.br)')
            ->accept('text/html')
            ->timeout((int) config('finance.exchange.timeout', 12))
            ->retry(2, 400)
            ->withOptions(['verify' => (bool) config('finance.exchange.verify_ssl', true)])
            ->get(self::BASE_URL.$category);

        if (! $response->successful()) {
            throw new RuntimeException('LicenseBoy respondeu HTTP '.$response->status().' em '.$category.'.');
        }

        return $this->parseCategory($response->body(), $category, $label);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCategory(string $html, string $category, string $label): array
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('Não foi possível interpretar o HTML de '.$category.'.');
        }

        $xpath = new DOMXPath($document);
        $cards = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " package ")]');

        if ($cards === false) {
            return [];
        }

        $items = [];
        $seen = [];
        $syncedAt = now();

        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }

            $name = $this->nodeText($xpath, $card, 'package-title');
            $amount = $this->nodeText($xpath, $card, 'price-amount');

            if ($name === '' || $amount === '') {
                continue;
            }

            $url = $this->orderUrl($xpath, $card);
            $slug = $this->slugFromUrl($url, $name);

            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $cycleText = $this->nodeText($xpath, $card, 'price-cycle');
            $startingFrom = $xpath->query('.//*[contains(@class, "price-starting-from")]', $card);

            $items[] = [
                'provider' => self::PROVIDER,
                'external_id' => $card->getAttribute('id') !== '' ? $card->getAttribute('id') : null,
                'category' => $category,
                'category_name' => $label,
                'name' => $name,
                'slug' => $slug,
                'price' => $this->parsePrice($amount),
                'price_is_from' => $startingFrom !== false && $startingFrom->length > 0,
                'currency' => $this->parseCurrency($amount),
                'billing_cycle' => $this->parseCycle($cycleText),
                'setup_fee' => $this->parseSetupFee($cycleText),
                'stock' => $this->parseStock($card->textContent),
                'url' => $url,
                'synced_at' => $syncedAt,
            ];
        }

        return $items;
    }

    private function nodeText(DOMXPath $xpath, DOMElement $card, string $class): string
    {
        $nodes = $xpath->query('.//*[contains(@class, "'.$class.'")]', $card);
        $node = $nodes !== false ? $nodes->item(0) : null;

        if (! $node) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', $node->textContent));
    }

    private function orderUrl(DOMXPath $xpath, DOMElement $card): string
    {
        $nodes = $xpath->query('.//a[contains(@href, "/store/")]', $card);
        $link = $nodes !== false ? $nodes->item(0) : null;

        if (! $link instanceof DOMElement) {
            return '';
        }

        $href = trim($link->getAttribute('href'));

        if ($href === '') {
            return '';
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim('https://licenseboy.com', '/').'/'.ltrim($href, '/');
    }

    private function slugFromUrl(string $url, string $fallback): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $slug = trim(basename($path));

        if ($slug === '' || $slug === 'store' || $slug === '.') {
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $fallback), '-'));
        }

        return $slug;
    }

    private function parsePrice(string $amount): float
    {
        if (stripos($amount, 'FREE') !== false) {
            return 0.0;
        }

        if (preg_match('/\$?\s*([0-9][0-9.,]*)/', $amount, $matches) !== 1) {
            return 0.0;
        }

        return round((float) str_replace(',', '', $matches[1]), 2);
    }

    private function parseCurrency(string $amount): string
    {
        if (preg_match('/\b(USD|EUR|BRL)\b/i', $amount, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'USD';
    }

    private function parseCycle(string $cycleText): ?string
    {
        $text = mb_strtolower($cycleText);

        return match (true) {
            str_contains($text, 'lifetime') => LicenseService::CYCLE_LIFETIME,
            str_contains($text, 'quarter') => LicenseService::CYCLE_QUARTERLY,
            str_contains($text, 'semi') => LicenseService::CYCLE_SEMIANNUAL,
            str_contains($text, 'annual') || str_contains($text, 'year') => LicenseService::CYCLE_ANNUAL,
            str_contains($text, 'month') => LicenseService::CYCLE_MONTHLY,
            default => null,
        };
    }

    private function parseSetupFee(string $cycleText): ?float
    {
        if (preg_match('/\+\s*\$?\s*([0-9][0-9.,]*)\s*setup\s*fee/i', $cycleText, $matches) === 1) {
            return round((float) str_replace(',', '', $matches[1]), 2);
        }

        if (preg_match('/free!?\s*setup\s*fee/i', $cycleText) === 1) {
            return 0.0;
        }

        return null;
    }

    private function parseStock(string $text): ?int
    {
        if (preg_match('/([0-9]+)\s+available/i', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
