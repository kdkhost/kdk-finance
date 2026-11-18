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

            if ($setting->value === null || $setting->value === '') {
                return $default;
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
     * Lê um grupo inteiro de configurações em uma única consulta, mantendo os
     * valores criptografados dentro do cache (a descriptografia acontece só na
     * entrega para a aplicação).
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function group(string $group, array $defaults = []): array
    {
        try {
            $rows = Cache::remember(
                self::groupCacheKey($group),
                now()->addHours(24),
                function () use ($group): array {
                    return static::query()
                        ->where('group', $group)
                        ->get(['key', 'value', 'is_secret'])
                        ->mapWithKeys(fn (self $setting): array => [
                            $setting->key => [
                                'value' => $setting->value,
                                'is_secret' => (bool) $setting->is_secret,
                            ],
                        ])
                        ->all();
                }
            );
        } catch (\Throwable $exception) {
            // Banco ainda não migrado (instalador/primeiro boot) ou cache indisponível.
            return $defaults;
        }

        $values = $defaults;

        foreach ($rows as $key => $row) {
            $value = $row['value'];

            if ($value === null) {
                continue;
            }

            if ($row['is_secret'] && $value === '') {
                continue;
            }

            if ($row['is_secret']) {
                try {
                    $value = Crypt::decryptString((string) $value);
                } catch (DecryptException $exception) {
                    Log::warning('Não foi possível descriptografar a configuração secreta do grupo.', [
                        'group' => $group,
                        'key' => $key,
                    ]);

                    continue;
                }
            }

            $values[$key] = $value;
        }

        return $values;
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

        $previousGroup = static::query()->where('key', $key)->value('group');

        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $stored,
                'is_secret' => $isSecret,
            ]
        );

        Cache::forget(self::cacheKey($key));
        Cache::forget(self::groupCacheKey($group));

        if (is_string($previousGroup) && $previousGroup !== '' && $previousGroup !== $group) {
            Cache::forget(self::groupCacheKey($previousGroup));
        }
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

    private static function groupCacheKey(string $group): string
    {
        return 'app_setting_group:'.$group;
    }
}
