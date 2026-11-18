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

class SystemVersion
{
    /**
     * Versão atual de lançamento do sistema.
     * Centraliza a versão exibida no rodapé, menus laterais e cabeçalhos.
     */
    public const VERSION = '1.14.4';

    /**
     * Retorna a versão pura (ex: "1.14.3").
     */
    public static function get(): string
    {
        $version = (string) config('finance.version', self::VERSION);

        return $version !== '' ? $version : self::VERSION;
    }

    /**
     * Retorna a versão prefixada com "v" (ex: "v1.14.3").
     */
    public static function formatted(): string
    {
        return 'v'.ltrim(self::get(), 'v');
    }
}
