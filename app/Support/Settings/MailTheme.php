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
use Illuminate\Support\Str;

/**
 * Tema visual dos e-mails transacionais, editável pelo painel.
 *
 * As cores são validadas em hexadecimal antes de chegar ao HTML, porque o
 * template usa estilos inline (exigência dos clientes de e-mail).
 */
final class MailTheme
{
    public const GROUP = 'mail_theme';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'theme_brand' => 'KDK Finance',
            'theme_logo_enabled' => '1',
            'theme_primary' => '#0f9d76',
            'theme_accent' => '#0a2540',
            'theme_background' => '#f1f5f9',
            'theme_text' => '#1f2937',
            'theme_muted' => '#64748b',
            'theme_footer' => 'Mensagem automática do painel financeiro da KDKHost Soluções. Não responda este e-mail.',
            'theme_signature' => 'Equipe KDKHost · contato@kdkhost.com.br',
            'theme_support_email' => 'contato@kdkhost.com.br',
            'theme_support_url' => 'https://kdkhost.com.br',
            'theme_company' => 'KDKHost Soluções · CNPJ 33.986.975/0001-90',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $theme = AppSetting::group(self::GROUP, self::defaults());

        $colorDefaults = [
            'theme_primary' => '#0f9d76',
            'theme_accent' => '#0a2540',
            'theme_background' => '#f1f5f9',
            'theme_text' => '#1f2937',
            'theme_muted' => '#64748b',
        ];

        foreach ($colorDefaults as $key => $fallback) {
            $theme[$key] = self::color($theme[$key] ?? null, $fallback);
        }

        $theme['theme_logo_enabled'] = filter_var($theme['theme_logo_enabled'] ?? '0', FILTER_VALIDATE_BOOL) ? '1' : '0';
        $theme['theme_brand'] = trim((string) ($theme['theme_brand'] ?? '')) ?: 'KDK Finance';
        $theme['theme_support_url'] = trim((string) ($theme['theme_support_url'] ?? '')) ?: 'https://kdkhost.com.br';

        return $theme;
    }

    public static function brand(): string
    {
        return self::all()['theme_brand'];
    }

    public static function logoEnabled(): bool
    {
        return self::all()['theme_logo_enabled'] === '1';
    }

    public static function initials(): string
    {
        $initials = Str::of(self::brand())
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'K';
    }

    /**
     * Aceita somente #RRGGBB. Qualquer outra coisa volta para o padrão.
     */
    public static function color(?string $value, string $fallback = '#0f9d76'): string
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
    }

    public static function safeUrl(?string $value): string
    {
        $value = trim((string) $value);

        return preg_match('#^https?://#i', $value) === 1 ? $value : 'https://kdkhost.com.br';
    }
}
