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

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Conteúdo rico produzido pelo Summernote.
 *
 * O editor é global no painel, então tudo o que ele grava passa por aqui:
 * o HTML é reduzido a uma lista segura de tags antes de ir para o banco e
 * novamente antes de ser exibido (defesa em profundidade).
 */
final class RichText
{
    private const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'blockquote', 'h2', 'h3', 'h4', 'div',
    ];

    private const DROP = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'link', 'meta', 'svg',
        'math', 'img', 'video', 'audio', 'source', 'picture',
    ];

    public static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! self::isHtml($value)) {
            return $value;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div data-kdk-root="1">'.$value.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            return '';
        }

        $elements = iterator_to_array($root->getElementsByTagName('*'));

        foreach (array_reverse($elements) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($element->tagName);

            if (in_array($tag, self::DROP, true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            if (! in_array($tag, self::ALLOWED, true)) {
                self::unwrap($element);

                continue;
            }

            self::cleanAttributes($element, $tag);
        }

        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    /**
     * Renderiza o valor para uma página do painel. Texto puro continua com
     * quebras de linha preservadas; HTML passa pela lista segura.
     */
    public static function render(?string $value, string $class = 'rich-text'): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $body = self::isHtml($value)
            ? (self::sanitize($value) ?? '')
            : nl2br(self::escape($value), false);

        return '<div class="'.$class.'">'.$body.'</div>';
    }

    /**
     * Versão para e-mail: devolve HTML seguro, pronto para {!! !!}.
     */
    public static function email(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        if (! self::isHtml($value)) {
            return nl2br(self::escape($value), false);
        }

        return self::sanitize($value) ?? '';
    }

    /**
     * Texto simples para CSV, busca, títulos e confirmações.
     */
    public static function text(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! self::isHtml($value)) {
            return trim($value);
        }

        $html = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $value) ?? $value;
        $html = preg_replace('~<\s*/\s*(p|div|li|h[1-6]|tr|blockquote)\s*>~i', "\n", $html) ?? $html;
        $html = preg_replace('~<[^>]*>~', '', $html) ?? $html;

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    public static function isHtml(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return preg_match('~<\s*/?\s*[a-z][a-z0-9]*(\s[^>]*)?>~i', $value) === 1;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = null;

        if ($tag === 'a') {
            $candidate = trim($element->getAttribute('href'));

            if (
                $candidate !== ''
                && preg_match('~^(https?://|mailto:|tel:|/|#)~i', $candidate) === 1
                && preg_match('/[\x00-\x20]/', $candidate) !== 1
            ) {
                $href = $candidate;
            }
        }

        while ($element->attributes->length > 0) {
            $attribute = $element->attributes->item(0);

            if ($attribute !== null) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($href !== null) {
            $element->setAttribute('href', $href);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
