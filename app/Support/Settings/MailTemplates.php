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

/**
 * Catálogo de modelos transacionais e seus valores de exemplo.
 *
 * O conteúdo editado pelo painel fica na tabela email_templates. Este catálogo
 * guarda apenas o padrão de fábrica, usado na instalação e no botão restaurar.
 */
final class MailTemplates
{
    /**
     * @return array<string, array{name: string, description: string, subject: string, heading: string, intro: string, details: array<string, string>, action_text: ?string, action_url: ?string, footnote: ?string, enabled: bool, variables: list<string>}>
     */
    public static function defaults(): array
    {
        return [
            'test' => [
                'name' => 'Teste de envio',
                'description' => 'Confirma o SMTP e o tema visual. É enviado pelo botão "Enviar teste".',
                'subject' => 'Teste de e-mail · {{ marca }}',
                'heading' => 'Teste de envio concluído',
                'intro' => 'Olá, {{ usuario }}. Se você recebeu esta mensagem, o servidor SMTP configurado no painel está respondendo e o tema dos e-mails está sendo aplicado.',
                'details' => [
                    'Servidor' => '{{ servidor }}',
                    'Criptografia' => '{{ criptografia }}',
                    'Remetente' => '{{ remetente }}',
                ],
                'action_text' => 'Abrir o painel',
                'action_url' => '{{ link }}',
                'footnote' => 'Mensagem de teste gerada pelo painel de configurações. Nenhum dado financeiro foi incluído.',
                'enabled' => true,
                'variables' => ['marca', 'usuario', 'servidor', 'criptografia', 'remetente', 'link'],
            ],
            'invoice_rate' => [
                'name' => 'Variação cambial da fatura',
                'description' => 'Enviado quando a atualização das cotações muda o valor de uma fatura pendente acima do limite configurado.',
                'subject' => 'Variação cambial de {{ variacao }} na fatura {{ fatura }}',
                'heading' => 'Variação cambial na fatura',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" sofreu {{ direcao }} de {{ variacao }} após a atualização das cotações. O valor convertido e as tarifas estão discriminados abaixo.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Fornecedor' => '{{ fornecedor }}',
                    'Variação' => '{{ variacao_sinal }}',
                    'Valor anterior' => '{{ valor_anterior }}',
                    'Valor atualizado' => '{{ valor_atual }}',
                    'Moeda original' => '{{ valor_original }}',
                    'Cotação aplicada' => '{{ cotacao }}',
                    'Valor convertido' => '{{ valor_base }}',
                    'Spread' => '{{ spread }}',
                    'IOF' => '{{ iof }}',
                    'ICMS' => '{{ icms }}',
                    'Outros tributos' => '{{ outros_tributos }}',
                    'Total a reservar' => '{{ total_brl }}',
                ],
                'action_text' => 'Ver fatura oficial',
                'action_url' => '{{ link_oficial }}',
                'footnote' => 'O botão abre a área oficial de pagamento do fornecedor. O valor em reais já inclui todas as tarifas configuradas no painel.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'fornecedor', 'direcao', 'variacao', 'variacao_sinal', 'valor_anterior', 'valor_atual', 'valor_original', 'cotacao', 'valor_base', 'spread', 'iof', 'icms', 'outros_tributos', 'total_brl', 'link_oficial'],
            ],
            'invoice_due' => [
                'name' => 'Fatura próxima do vencimento',
                'description' => 'Enviado pelo cron diário para faturas pendentes que vencem nos próximos dias.',
                'subject' => 'Fatura {{ fatura }} vence em {{ dias }} dia(s) · reserva {{ total_brl }}',
                'heading' => 'Fatura próxima do vencimento',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" vence em {{ vencimento }} e continua pendente. Abaixo está o valor convertido para real, com spread, IOF, ICMS e demais tributos discriminados para você reservar o total exato.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Fornecedor' => '{{ fornecedor }}',
                    'Vencimento' => '{{ vencimento }}',
                    'Dias restantes' => '{{ dias }}',
                    'Moeda original' => '{{ valor_original }}',
                    'Cotação aplicada' => '{{ cotacao }}',
                    'Valor convertido' => '{{ valor_base }}',
                    'Spread' => '{{ spread }}',
                    'IOF' => '{{ iof }}',
                    'ICMS' => '{{ icms }}',
                    'Outros tributos' => '{{ outros_tributos }}',
                    'Total a reservar' => '{{ total_brl }}',
                ],
                'action_text' => 'Pagar fatura',
                'action_url' => '{{ link_oficial }}',
                'footnote' => 'O botão abre a área oficial de pagamento do fornecedor. O total em reais usa a cotação e as tarifas gravadas no painel.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'fornecedor', 'vencimento', 'dias', 'valor_original', 'cotacao', 'valor_base', 'spread', 'iof', 'icms', 'outros_tributos', 'total_brl', 'link_oficial'],
            ],
            'invoice_overdue' => [
                'name' => 'Fatura vencida',
                'description' => 'Enviado quando uma fatura pendente passa do vencimento.',
                'subject' => 'Fatura {{ fatura }} está vencida · reserva {{ total_brl }}',
                'heading' => 'Fatura vencida',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" venceu em {{ vencimento }} e continua pendente. Regularize pelo botão e reserve o total abaixo, que já inclui spread, IOF, ICMS e demais tributos.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Fornecedor' => '{{ fornecedor }}',
                    'Vencimento' => '{{ vencimento }}',
                    'Dias em atraso' => '{{ dias_atraso }}',
                    'Moeda original' => '{{ valor_original }}',
                    'Cotação aplicada' => '{{ cotacao }}',
                    'Valor convertido' => '{{ valor_base }}',
                    'Spread' => '{{ spread }}',
                    'IOF' => '{{ iof }}',
                    'ICMS' => '{{ icms }}',
                    'Outros tributos' => '{{ outros_tributos }}',
                    'Total a reservar' => '{{ total_brl }}',
                ],
                'action_text' => 'Regularizar fatura',
                'action_url' => '{{ link_oficial }}',
                'footnote' => 'O botão abre a área oficial de pagamento do fornecedor. O total em reais usa a cotação e as tarifas gravadas no painel.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'fornecedor', 'vencimento', 'dias_atraso', 'valor_original', 'cotacao', 'valor_base', 'spread', 'iof', 'icms', 'outros_tributos', 'total_brl', 'link_oficial'],
            ],
            'invoice_paid' => [
                'name' => 'Pagamento confirmado',
                'description' => 'Enviado quando uma fatura é marcada como paga no painel.',
                'subject' => 'Fatura {{ fatura }} foi paga · {{ total_brl }}',
                'heading' => 'Pagamento confirmado',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" foi marcada como paga em {{ data_pagamento }}. Confira abaixo o valor original, a cotação aplicada e as tarifas que compõem o total liquidado.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Fornecedor' => '{{ fornecedor }}',
                    'Moeda original' => '{{ valor_original }}',
                    'Cotação aplicada' => '{{ cotacao }}',
                    'Valor convertido' => '{{ valor_base }}',
                    'Spread' => '{{ spread }}',
                    'IOF' => '{{ iof }}',
                    'ICMS' => '{{ icms }}',
                    'Outros tributos' => '{{ outros_tributos }}',
                    'Total pago' => '{{ total_brl }}',
                    'Pago em' => '{{ data_pagamento }}',
                ],
                'action_text' => 'Ver fatura oficial',
                'action_url' => '{{ link_oficial }}',
                'footnote' => 'O botão abre a fatura oficial do fornecedor. O valor em reais é o total liquidado nesta baixa.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'fornecedor', 'valor_original', 'cotacao', 'valor_base', 'spread', 'iof', 'icms', 'outros_tributos', 'total_brl', 'data_pagamento', 'link_oficial'],
            ],
            'license_expiring' => [
                'name' => 'Licença próxima do vencimento',
                'description' => 'Enviado pelo cron diário para licenças ativas que vencem nos próximos dias.',
                'subject' => 'Licença {{ licenca }} vence em {{ dias }} dia(s)',
                'heading' => 'Licença próxima do vencimento',
                'intro' => 'Olá, {{ usuario }}. A licença "{{ licenca }}" vence em {{ vencimento }} e precisa de renovação.',
                'details' => [
                    'Licença' => '{{ licenca }}',
                    'Produto' => '{{ produto }}',
                    'Vencimento' => '{{ vencimento }}',
                    'Dias restantes' => '{{ dias }}',
                    'Valor' => '{{ valor }}',
                ],
                'action_text' => 'Abrir licença',
                'action_url' => '{{ link }}',
                'footnote' => 'Aviso automático do controle de licenças.',
                'enabled' => true,
                'variables' => ['usuario', 'licenca', 'produto', 'vencimento', 'dias', 'valor', 'link'],
            ],
            'sync_failure' => [
                'name' => 'Falha em cron ou sincronização',
                'description' => 'Enviado quando um cron da Central de cron termina com erro.',
                'subject' => 'Falha na rotina {{ rotina }}',
                'heading' => 'Rotina com falha',
                'intro' => 'Olá, {{ usuario }}. A rotina "{{ rotina }}" terminou com erro em {{ data }}.',
                'details' => [
                    'Rotina' => '{{ rotina }}',
                    'Comando' => '{{ comando }}',
                    'Data' => '{{ data }}',
                    'Erro' => '{{ erro }}',
                ],
                'action_text' => 'Abrir a Central de cron',
                'action_url' => '{{ link }}',
                'footnote' => 'A saída completa e o histórico de execuções estão na Central de cron.',
                'enabled' => true,
                'variables' => ['usuario', 'rotina', 'comando', 'data', 'erro', 'link'],
            ],
            'welcome' => [
                'name' => 'Boas-vindas ao novo usuário',
                'description' => 'Enviado quando um usuário é criado pelo painel.',
                'subject' => 'Sua conta no {{ marca }} foi criada',
                'heading' => 'Bem-vindo ao painel financeiro',
                'intro' => 'Olá, {{ usuario }}. Sua conta foi criada com o perfil {{ perfil }}. Use o botão abaixo para entrar; a senha inicial foi informada pelo administrador.',
                'details' => [
                    'E-mail' => '{{ email }}',
                    'Perfil' => '{{ perfil }}',
                ],
                'action_text' => 'Entrar no painel',
                'action_url' => '{{ link }}',
                'footnote' => 'Se você não esperava este acesso, avise o administrador.',
                'enabled' => true,
                'variables' => ['marca', 'usuario', 'email', 'perfil', 'link'],
            ],
        ];
    }

    /**
     * Variáveis de exemplo usadas na prévia e no envio de teste.
     *
     * @return array<string, string>
     */
    public static function samples(string $key): array
    {
        $samples = [
            'marca' => MailTheme::brand(),
            'usuario' => 'Marcelo Brad',
            'link' => url('/'),
            'servidor' => MailSettings::host().':'.MailSettings::port(),
            'criptografia' => strtoupper(MailSettings::encryption()),
            'remetente' => MailSettings::fromName().' <'.MailSettings::fromAddress().'>',
            'descricao' => 'OVHcloud — serviços de novembro',
            'fatura' => 'OVH-2026-001',
            'fornecedor' => 'OVHcloud',
            'moeda' => 'Euro (EUR)',
            'direcao' => 'alta',
            'variacao' => '2,47%',
            'variacao_sinal' => '+2,47%',
            'valor_original' => '€ 123,45',
            'cotacao' => 'R$ 6,1234',
            'valor_base' => 'R$ 755,93',
            'spread' => '2,00% · R$ 15,12',
            'iof' => '3,50% · R$ 26,99',
            'icms' => '0,00% · R$ 0,00',
            'outros_tributos' => '1,00% · R$ 7,71',
            'total_tributos' => 'R$ 49,82',
            'valor_anterior' => 'R$ 786,30',
            'valor_atual' => 'R$ 805,75',
            'valor' => 'R$ 805,75',
            'total_brl' => 'R$ 805,75',
            'vencimento' => now()->addDays(3)->format('d/m/Y'),
            'dias' => '3',
            'dias_atraso' => '2',
            'data_pagamento' => now()->format('d/m/Y H:i'),
            'licenca' => 'LiteSpeed Web Server',
            'produto' => 'LiteSpeed Enterprise',
            'rotina' => 'Sincronizar faturas OVHcloud',
            'comando' => 'finance:sync-invoices',
            'data' => now()->format('d/m/Y H:i'),
            'erro' => 'Tempo limite esgotado ao consultar a OVHcloud.',
            'email' => 'novo.usuario@kdkhost.com.br',
            'perfil' => 'Gestor financeiro',
            'link_oficial' => 'https://www.ovh.com/manager/#/dedicated/billing/history',
            'link_painel' => url('/'),
        ];

        $variables = self::defaults()[$key]['variables'] ?? [];

        return array_intersect_key($samples, array_flip($variables));
    }

    /**
     * Monta o placeholder exibido ao editor, sem que o Blade o interprete.
     */
    public static function placeholder(string $variable): string
    {
        return '{{ '.$variable.' }}';
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public static function interpolate(?string $value, array $variables): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $match) use ($variables): string {
                $key = strtolower($match[1]);

                return array_key_exists($key, $variables) ? (string) $variables[$key] : $match[0];
            },
            $value
        );
    }
}
