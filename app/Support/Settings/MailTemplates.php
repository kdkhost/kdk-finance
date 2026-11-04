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
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" sofreu {{ direcao }} de {{ variacao }} após a atualização das cotações.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Variação' => '{{ variacao_sinal }}',
                    'Valor anterior' => '{{ valor_anterior }}',
                    'Valor atualizado' => '{{ valor_atual }}',
                ],
                'action_text' => 'Abrir fatura',
                'action_url' => '{{ link }}',
                'footnote' => 'Este alerta foi gerado automaticamente pelo monitoramento de câmbio.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'direcao', 'variacao', 'variacao_sinal', 'valor_anterior', 'valor_atual', 'link'],
            ],
            'invoice_due' => [
                'name' => 'Fatura próxima do vencimento',
                'description' => 'Enviado pelo cron diário para faturas pendentes que vencem nos próximos dias.',
                'subject' => 'Fatura {{ fatura }} vence em {{ dias }} dia(s)',
                'heading' => 'Fatura próxima do vencimento',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" vence em {{ vencimento }} e continua pendente.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Vencimento' => '{{ vencimento }}',
                    'Dias restantes' => '{{ dias }}',
                    'Valor' => '{{ valor }}',
                ],
                'action_text' => 'Abrir fatura',
                'action_url' => '{{ link }}',
                'footnote' => 'Aviso automático do controle financeiro.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'vencimento', 'dias', 'valor', 'link'],
            ],
            'invoice_overdue' => [
                'name' => 'Fatura vencida',
                'description' => 'Enviado quando uma fatura pendente passa do vencimento.',
                'subject' => 'Fatura {{ fatura }} está vencida',
                'heading' => 'Fatura vencida',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" venceu em {{ vencimento }} e continua pendente, no valor de {{ valor }}.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Vencimento' => '{{ vencimento }}',
                    'Dias em atraso' => '{{ dias_atraso }}',
                    'Valor' => '{{ valor }}',
                ],
                'action_text' => 'Abrir fatura',
                'action_url' => '{{ link }}',
                'footnote' => 'Aviso automático do controle financeiro.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'vencimento', 'dias_atraso', 'valor', 'link'],
            ],
            'invoice_paid' => [
                'name' => 'Pagamento confirmado',
                'description' => 'Enviado quando uma fatura é marcada como paga no painel.',
                'subject' => 'Fatura {{ fatura }} foi paga',
                'heading' => 'Pagamento confirmado',
                'intro' => 'Olá, {{ usuario }}. A fatura "{{ descricao }}" foi marcada como paga em {{ data_pagamento }}.',
                'details' => [
                    'Fatura' => '{{ fatura }}',
                    'Valor' => '{{ valor }}',
                    'Pago em' => '{{ data_pagamento }}',
                ],
                'action_text' => 'Abrir fatura',
                'action_url' => '{{ link }}',
                'footnote' => 'Confirmação automática do controle financeiro.',
                'enabled' => true,
                'variables' => ['usuario', 'descricao', 'fatura', 'valor', 'data_pagamento', 'link'],
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
            'direcao' => 'alta',
            'variacao' => '2,45%',
            'variacao_sinal' => '+2,45%',
            'valor_anterior' => 'R$ 1.250,00',
            'valor_atual' => 'R$ 1.280,63',
            'valor' => 'R$ 1.280,63',
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
