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
        // Provedores consultados nesta ordem até um responder. A AwesomeAPI é a
        // principal; os demais são gratuitos, sem chave e entram quando ela
        // estoura a cota (HTTP 429) ou fica fora do ar.
        'providers' => ['awesomeapi', 'er-api', 'frankfurter'],
        'er_api_url' => 'https://open.er-api.com/v6/latest/USD',
        'frankfurter_url' => 'https://api.frankfurter.app/latest?from=USD&to=BRL,EUR',
        'timeout' => 12,
        'cache_minutes' => 15,
        'pair_cache_minutes' => 5,
        // Tempo de cache do resultado degradado (última cotação conhecida)
        // para não repetir a consulta a cada carregamento quando todos falham.
        'failure_cache_minutes' => 2,
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
        // Encargos cobrados na fatura do cartão.
        'spread_percent' => 2.00,
        // Decreto 6.306/2007, art. 15-B, VII, na redação do Decreto 12.499/2025:
        // 3,5% para cartão internacional e remessas de pagamento de serviços.
        'iof_percent' => 3.50,
        'card_fixed_fee' => 0.00,
        'card_spread_auto' => '0',
        // Tributos da importação de serviços (recolhidos pela empresa).
        'service_taxes_enabled' => '1',
        'pis_cofins_percent' => 9.25,
        'pis_cofins_enabled' => '1',
        'irrf_percent' => 15.00,
        'irrf_enabled' => '1',
        'iss_percent' => 5.00,
        'iss_enabled' => '1',
        'cide_percent' => 10.00,
        'cide_enabled' => '0',
        'cbs_percent' => 0.90,
        'cbs_enabled' => '0',
        'ibs_percent' => 0.10,
        'ibs_enabled' => '0',
        'other_taxes_percent' => 0.00,
        'other_taxes_enabled' => '0',
        // Importação de bens (não se aplica a serviços).
        'icms_percent' => 17.00,
        'icms_enabled' => '0',
        'import_tax_percent' => 60.00,
        'import_tax_enabled' => '0',
        'import_tax_deduction_usd' => 30.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sincronização de tarifas (bandeiras, BCB e PayPal)
    |--------------------------------------------------------------------------
    | Endpoints públicos consultados pelo comando finance:sync-fees. Nenhuma
    | chave é necessária; o valor observado fica gravado em app_settings e só
    | substitui o spread da fatura quando o administrador liga a atualização
    | automática (card_spread_auto).
    */
    'fees' => [
        'timeout' => 15,
        'visa_url' => 'https://www.visa.com.br/cmsapi/fx/rates',
        'mastercard_url' => 'https://www.mastercard.us/settlement/currencyrate/conversion-rate',
        'bcb_url' => 'https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata',
        // Janela de conversões usadas para estimar o spread efetivo do PayPal.
        'paypal_window_days' => 120,
        'paypal_min_samples' => 3,
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
