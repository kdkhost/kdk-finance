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

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AppSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_secret',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    /**
     * Lê uma configuração com cache de 24 h, descriptografando quando necessário.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember(self::cacheKey($key), now()->addHours(24), function () use ($key, $default) {
            $setting = static::query()->where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            if (! $setting->is_secret) {
                return $setting->value;
            }

            try {
                return Crypt::decryptString((string) $setting->value);
            } catch (DecryptException $exception) {
                Log::warning('Não foi possível descriptografar a configuração secreta.', [
                    'key' => $key,
                    'error' => $exception->getMessage(),
                ]);

                return $default;
            }
        });

        return $cached ?? $default;
    }

    /**
     * Grava uma configuração, criptografando segredos e invalidando o cache.
     */
    public static function set(string $key, mixed $value, string $group = 'general', bool $isSecret = false): void
    {
        $stored = $value === null ? null : (string) $value;

        if ($isSecret && $stored !== null && $stored !== '') {
            $stored = Crypt::encryptString($stored);
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $stored,
                'is_secret' => $isSecret,
            ]
        );

        Cache::forget(self::cacheKey($key));
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::cacheKey($key));
    }

    public static function isSecret(string $key): bool
    {
        return (bool) static::query()->where('key', $key)->value('is_secret');
    }

    public static function mask(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        $tail = mb_substr($value, -4);

        return str_repeat('•', max(8, mb_strlen($value) - 4)).$tail;
    }

    private static function cacheKey(string $key): string
    {
        return 'app_setting:'.$key;
    }
}
