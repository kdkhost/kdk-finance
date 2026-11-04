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

use App\Support\Settings\MailTemplates;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo transacional editável pelo painel.
 *
 * O conteúdo usa placeholders no formato {{ variavel }}, substituídos no
 * momento do envio. Nada é avaliado como código, apenas troca de texto.
 */
class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'subject',
        'heading',
        'intro',
        'details',
        'action_text',
        'action_url',
        'footnote',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return array{subject: string, heading: string, intro: string, details: array<string, string>, action_text: ?string, action_url: ?string, footnote: string, preheader: string}
     */
    public function render(array $variables): array
    {
        $details = [];

        foreach ((array) $this->details as $label => $value) {
            $label = trim(MailTemplates::interpolate((string) $label, $variables));

            if ($label === '') {
                continue;
            }

            $details[$label] = MailTemplates::interpolate((string) $value, $variables);
        }

        $intro = MailTemplates::interpolate($this->intro, $variables);

        return [
            'subject' => MailTemplates::interpolate($this->subject, $variables),
            'heading' => MailTemplates::interpolate($this->heading, $variables),
            'intro' => $intro,
            'details' => $details,
            'action_text' => trim((string) $this->action_text) !== ''
                ? MailTemplates::interpolate($this->action_text, $variables)
                : null,
            'action_url' => trim((string) $this->action_url) !== ''
                ? MailTemplates::interpolate($this->action_url, $variables)
                : null,
            'footnote' => MailTemplates::interpolate($this->footnote, $variables),
            'preheader' => $intro,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        return MailTemplates::defaults()[$this->key]['variables'] ?? [];
    }

    /**
     * Converte o texto do formulário ("Rótulo|Valor" por linha) em pares.
     *
     * @return array<string, string>
     */
    public static function parseDetails(?string $text): array
    {
        $details = [];

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! str_contains($line, '|')) {
                continue;
            }

            [$label, $value] = explode('|', $line, 2);
            $label = trim($label);

            if ($label === '') {
                continue;
            }

            $details[$label] = trim($value);
        }

        return $details;
    }

    public static function detailsAsText(?array $details): string
    {
        $lines = [];

        foreach ((array) $details as $label => $value) {
            $lines[] = $label.'|'.$value;
        }

        return implode("\n", $lines);
    }
}
