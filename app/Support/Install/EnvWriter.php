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

namespace App\Support\Install;

/**
 * Gera e grava o .env do instalador sem depender do framework carregado.
 *
 * Todos os valores são escapados para o formato entre aspas do Dotenv, de
 * modo que senhas com #, $, aspas, barras ou espaços não quebrem o arquivo.
 */
final class EnvWriter
{
    public static function appKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function render(array $values): string
    {
        $lines = [
            '# ============================================================================',
            '# Gerado pelo instalador do KDK Finance — não versionar este arquivo.',
            '# ============================================================================',
            '',
        ];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $lines[] = $key.'='.self::quote((string) $value);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function write(string $path, array $values): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new \RuntimeException('A pasta do .env não permite gravação.');
        }

        $contents = self::render($values);
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo .env.');
        }

        @chmod($temporary, 0640);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException('Não foi possível publicar o arquivo .env.');
        }

        @chmod($path, 0640);
    }

    public static function quote(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', ''],
            $value
        );

        return '"'.$escaped.'"';
    }

    /**
     * Lê o .env existente e devolve o valor de uma chave (sem carregar o framework).
     */
    public static function readKey(string $path, string $key): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1], " \t\n\r\0\x0B");

        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return self::unquote(substr($value, 1, -1));
        }

        return trim($value, " \t\n\r\0\x0B\"'");
    }

    /**
     * Reverte o escape aplicado por quote() em valores entre aspas.
     */
    public static function unquote(string $value): string
    {
        $result = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($character === '\\' && $index + 1 < $length) {
                $next = $value[$index + 1];

                if ($next === '\\' || $next === '"' || $next === '$') {
                    $result .= $next;
                    $index++;

                    continue;
                }
            }

            $result .= $character;
        }

        return $result;
    }
}
