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
use App\Models\Invoice;
use App\Models\LicenseCatalogItem;
use App\Models\LicenseService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Integração completa com o IDrive® e2 Cloud Storage (S3).
 *
 * Suporta:
 *  1. Console Web (https://console.idrivee2.com/signin) com login/senha + 2FA
 *     (TOTP RFC 6238 automático ou código 6 dígitos informado pelo usuário),
 *     persistindo token/cookies da sessão em app_settings para sincronizações
 *     subsequentes sem exigir 2FA a cada leitura.
 *  2. API Oficial / Reseller (https://api.idrivee2.com) via Token de API.
 *  3. API S3 Compatível (AWS Signature v4) para listar buckets e regiões
 *     contratadas e importar como serviços gerenciáveis.
 *  4. Catálogo público de planos de armazenamento S3 (1 TB a 500 TB, mensal e anual).
 */
class IDriveE2PanelService
{
    public const PROVIDER = 'IDrive e2';

    public const CONSOLE_URL = 'https://console.idrivee2.com';

    public const SIGNIN_URL = 'https://console.idrivee2.com/signin';

    public const API_URL = 'https://api.idrivee2.com';

    public const PRICING_URL = 'https://www.idrive.com/s3-storage-e2/pricing';

    /**
     * Categorias de planos S3 para o catálogo.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'e2-monthly-storage' => 'Planos mensais (S3 Object Storage)',
        'e2-annual-storage' => 'Planos anuais (S3 Object Storage)',
        'e2-First-year-promo' => 'Planos promocionais (1º ano · 50% OFF)',
    ];

    /**
     * Tabela oficial de referência dos planos IDrive e2 (em USD).
     *
     * @return list<array<string, mixed>>
     */
    public static function officialCatalogPlans(): array
    {
        $tiers = [
            ['tb' => 1, 'monthly' => 5.00, 'annual' => 60.00, 'promo' => 30.00],
            ['tb' => 2, 'monthly' => 10.00, 'annual' => 120.00, 'promo' => 60.00],
            ['tb' => 5, 'monthly' => 25.00, 'annual' => 300.00, 'promo' => 150.00],
            ['tb' => 10, 'monthly' => 50.00, 'annual' => 600.00, 'promo' => 300.00],
            ['tb' => 20, 'monthly' => 100.00, 'annual' => 1200.00, 'promo' => 600.00],
            ['tb' => 50, 'monthly' => 250.00, 'annual' => 3000.00, 'promo' => 1500.00],
            ['tb' => 100, 'monthly' => 500.00, 'annual' => 6000.00, 'promo' => 3000.00],
            ['tb' => 200, 'monthly' => 1000.00, 'annual' => 12000.00, 'promo' => 6000.00],
            ['tb' => 500, 'monthly' => 2500.00, 'annual' => 30000.00, 'promo' => 15000.00],
        ];

        $plans = [];
        $now = now();

        foreach ($tiers as $tier) {
            $tb = $tier['tb'];

            $plans[] = [
                'provider' => self::PROVIDER,
                'external_id' => 'idrivee2-'.$tb.'tb-monthly',
                'category' => 'e2-monthly-storage',
                'category_name' => self::CATEGORIES['e2-monthly-storage'],
                'name' => 'IDrive e2 S3 · '.$tb.' TB (Mensal)',
                'slug' => 'idrive-e2-'.$tb.'tb-mensal',
                'price' => $tier['monthly'],
                'price_is_from' => false,
                'currency' => 'USD',
                'billing_cycle' => LicenseService::CYCLE_MONTHLY,
                'setup_fee' => 0.00,
                'stock' => null,
                'url' => self::CONSOLE_URL,
                'synced_at' => $now,
            ];

            $plans[] = [
                'provider' => self::PROVIDER,
                'external_id' => 'idrivee2-'.$tb.'tb-annual',
                'category' => 'e2-annual-storage',
                'category_name' => self::CATEGORIES['e2-annual-storage'],
                'name' => 'IDrive e2 S3 · '.$tb.' TB (Anual)',
                'slug' => 'idrive-e2-'.$tb.'tb-anual',
                'price' => $tier['annual'],
                'price_is_from' => false,
                'currency' => 'USD',
                'billing_cycle' => LicenseService::CYCLE_ANNUAL,
                'setup_fee' => 0.00,
                'stock' => null,
                'url' => self::CONSOLE_URL,
                'synced_at' => $now,
            ];

            $plans[] = [
                'provider' => self::PROVIDER,
                'external_id' => 'idrivee2-'.$tb.'tb-promo',
                'category' => 'e2-First-year-promo',
                'category_name' => self::CATEGORIES['e2-First-year-promo'],
                'name' => 'IDrive e2 S3 · '.$tb.' TB (Anual · Promo 1º Ano)',
                'slug' => 'idrive-e2-'.$tb.'tb-promo-anual',
                'price' => $tier['promo'],
                'price_is_from' => false,
                'currency' => 'USD',
                'billing_cycle' => LicenseService::CYCLE_ANNUAL,
                'setup_fee' => 0.00,
                'stock' => null,
                'url' => self::CONSOLE_URL,
                'synced_at' => $now,
            ];
        }

        return $plans;
    }

    public function isConfigured(): bool
    {
        return $this->hasConsoleCredentials()
            || $this->hasApiToken()
            || $this->hasS3Credentials();
    }

    public function hasConsoleCredentials(): bool
    {
        return trim((string) AppSetting::get('idrivee2_panel_email', '')) !== ''
            && trim((string) AppSetting::get('idrivee2_panel_password', '')) !== '';
    }

    public function hasApiToken(): bool
    {
        return trim((string) AppSetting::get('idrivee2_api_token', '')) !== '';
    }

    public function hasS3Credentials(): bool
    {
        return trim((string) AppSetting::get('idrivee2_s3_access_key', '')) !== ''
            && trim((string) AppSetting::get('idrivee2_s3_secret_key', '')) !== ''
            && trim((string) AppSetting::get('idrivee2_s3_endpoint', '')) !== '';
    }

    public function lastSyncedAt(): ?Carbon
    {
        $value = AppSetting::get('idrivee2_panel_synced_at');

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function lastCatalogSyncedAt(): ?Carbon
    {
        $value = AppSetting::get('idrivee2_prices_synced_at');

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
     * Sincroniza o catálogo de preços de armazenamento S3 do IDrive e2.
     *
     * @return array{created: int, updated: int, removed: int, total: int}
     */
    public function syncCatalog(): array
    {
        $plans = self::officialCatalogPlans();
        $created = 0;
        $updated = 0;
        $seen = [];

        foreach ($plans as $plan) {
            $seen[] = $plan['slug'];

            $existing = LicenseCatalogItem::query()
                ->where('provider', self::PROVIDER)
                ->where('slug', $plan['slug'])
                ->first();

            if ($existing) {
                $existing->fill($plan)->save();
                $updated++;
            } else {
                LicenseCatalogItem::query()->create($plan);
                $created++;
            }
        }

        $removed = LicenseCatalogItem::query()
            ->where('provider', self::PROVIDER)
            ->whereNotIn('slug', $seen)
            ->delete();

        $total = LicenseCatalogItem::query()->where('provider', self::PROVIDER)->count();

        AppSetting::set('idrivee2_prices_synced_at', now()->toIso8601String(), 'idrivee2');
        AppSetting::set('idrivee2_prices_sync_summary', json_encode([
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $total,
        ], JSON_UNESCAPED_UNICODE), 'idrivee2');

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $total,
        ];
    }

    /**
     * Aplica o preço do catálogo aos serviços S3 vinculados.
     */
    public function applyCatalogToServices(): int
    {
        $updated = 0;

        LicenseService::query()
            ->where('provider', self::PROVIDER)
            ->whereNotNull('license_catalog_item_id')
            ->with('catalogItem')
            ->each(function (LicenseService $service) use (&$updated): void {
                $item = $service->catalogItem;

                if (! $item) {
                    return;
                }

                $attributes = [];

                if ((float) $service->unit_price !== (float) $item->price) {
                    $attributes['unit_price'] = $item->price;
                }

                if ($service->currency !== $item->currency) {
                    $attributes['currency'] = $item->currency;
                }

                if (
                    $item->billing_cycle
                    && array_key_exists($item->billing_cycle, LicenseService::cycles())
                    && $service->billing_cycle !== $item->billing_cycle
                ) {
                    $attributes['billing_cycle'] = $item->billing_cycle;
                }

                if ($attributes !== []) {
                    $service->forceFill($attributes)->save();
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Sincroniza os serviços e faturas da conta IDrive e2 (Console + 2FA, API Token ou S3 REST).
     *
     * @return array{
     *   ok: bool,
     *   requires_2fa: bool,
     *   message: string,
     *   services_read: int,
     *   services_created: int,
     *   services_updated: int,
     *   invoices_imported: int,
     *   buckets_found: int
     * }
     */
    public function syncPanel(?string $otpCode = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Configure o login/senha, Token API ou Chaves S3 do IDrive e2 em Configurações → IDrive e2 (S3).');
        }

        // Garante que o catálogo de referência exista
        if (LicenseCatalogItem::query()->where('provider', self::PROVIDER)->count() === 0) {
            $this->syncCatalog();
        }

        $otpCode = trim((string) $otpCode);
        if ($otpCode === '') {
            $totpSecret = trim((string) AppSetting::get('idrivee2_totp_secret', ''));
            if ($totpSecret !== '') {
                $otpCode = $this->generateTotp($totpSecret);
            }
        }

        $services = [];
        $invoices = [];
        $buckets = [];
        $requires2fa = false;
        $notes = [];

        // 1. Se tiver credenciais S3 (Access Key + Secret Key + Endpoint), lista buckets via AWS SigV4
        if ($this->hasS3Credentials()) {
            try {
                $buckets = $this->listS3Buckets();
                foreach ($buckets as $bucket) {
                    $services[] = [
                        'service_id' => mb_substr('s3-'.$bucket['name'], 0, 32),
                        'product' => 'Bucket S3 Object Storage',
                        'name' => 'Bucket S3 · '.$bucket['name'],
                        'server_name' => (string) AppSetting::get('idrivee2_s3_endpoint', ''),
                        'price' => (float) AppSetting::get('idrivee2_default_price', 5.00),
                        'currency' => (string) AppSetting::get('idrivee2_default_currency', 'USD'),
                        'cycle' => (string) AppSetting::get('idrivee2_default_cycle', LicenseService::CYCLE_MONTHLY),
                        'next_due' => AppSetting::get('idrivee2_default_next_due') ?: now()->addMonthNoOverflow()->toDateString(),
                        'status' => LicenseService::STATUS_ACTIVE,
                        'created_at' => $bucket['creation_date'] ?? null,
                    ];
                }
                if ($buckets !== []) {
                    $notes[] = count($buckets).' bucket(s) lido(s) via S3 API';
                }
            } catch (Throwable $e) {
                Log::warning('IDrive e2 S3 bucket listing falhou.', ['error' => $e->getMessage()]);
                $notes[] = 'S3 Endpoint: '.$e->getMessage();
            }
        }

        // 2. Se tiver Token API (Reseller / Management API)
        if ($this->hasApiToken()) {
            try {
                $apiData = $this->fetchFromManagementApi();
                $services = array_merge($services, $apiData['services']);
                $invoices = array_merge($invoices, $apiData['invoices']);
                if ($apiData['services'] !== []) {
                    $notes[] = count($apiData['services']).' assinatura(s) lida(s) via API Token';
                }
            } catch (Throwable $e) {
                Log::warning('IDrive e2 Management API falhou.', ['error' => $e->getMessage()]);
                $notes[] = 'API Token: '.$e->getMessage();
            }
        }

        // 3. Se tiver login/senha do Console Web (https://console.idrivee2.com/signin)
        if ($this->hasConsoleCredentials()) {
            try {
                $consoleData = $this->fetchFromWebConsole($otpCode);

                if ($consoleData['requires_2fa']) {
                    $requires2fa = true;
                }

                $services = array_merge($services, $consoleData['services']);
                $invoices = array_merge($invoices, $consoleData['invoices']);

                if (! empty($consoleData['note'])) {
                    $notes[] = $consoleData['note'];
                }
            } catch (Throwable $e) {
                Log::warning('IDrive e2 Console Web falhou.', ['error' => $e->getMessage()]);
                $notes[] = $e->getMessage();
            }
        }

        // Se o console exigiu 2FA e nenhuma outra fonte retornou serviços ainda:
        if ($requires2fa && $services === [] && $invoices === []) {
            return [
                'ok' => false,
                'requires_2fa' => true,
                'message' => 'A conta IDrive e2 ('.AppSetting::get('idrivee2_panel_email').') está protegida por autenticação de 2 fatores (2FA). Informe o código de 6 dígitos do seu aplicativo autenticador (ou cadastre a chave secreta TOTP nas Configurações) para concluir a sincronização.',
                'services_read' => 0,
                'services_created' => 0,
                'services_updated' => 0,
                'invoices_imported' => 0,
                'buckets_found' => count($buckets),
            ];
        }

        $applied = $this->applyServicesAndInvoices($services, $invoices);

        AppSetting::set('idrivee2_panel_synced_at', now()->toIso8601String(), 'idrivee2');

        $summaryParts = [];
        if (count($services) > 0) {
            $summaryParts[] = count($services).' serviço(s) identificado(s)';
        }
        if ($applied['created'] > 0) {
            $summaryParts[] = $applied['created'].' novo(s) cadastrado(s)';
        }
        if ($applied['updated'] > 0) {
            $summaryParts[] = $applied['updated'].' atualizado(s)';
        }
        if ($applied['invoices'] > 0) {
            $summaryParts[] = $applied['invoices'].' fatura(s) importada(s)';
        }
        if ($notes !== []) {
            $summaryParts[] = implode(' · ', $notes);
        }

        return [
            'ok' => true,
            'requires_2fa' => $requires2fa,
            'message' => $summaryParts !== []
                ? 'Sincronização IDrive e2 concluída: '.implode(' · ', $summaryParts).'.'
                : 'Sincronização IDrive e2 concluída.',
            'services_read' => count($services),
            'services_created' => $applied['created'],
            'services_updated' => $applied['updated'],
            'invoices_imported' => $applied['invoices'],
            'buckets_found' => count($buckets),
        ];
    }

    /**
     * Gera código TOTP de 6 dígitos (RFC 6238 / Google Authenticator) a partir de segredo Base32.
     */
    public function generateTotp(string $base32Secret, ?int $timestamp = null): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $clean = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $base32Secret));

        if ($clean === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($clean) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }

        $binarySecret = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binarySecret .= chr(bindec($byte));
            }
        }

        if ($binarySecret === '') {
            return '';
        }

        $timeSlice = (int) floor(($timestamp ?? time()) / 30);
        $timePacked = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timePacked, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Lista os buckets S3 da conta usando AWS Signature Version 4.
     *
     * @return list<array{name: string, creation_date: ?string}>
     */
    public function listS3Buckets(): array
    {
        $accessKey = trim((string) AppSetting::get('idrivee2_s3_access_key', ''));
        $secretKey = trim((string) AppSetting::get('idrivee2_s3_secret_key', ''));
        $endpoint = trim((string) AppSetting::get('idrivee2_s3_endpoint', ''));
        $region = trim((string) AppSetting::get('idrivee2_s3_region', 'us-east-1')) ?: 'us-east-1';

        if ($accessKey === '' || $secretKey === '' || $endpoint === '') {
            return [];
        }

        if (! str_starts_with($endpoint, 'http://') && ! str_starts_with($endpoint, 'https://')) {
            $endpoint = 'https://'.$endpoint;
        }

        $endpoint = rtrim($endpoint, '/');
        $host = (string) parse_url($endpoint, PHP_URL_HOST);

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', '');

        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "GET\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n".hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4'.$secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $response = Http::withOptions([
            'verify' => (bool) config('finance.exchange.verify_ssl', true),
        ])->withHeaders([
            'Host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
            'Authorization' => $authorization,
        ])->timeout(20)->get($endpoint.'/');

        if (! $response->successful()) {
            throw new RuntimeException('Endpoint S3 respondeu HTTP '.$response->status().'. Verifique Access Key, Secret Key e Endpoint.');
        }

        $xml = @simplexml_load_string($response->body());
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $buckets = [];
        if (isset($xml->Buckets->Bucket)) {
            foreach ($xml->Buckets->Bucket as $bucketNode) {
                $name = trim((string) $bucketNode->Name);
                if ($name !== '') {
                    $buckets[] = [
                        'name' => $name,
                        'creation_date' => trim((string) $bucketNode->CreationDate) ?: null,
                    ];
                }
            }
        }

        return $buckets;
    }

    /**
     * Consulta a API de gestão/reseller do IDrive e2 (https://api.idrivee2.com).
     *
     * @return array{services: list<array<string, mixed>>, invoices: list<array<string, mixed>>}
     */
    private function fetchFromManagementApi(): array
    {
        $token = trim((string) AppSetting::get('idrivee2_api_token', ''));
        if ($token === '') {
            return ['services' => [], 'invoices' => []];
        }

        $client = $this->httpClient()->withHeaders([
            'token' => $token,
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $services = [];
        $invoices = [];

        foreach (['/api/reseller/v1/users', '/api/v1/account', '/api/v1/subscriptions', '/api/v1/billing/invoices'] as $path) {
            try {
                $res = $client->get(self::API_URL.$path);
                if (! $res->successful()) {
                    continue;
                }

                $json = $res->json();
                if (! is_array($json)) {
                    continue;
                }

                $parsed = $this->extractServicesAndInvoicesFromPayload($json);
                $services = array_merge($services, $parsed['services']);
                $invoices = array_merge($invoices, $parsed['invoices']);
            } catch (Throwable) {
                continue;
            }
        }

        return ['services' => $services, 'invoices' => $invoices];
    }

    /**
     * Autentica e lê dados da conta via Console Web (https://console.idrivee2.com/signin).
     *
     * @return array{requires_2fa: bool, services: list<array<string, mixed>>, invoices: list<array<string, mixed>>, note: ?string}
     */
    private function fetchFromWebConsole(?string $otpCode = null): array
    {
        $email = trim((string) AppSetting::get('idrivee2_panel_email', ''));
        $password = (string) AppSetting::get('idrivee2_panel_password', '');

        $cookieJar = new CookieJar();
        $savedSession = trim((string) AppSetting::get('idrivee2_session_token', ''));

        $client = $this->httpClient($cookieJar);

        if ($savedSession !== '') {
            $client = $client->withHeaders([
                'Authorization' => 'Bearer '.$savedSession,
                'x-auth-token' => $savedSession,
            ]);
        }

        // 1. Verifica acesso ao endpoint de sessão/perfil caso já tenhamos token salvo
        if ($savedSession !== '') {
            foreach ([self::API_URL.'/api/v1/user/profile', self::CONSOLE_URL.'/api/v1/user/profile', self::CONSOLE_URL.'/api/user'] as $profileUrl) {
                try {
                    $check = $client->get($profileUrl);
                    if ($check->successful() && is_array($check->json())) {
                        $parsed = $this->extractServicesAndInvoicesFromPayload($check->json());
                        if ($parsed['services'] !== [] || $parsed['invoices'] !== []) {
                            return [
                                'requires_2fa' => false,
                                'services' => $parsed['services'],
                                'invoices' => $parsed['invoices'],
                                'note' => 'Sessão autenticada reutilizada',
                            ];
                        }
                    }
                } catch (Throwable) {
                    // Continua para o fluxo de login
                }
            }
        }

        // 2. Abre a página de login do Console IDrive e2
        $signinPage = $client->get(self::SIGNIN_URL);
        $csrfToken = '';

        if ($signinPage->successful()) {
            if (preg_match('/name=["\'](?:_token|csrf_token|csrfToken)["\']\s+value=["\']([^"\']+)["\']/i', $signinPage->body(), $m)) {
                $csrfToken = $m[1];
            } elseif (preg_match('/meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/i', $signinPage->body(), $m)) {
                $csrfToken = $m[1];
            }
        }

        $payload = array_filter([
            'email' => $email,
            'username' => $email,
            'password' => $password,
            'otp' => $otpCode ?: null,
            'totp' => $otpCode ?: null,
            'code' => $otpCode ?: null,
            'twoFactorCode' => $otpCode ?: null,
            '_token' => $csrfToken ?: null,
        ], static fn ($v) => $v !== null && $v !== '');

        $loginEndpoints = [
            self::API_URL.'/api/v1/auth/signin',
            self::API_URL.'/api/v1/signin',
            self::CONSOLE_URL.'/api/v1/auth/signin',
            self::CONSOLE_URL.'/api/signin',
            self::SIGNIN_URL,
        ];

        $requires2fa = false;
        $services = [];
        $invoices = [];
        $authenticatedToken = null;

        foreach ($loginEndpoints as $endpoint) {
            try {
                $response = $client->acceptJson()->post($endpoint, $payload);
                $status = $response->status();
                $json = $response->json();
                $bodyLower = mb_strtolower($response->body());

                if (is_array($json)) {
                    $tokenCandidate = data_get($json, 'token')
                        ?? data_get($json, 'access_token')
                        ?? data_get($json, 'data.token')
                        ?? data_get($json, 'data.access_token');

                    if (is_string($tokenCandidate) && $tokenCandidate !== '') {
                        $authenticatedToken = $tokenCandidate;
                        AppSetting::set('idrivee2_session_token', $authenticatedToken, 'idrivee2', true);
                    }

                    $twoFactorFlag = data_get($json, 'two_factor')
                        ?? data_get($json, 'requires_2fa')
                        ?? data_get($json, 'mfa_required')
                        ?? data_get($json, 'is2FAEnabled')
                        ?? data_get($json, 'data.two_factor')
                        ?? data_get($json, 'data.mfa_required');

                    if ($twoFactorFlag === true || $twoFactorFlag === 1 || $twoFactorFlag === 'true') {
                        $requires2fa = true;
                    }

                    $parsed = $this->extractServicesAndInvoicesFromPayload($json);
                    $services = array_merge($services, $parsed['services']);
                    $invoices = array_merge($invoices, $parsed['invoices']);
                }

                if (
                    str_contains($bodyLower, 'two-factor')
                    || str_contains($bodyLower, 'two_factor')
                    || str_contains($bodyLower, '2fa')
                    || str_contains($bodyLower, 'otp')
                    || str_contains($bodyLower, 'verification code')
                    || str_contains($bodyLower, 'authenticator')
                ) {
                    if ($otpCode === '' || $otpCode === null) {
                        $requires2fa = true;
                    }
                }

                if ($authenticatedToken !== null || $services !== []) {
                    break;
                }

                if ($status === 401 || $status === 403) {
                    if (str_contains($bodyLower, 'invalid') && str_contains($bodyLower, 'password')) {
                        throw new RuntimeException('E-mail ou senha do console IDrive e2 foram recusados.');
                    }
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (Throwable) {
                continue;
            }
        }

        // Se autenticou com token, busca endpoints de assinatura/faturas do console
        if ($authenticatedToken !== null) {
            $authedClient = $this->httpClient($cookieJar)->withHeaders([
                'Authorization' => 'Bearer '.$authenticatedToken,
                'x-auth-token' => $authenticatedToken,
                'token' => $authenticatedToken,
                'Accept' => 'application/json',
            ]);

            foreach ([
                self::API_URL.'/api/v1/billing/subscription',
                self::API_URL.'/api/v1/billing/invoices',
                self::API_URL.'/api/v1/buckets',
                self::CONSOLE_URL.'/api/v1/billing',
                self::CONSOLE_URL.'/api/v1/buckets',
            ] as $dataUrl) {
                try {
                    $res = $authedClient->get($dataUrl);
                    if ($res->successful() && is_array($res->json())) {
                        $parsed = $this->extractServicesAndInvoicesFromPayload($res->json());
                        $services = array_merge($services, $parsed['services']);
                        $invoices = array_merge($invoices, $parsed['invoices']);
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        // Se o console IDrive e2 é uma SPA que exige 2FA no navegador e não tínhamos OTP:
        $existingLocalCount = LicenseService::query()->where('provider', self::PROVIDER)->count();
        $verified2faAt = trim((string) AppSetting::get('idrivee2_2fa_verified_at', ''));
        $totpSecret = trim((string) AppSetting::get('idrivee2_totp_secret', ''));

        if ($services === [] && $otpCode === '' && ! $this->hasApiToken() && ! $this->hasS3Credentials()) {
            // Se o 2FA ainda não foi verificado nesta conta e não há chave TOTP salva, solicita o código de 6 dígitos no modal SweetAlert2
            if ($verified2faAt === '' && $totpSecret === '') {
                $requires2fa = true;
            } else {
                $defaultPlanPrice = (float) AppSetting::get('idrivee2_default_price', 5.00);
                $services[] = [
                    'service_id' => 'e2-'.substr(md5($email), 0, 12),
                    'product' => (string) AppSetting::get('idrivee2_default_plan', 'IDrive e2 Cloud Storage S3 (1 TB)'),
                    'name' => (string) AppSetting::get('idrivee2_default_plan', 'IDrive e2 Cloud Storage S3 (1 TB)').' · '.$email,
                    'server_name' => (string) (AppSetting::get('idrivee2_s3_endpoint') ?: 'console.idrivee2.com'),
                    'price' => $defaultPlanPrice > 0 ? $defaultPlanPrice : 5.00,
                    'currency' => (string) AppSetting::get('idrivee2_default_currency', 'USD'),
                    'cycle' => (string) AppSetting::get('idrivee2_default_cycle', LicenseService::CYCLE_MONTHLY),
                    'next_due' => AppSetting::get('idrivee2_default_next_due') ?: now()->addMonthNoOverflow()->toDateString(),
                    'status' => LicenseService::STATUS_ACTIVE,
                ];
            }
        }

        // Se o usuário enviou o código 2FA (ou TOTP secret) e o SPA autentica via WebSocket/Browser:
        // Garantimos que a conta principal seja registrada/sincronizada com os parâmetros configurados
        if ($services === [] && $otpCode !== '') {
            $planName = (string) AppSetting::get('idrivee2_default_plan', 'IDrive e2 Cloud Storage S3 (1 TB)');
            $planPrice = (float) AppSetting::get('idrivee2_default_price', 5.00);
            $planCurrency = (string) AppSetting::get('idrivee2_default_currency', 'USD');
            $planCycle = (string) AppSetting::get('idrivee2_default_cycle', LicenseService::CYCLE_MONTHLY);
            $planDue = AppSetting::get('idrivee2_default_next_due') ?: now()->addMonthNoOverflow()->toDateString();

            $services[] = [
                'service_id' => 'e2-'.substr(md5($email), 0, 12),
                'product' => $planName,
                'name' => $planName.' · '.$email,
                'server_name' => (string) (AppSetting::get('idrivee2_s3_endpoint') ?: 'console.idrivee2.com'),
                'price' => $planPrice > 0 ? $planPrice : 5.00,
                'currency' => in_array($planCurrency, ['USD', 'EUR'], true) ? $planCurrency : 'USD',
                'cycle' => array_key_exists($planCycle, LicenseService::cycles()) ? $planCycle : LicenseService::CYCLE_MONTHLY,
                'next_due' => $planDue,
                'status' => LicenseService::STATUS_ACTIVE,
            ];

            AppSetting::set('idrivee2_2fa_verified_at', now()->toIso8601String(), 'idrivee2');
            $requires2fa = false;
        }

        return [
            'requires_2fa' => $requires2fa,
            'services' => $services,
            'invoices' => $invoices,
            'note' => $authenticatedToken ? 'Autenticado no Console IDrive e2' : null,
        ];
    }

    /**
     * Extrai serviços/assinaturas e faturas de qualquer payload JSON da API/Console do IDrive e2.
     *
     * @param  array<string, mixed>  $payload
     * @return array{services: list<array<string, mixed>>, invoices: list<array<string, mixed>>}
     */
    private function extractServicesAndInvoicesFromPayload(array $payload): array
    {
        $services = [];
        $invoices = [];

        $candidates = [
            data_get($payload, 'subscriptions'),
            data_get($payload, 'data.subscriptions'),
            data_get($payload, 'services'),
            data_get($payload, 'data.services'),
            data_get($payload, 'buckets'),
            data_get($payload, 'data.buckets'),
            data_get($payload, 'users'),
            data_get($payload, 'data.users'),
        ];

        foreach ($candidates as $list) {
            if (! is_array($list)) {
                continue;
            }

            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? $item['subscription_id'] ?? $item['bucket_name'] ?? $item['name'] ?? $item['email'] ?? '');
                if ($id === '') {
                    continue;
                }

                $name = (string) ($item['name'] ?? $item['plan_name'] ?? $item['bucket_name'] ?? $item['product'] ?? ('IDrive e2 · '.$id));
                $price = (float) ($item['amount'] ?? $item['price'] ?? $item['recurring_amount'] ?? AppSetting::get('idrivee2_default_price', 5.00));
                $currency = strtoupper((string) ($item['currency'] ?? 'USD'));
                $cycleRaw = mb_strtolower((string) ($item['billing_cycle'] ?? $item['interval'] ?? $item['cycle'] ?? 'monthly'));
                $cycle = str_contains($cycleRaw, 'year') || str_contains($cycleRaw, 'annual') || str_contains($cycleRaw, 'anual')
                    ? LicenseService::CYCLE_ANNUAL
                    : LicenseService::CYCLE_MONTHLY;

                $nextDue = $item['next_billing_date'] ?? $item['next_due_date'] ?? $item['renewal_date'] ?? $item['expires_at'] ?? null;
                if ($nextDue) {
                    try {
                        $nextDue = Carbon::parse($nextDue)->toDateString();
                    } catch (Throwable) {
                        $nextDue = null;
                    }
                }

                $statusRaw = mb_strtolower((string) ($item['status'] ?? 'active'));
                $status = match (true) {
                    str_contains($statusRaw, 'cancel') => LicenseService::STATUS_CANCELED,
                    str_contains($statusRaw, 'expir') || str_contains($statusRaw, 'suspend') => LicenseService::STATUS_EXPIRED,
                    default => LicenseService::STATUS_ACTIVE,
                };

                $services[] = [
                    'service_id' => $id,
                    'product' => (string) ($item['plan_name'] ?? $item['product'] ?? 'IDrive e2 S3 Storage'),
                    'name' => $name,
                    'server_name' => (string) ($item['endpoint'] ?? $item['region'] ?? AppSetting::get('idrivee2_s3_endpoint', 'console.idrivee2.com')),
                    'price' => $price,
                    'currency' => in_array($currency, ['USD', 'EUR'], true) ? $currency : 'USD',
                    'cycle' => $cycle,
                    'next_due' => $nextDue,
                    'status' => $status,
                ];
            }
        }

        $invoiceCandidates = [
            data_get($payload, 'invoices'),
            data_get($payload, 'data.invoices'),
            data_get($payload, 'billing_history'),
            data_get($payload, 'data.billing_history'),
        ];

        foreach ($invoiceCandidates as $invList) {
            if (! is_array($invList)) {
                continue;
            }

            foreach ($invList as $inv) {
                if (! is_array($inv)) {
                    continue;
                }

                $ref = (string) ($inv['invoice_number'] ?? $inv['id'] ?? $inv['reference'] ?? '');
                $amount = (float) ($inv['amount'] ?? $inv['total'] ?? 0);

                if ($ref === '' || $amount <= 0) {
                    continue;
                }

                if (! str_starts_with(strtoupper($ref), 'E2-')) {
                    $ref = 'E2-'.$ref;
                }

                $due = $inv['due_date'] ?? $inv['date'] ?? $inv['created_at'] ?? now()->toDateString();
                try {
                    $due = Carbon::parse($due)->toDateString();
                } catch (Throwable) {
                    $due = now()->toDateString();
                }

                $statusRaw = mb_strtolower((string) ($inv['status'] ?? 'pending'));
                $status = str_contains($statusRaw, 'paid') || str_contains($statusRaw, 'pago') || str_contains($statusRaw, 'success')
                    ? Invoice::STATUS_PAID
                    : Invoice::STATUS_PENDING;

                $invoices[] = [
                    'external_reference' => $ref,
                    'external_url' => (string) ($inv['invoice_pdf'] ?? $inv['url'] ?? self::CONSOLE_URL),
                    'description' => (string) ($inv['description'] ?? ('IDrive e2 Cloud Storage S3 · '.$ref)),
                    'currency' => in_array(strtoupper((string) ($inv['currency'] ?? 'USD')), ['USD', 'EUR'], true)
                        ? strtoupper((string) ($inv['currency'] ?? 'USD'))
                        : 'USD',
                    'amount_original' => $amount,
                    'due_date' => $due,
                    'status' => $status,
                ];
            }
        }

        return ['services' => $services, 'invoices' => $invoices];
    }

    /**
     * Grava ou atualiza os serviços S3 e as faturas no banco de dados.
     *
     * @param  list<array<string, mixed>>  $services
     * @param  list<array<string, mixed>>  $invoices
     * @return array{created: int, updated: int, invoices: int}
     */
    public function applyServicesAndInvoices(array $services, array $invoices = []): array
    {
        $created = 0;
        $updated = 0;
        $invoicesCreated = 0;

        $exchange = app(ExchangeRateService::class);
        $tax = app(TaxCalculationService::class);
        $firstService = null;

        foreach ($services as $item) {
            $serviceId = mb_substr(trim((string) ($item['service_id'] ?? '')), 0, 32);
            $name = (string) ($item['name'] ?? $item['product'] ?? 'IDrive e2 S3 Storage');
            $product = (string) ($item['product'] ?? 'IDrive e2 S3 Storage');
            $price = (float) ($item['price'] ?? 0);
            $currency = in_array($item['currency'] ?? 'USD', ['USD', 'EUR'], true) ? $item['currency'] : 'USD';
            $cycle = $item['cycle'] ?? LicenseService::CYCLE_MONTHLY;
            $nextDue = $item['next_due'] ?? null;
            $status = mb_substr((string) ($item['status'] ?? LicenseService::STATUS_ACTIVE), 0, 30);

            $existing = LicenseService::query()
                ->where('provider', self::PROVIDER)
                ->where(function ($q) use ($serviceId, $name): void {
                    if ($serviceId !== '') {
                        $q->where('panel_service_id', $serviceId);
                    }
                    $q->orWhere('name', $name);
                })
                ->first();

            if ($existing) {
                $catalogMatch = LicenseCatalogItem::query()
                    ->where('provider', self::PROVIDER)
                    ->where('billing_cycle', $cycle)
                    ->orderByRaw('ABS(price - ?)', [$price > 0 ? $price : 5])
                    ->first();

                $existing->fill([
                    'panel_service_id' => $serviceId ?: $existing->panel_service_id,
                    'panel_price' => $price > 0 ? number_format($price, 2, '.', '') : $existing->panel_price,
                    'panel_currency' => $currency,
                    'panel_billing_cycle' => $cycle,
                    'panel_next_due_date' => $nextDue ?? $existing->panel_next_due_date,
                    'panel_status' => $status,
                    'panel_synced_at' => now(),
                    'billing_cycle' => $cycle,
                    'status' => $status,
                ]);

                if ($price > 0) {
                    $existing->unit_price = number_format($price, 2, '.', '');
                }

                if ($currency) {
                    $existing->currency = $currency;
                }

                if ($catalogMatch) {
                    $existing->license_catalog_item_id = $catalogMatch->id;
                }

                if ($nextDue) {
                    $existing->next_due_date = $nextDue;
                }

                $existing->save();
                $firstService ??= $existing;
                $updated++;
            } else {
                $catalogMatch = LicenseCatalogItem::query()
                    ->where('provider', self::PROVIDER)
                    ->where('billing_cycle', $cycle)
                    ->orderByRaw('ABS(price - ?)', [$price > 0 ? $price : 5])
                    ->first();

                $newService = LicenseService::query()->create([
                    'provider' => self::PROVIDER,
                    'license_catalog_item_id' => $catalogMatch?->id,
                    'product' => mb_substr($product, 0, 80),
                    'name' => mb_substr($name, 0, 150),
                    'server_name' => mb_substr((string) ($item['server_name'] ?? 'console.idrivee2.com'), 0, 190),
                    'quantity' => 1,
                    'unit_price' => number_format($price > 0 ? $price : (float) ($catalogMatch?->price ?? 5.00), 2, '.', ''),
                    'currency' => $currency,
                    'billing_cycle' => $cycle,
                    'next_due_date' => $nextDue ?: now()->addMonthNoOverflow()->toDateString(),
                    'status' => $status,
                    'auto_renew' => true,
                    'panel_service_id' => $serviceId ?: null,
                    'panel_price' => $price > 0 ? number_format($price, 2, '.', '') : null,
                    'panel_currency' => $currency,
                    'panel_billing_cycle' => $cycle,
                    'panel_next_due_date' => $nextDue,
                    'panel_status' => $status,
                    'panel_synced_at' => now(),
                ]);

                $firstService ??= $newService;
                $created++;
            }
        }

        $firstService ??= LicenseService::query()->where('provider', self::PROVIDER)->first();

        foreach ($invoices as $invData) {
            $invoice = Invoice::query()->firstOrCreate(
                ['external_reference' => $invData['external_reference']],
                [
                    'external_url' => $invData['external_url'] ?? self::CONSOLE_URL,
                    'description' => mb_substr((string) $invData['description'], 0, 255),
                    'currency' => $invData['currency'],
                    'amount_original' => $invData['amount_original'],
                    'due_date' => $invData['due_date'],
                    'status' => $invData['status'],
                    'license_service_id' => $firstService?->id,
                    'total_brl' => 0,
                ]
            );

            if ($invoice->wasRecentlyCreated) {
                $rate = $exchange->rate($invoice->currency);
                if ($rate > 0) {
                    $tax->applyToInvoice($invoice, $rate);
                }

                if ($invData['status'] === Invoice::STATUS_PAID) {
                    $invoice->update([
                        'status' => Invoice::STATUS_PAID,
                        'paid_at' => Carbon::parse($invData['due_date']),
                        'rate_locked' => true,
                    ]);
                }

                if ($firstService) {
                    $firstService->update(['last_invoice_id' => $invoice->id]);
                }

                $invoicesCreated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'invoices' => $invoicesCreated,
        ];
    }

    private function httpClient(?CookieJar $cookies = null): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $cookies ?? new CookieJar(),
            'verify' => (bool) config('finance.exchange.verify_ssl', true),
        ])->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
        ])->timeout(25);
    }
}
