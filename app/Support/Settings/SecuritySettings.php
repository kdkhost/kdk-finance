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

namespace App\Support\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * Centraliza as decisões de segurança operacional.
 *
 * Tudo é lido da tabela app_settings (grupo "security"), portanto pode ser
 * alterado pelo painel administrativo sem tocar no .env ou reiniciar o PHP.
 */
final class SecuritySettings
{
    public const GROUP = 'security';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'security_force_https' => '0',
            'security_hsts_enabled' => '1',
            'security_hsts_max_age' => '365',
            'security_headers_enabled' => '1',
            'security_csp_enabled' => '1',
            'security_frame_options' => 'SAMEORIGIN',
            'security_trust_hosts' => '0',
            'security_allowed_hosts' => '',
            'security_trusted_proxies' => '',
            'security_no_store' => '1',
            'security_session_lifetime' => '120',
            'security_session_encrypt' => '0',
            'security_session_secure' => '1',
            'security_session_same_site' => 'lax',
            'security_session_expire_on_close' => '0',
            'security_login_max_attempts' => '5',
            'security_login_decay_seconds' => '60',
            'security_password_min' => '10',
            'security_password_mixed_case' => '1',
            'security_password_numbers' => '1',
            'security_password_symbols' => '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return AppSetting::group(self::GROUP, self::defaults());
    }

    public static function forceHttps(): bool
    {
        return self::bool('security_force_https');
    }

    public static function headersEnabled(): bool
    {
        return self::bool('security_headers_enabled');
    }

    public static function cspEnabled(): bool
    {
        return self::bool('security_csp_enabled');
    }

    public static function hstsEnabled(): bool
    {
        return self::bool('security_hsts_enabled');
    }

    public static function hstsMaxAge(): int
    {
        return self::int('security_hsts_max_age', 365, 1, 730);
    }

    public static function frameOptions(): string
    {
        $value = strtoupper((string) self::value('security_frame_options', 'SAMEORIGIN'));

        return in_array($value, ['SAMEORIGIN', 'DENY'], true) ? $value : 'SAMEORIGIN';
    }

    public static function trustHosts(): bool
    {
        return self::bool('security_trust_hosts');
    }

    /**
     * Lista normalizada de hosts aceitos. Sem valor explícito, usa o host do
     * APP_URL — nunca fica vazia quando a proteção está ligada.
     *
     * @return array<int, string>
     */
    public static function allowedHosts(): array
    {
        $hosts = self::normalizeHosts((string) self::value('security_allowed_hosts', ''));

        if ($hosts === []) {
            $hosts = self::normalizeHosts((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        }

        return $hosts;
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeHosts(string $raw): array
    {
        $items = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $hosts = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            // Aceita "https://exemplo.com/painel", "exemplo.com:8443" e "*.exemplo.com".
            if (str_starts_with($item, '*.')) {
                $host = strtolower($item);
            } else {
                $parsed = parse_url(str_contains($item, '://') ? $item : 'https://'.$item, PHP_URL_HOST);
                $host = strtolower((string) ($parsed ?: $item));
                $host = preg_replace('/:\d+$/', '', $host) ?? $host;
            }

            if ($host !== '' && ! in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * @return array<int, string>
     */
    public static function trustedProxies(): array
    {
        return self::trustedProxiesFrom((string) self::value('security_trusted_proxies', ''));
    }

    /**
     * @return array<int, string>
     */
    public static function trustedProxiesFrom(string $raw): array
    {
        $items = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('trim', $items)));
    }

    public static function noStore(): bool
    {
        return self::bool('security_no_store');
    }

    public static function sessionLifetime(): int
    {
        return self::int('security_session_lifetime', 120, 5, 43200);
    }

    public static function sessionEncrypt(): bool
    {
        return self::bool('security_session_encrypt');
    }

    public static function sessionSecure(): bool
    {
        return self::bool('security_session_secure');
    }

    public static function sessionSameSite(): string
    {
        $value = strtolower((string) self::value('security_session_same_site', 'lax'));

        return in_array($value, ['lax', 'strict', 'none'], true) ? $value : 'lax';
    }

    public static function sessionExpireOnClose(): bool
    {
        return self::bool('security_session_expire_on_close');
    }

    public static function loginMaxAttempts(): int
    {
        return self::int('security_login_max_attempts', 5, 3, 50);
    }

    public static function loginDecaySeconds(): int
    {
        return self::int('security_login_decay_seconds', 60, 10, 86400);
    }

    public static function passwordMinLength(): int
    {
        return self::int('security_password_min', 10, 8, 72);
    }

    public static function passwordMixedCase(): bool
    {
        return self::bool('security_password_mixed_case');
    }

    public static function passwordNumbers(): bool
    {
        return self::bool('security_password_numbers');
    }

    public static function passwordSymbols(): bool
    {
        return self::bool('security_password_symbols');
    }

    /**
     * Aplica a política de senha padrão a todos os formulários do sistema.
     */
    public static function applyPasswordPolicy(): void
    {
        $rule = Password::min(self::passwordMinLength());

        if (self::passwordMixedCase()) {
            $rule->mixedCase();
        }

        if (self::passwordNumbers()) {
            $rule->numbers();
        }

        if (self::passwordSymbols()) {
            $rule->symbols();
        }

        Password::defaults(fn () => $rule);
    }

    /**
     * Ajusta a sessão antes de o middleware StartSession ser resolvido.
     */
    public static function applySessionConfig(): void
    {
        config([
            'session.lifetime' => self::sessionLifetime(),
            'session.encrypt' => self::sessionEncrypt(),
            'session.expire_on_close' => self::sessionExpireOnClose(),
            'session.secure' => self::sessionSecure() && self::requestIsSecure(),
            'session.same_site' => self::sessionSameSite(),
            'session.http_only' => true,
        ]);
    }

    public static function requestIsSecure(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        try {
            return request()->isSecure();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isHttpsConfigured(): bool
    {
        return str_starts_with(strtolower((string) config('app.url')), 'https://');
    }

    public static function logBootFailure(\Throwable $exception): void
    {
        Log::warning('Não foi possível aplicar as configurações de segurança do banco.', [
            'error' => $exception->getMessage(),
        ]);
    }

    private static function value(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    private static function bool(string $key): bool
    {
        return filter_var(self::value($key, '0'), FILTER_VALIDATE_BOOL);
    }

    private static function int(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var(self::value($key, $default), FILTER_VALIDATE_INT);

        if ($value === false) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }
}
