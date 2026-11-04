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

namespace App\Support\Settings;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

/**
 * Configuração completa de SMTP gravada no banco de dados.
 *
 * O grupo "mail" guarda host, porta, criptografia, autenticação, remetente e
 * resposta. A senha é criptografada pela própria AppSetting. Quando o SMTP não
 * está completo, o sistema cai para o transporte "log" (nada é enviado), em vez
 * de estourar erro em produção ou depender de credenciais no .env.
 */
final class MailSettings
{
    public const GROUP = 'mail';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'mail_enabled' => '0',
            'mail_host' => '',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_auth' => '1',
            'mail_username' => '',
            'mail_password' => '',
            'mail_timeout' => '15',
            'mail_from_address' => '',
            'mail_from_name' => 'KDK Finance',
            'mail_reply_to_address' => '',
            'mail_reply_to_name' => '',
            'mail_last_test_at' => '',
            'mail_last_test_status' => '',
            'mail_last_test_message' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return AppSetting::group(self::GROUP, self::defaults());
    }

    public static function enabled(): bool
    {
        return filter_var(self::value('mail_enabled', '0'), FILTER_VALIDATE_BOOL);
    }

    public static function host(): string
    {
        return trim((string) self::value('mail_host', ''));
    }

    public static function port(): int
    {
        return self::int('mail_port', 587, 1, 65535);
    }

    public static function encryption(): string
    {
        $value = strtolower((string) self::value('mail_encryption', 'tls'));

        return in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
    }

    public static function auth(): bool
    {
        return filter_var(self::value('mail_auth', '1'), FILTER_VALIDATE_BOOL);
    }

    public static function username(): string
    {
        return trim((string) self::value('mail_username', ''));
    }

    public static function password(): string
    {
        return (string) self::value('mail_password', '');
    }

    public static function timeout(): int
    {
        return self::int('mail_timeout', 15, 3, 120);
    }

    public static function fromAddress(): string
    {
        return trim((string) self::value('mail_from_address', ''));
    }

    public static function fromName(): string
    {
        return trim((string) self::value('mail_from_name', 'KDK Finance')) ?: 'KDK Finance';
    }

    public static function replyToAddress(): string
    {
        return trim((string) self::value('mail_reply_to_address', ''));
    }

    public static function replyToName(): string
    {
        return trim((string) self::value('mail_reply_to_name', ''));
    }

    public static function lastTestAt(): ?string
    {
        $value = (string) self::value('mail_last_test_at', '');

        return $value !== '' ? $value : null;
    }

    public static function lastTestStatus(): string
    {
        return (string) self::value('mail_last_test_status', '');
    }

    public static function lastTestMessage(): string
    {
        return (string) self::value('mail_last_test_message', '');
    }

    /**
     * SMTP só é considerado pronto quando está ativo, tem servidor e remetente.
     */
    public static function configured(): bool
    {
        return self::enabled() && self::host() !== '' && self::fromAddress() !== '';
    }

    public static function driver(): string
    {
        return self::configured() ? 'smtp' : 'log';
    }

    /**
     * Sobrepõe a configuração de e-mail em tempo de execução, sem .env.
     */
    public static function apply(): void
    {
        $configured = self::configured();
        $scheme = match (self::encryption()) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };

        $from = self::fromAddress() !== '' ? self::fromAddress() : 'no-reply@localhost';

        config([
            'mail.default' => $configured ? 'smtp' : 'log',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => self::host() !== '' ? self::host() : '127.0.0.1',
            'mail.mailers.smtp.port' => self::port(),
            'mail.mailers.smtp.username' => self::auth() ? self::username() : null,
            'mail.mailers.smtp.password' => self::auth() ? self::password() : null,
            'mail.mailers.smtp.timeout' => self::timeout(),
            'mail.mailers.smtp.local_domain' => (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'mail.from.address' => $from,
            'mail.from.name' => self::fromName(),
        ]);

        if (self::replyToAddress() !== '') {
            config([
                'mail.reply_to.address' => self::replyToAddress(),
                'mail.reply_to.name' => self::replyToName() !== '' ? self::replyToName() : self::fromName(),
            ]);
        }
    }

    public static function logBootFailure(\Throwable $exception): void
    {
        Log::warning('Não foi possível aplicar as configurações de e-mail do banco.', [
            'error' => $exception->getMessage(),
        ]);
    }

    private static function value(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
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
