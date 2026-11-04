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

return [

    /*
    |--------------------------------------------------------------------------
    | Identidade técnica do sistema
    |--------------------------------------------------------------------------
    | Usada nos cabeçalhos HTTP, no rodapé, nos metadados e nos documentos.
    */
    'developer' => [
        'name' => 'Marcelo Brad - RJ',
        'short_name' => 'Marcelo Brad - RJ',
        'company' => 'KDKHost Soluções',
        'company_ascii' => 'KDKHost Solucoes',
        'cnpj' => '33.986.975/0001-90',
        'email' => 'contato@kdkhost.com.br',
        'site' => 'https://kdkhost.com.br',
        'role' => 'Responsável Técnico / Engenheiro de Software Full-Stack',
    ],

    /*
    |--------------------------------------------------------------------------
    | Moedas suportadas
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'EUR' => ['symbol' => '€', 'label' => 'Euro', 'flag' => '🇪🇺'],
        'USD' => ['symbol' => '$', 'label' => 'Dólar americano', 'flag' => '🇺🇸'],
        'BRL' => ['symbol' => 'R$', 'label' => 'Real brasileiro', 'flag' => '🇧🇷'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cotação de câmbio (AwesomeAPI)
    |--------------------------------------------------------------------------
    */
    'exchange' => [
        'api_url' => 'https://economia.awesomeapi.com.br/last/USD-BRL,EUR-BRL',
        // Par direto (ex.: /last/EUR-USD) — gratuito e sem chave, igual ao endpoint acima.
        'pair_api_url' => 'https://economia.awesomeapi.com.br/json/last',
        'timeout' => 12,
        'cache_minutes' => 15,
        'pair_cache_minutes' => 5,
        // Em produção mantenha true. No WAMP local, sem bundle CA, use false no .env.
        'verify_ssl' => env('FINANCE_VERIFY_SSL', true),
        'default_rates' => [
            'USD' => 5.50,
            'EUR' => 6.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversão entre moedas e tarifas de cartão internacional
    |--------------------------------------------------------------------------
    | Valores usados somente enquanto a tela /configuracoes#conversao ainda
    | não foi salva. Depois disso, quem manda é a tabela app_settings
    | (grupo "conversion"), conforme a regra do projeto.
    */
    'conversion' => [
        'spread_percent' => 2.00,
        // 2026: IOF de 3,5% cobrado pelo banco no cartão internacional/aporte.
        // Se o saldo em dólar já foi abastecido com IOF, use 0 na operação para
        // não contar o imposto duas vezes.
        'iof_percent' => 3.50,
        'fixed_fee' => 0.00,
        'buffer_percent' => 0.00,
        'fallback_pair_rates' => [
            'EUR-USD' => 1.0900,
            'USD-EUR' => 0.9174,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tributos padrão (usados apenas quando o banco ainda não foi populado)
    |--------------------------------------------------------------------------
    */
    'taxes' => [
        'spread_percent' => 2.00,
        'iof_percent' => 3.38,
        'icms_percent' => 0.00,
        'other_taxes_percent' => 0.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Regras operacionais
    |--------------------------------------------------------------------------
    */
    'variation_threshold_percent' => 1.00,

    /*
    |--------------------------------------------------------------------------
    | OVHcloud
    |--------------------------------------------------------------------------
    | A autenticação legada (AK/AS/CK) usa eu.api.ovh.com/1.0. A API v2 usa
    | OAuth2 (Bearer) em api.eu.ovhcloud.com/v2 — ambos suportados pelo serviço.
    */
    'ovh' => [
        'endpoint' => 'ovh-eu',
        'v2_base_url' => 'https://api.eu.ovhcloud.com/v2',
        'timeout' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Central de cron
    |--------------------------------------------------------------------------
    | Tempo máximo (em segundos) de cada comando cadastrado em /crons.
    */
    'cron' => [
        'timeout' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | PWA
    |--------------------------------------------------------------------------
    */
    'pwa' => [
        'name' => 'KDK Finance — Multimoedas',
        'short_name' => 'KDK Finance',
        'theme_color' => '#0a2540',
        'background_color' => '#f4f7fb',
    ],

];
