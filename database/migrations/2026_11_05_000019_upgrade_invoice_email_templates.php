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

use App\Support\Settings\MailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza os modelos de fatura já gravados no banco com o discriminativo
 * de valores e o botão para a fatura oficial.
 *
 * Só substitui o campo que ainda estiver igual ao padrão anterior; textos
 * personalizados pelo administrador são preservados (ele pode usar o botão
 * "Restaurar padrão" quando quiser adotar o conteúdo novo por inteiro).
 */
return new class extends Migration
{
    /**
     * @return array<string, array{subject: string, heading: string, intro: string, details: array<string, string>, action_text: string, action_url: string, footnote: string}>
     */
    private function legacy(): array
    {
        return [
            'invoice_rate' => [
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
            ],
            'invoice_due' => [
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
            ],
            'invoice_overdue' => [
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
            ],
            'invoice_paid' => [
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
            ],
        ];
    }

    public function up(): void
    {
        $defaults = MailTemplates::defaults();

        foreach ($this->legacy() as $key => $legacy) {
            $row = DB::table('email_templates')->where('key', $key)->first();
            $default = $defaults[$key] ?? null;

            if ($row === null || $default === null) {
                continue;
            }

            $update = [];

            foreach (['subject', 'heading', 'intro', 'action_text', 'action_url', 'footnote'] as $field) {
                if ((string) $row->{$field} === (string) $legacy[$field]) {
                    $update[$field] = (string) ($default[$field] ?? '');
                }
            }

            if ((json_decode((string) $row->details, true) ?: []) == $legacy['details']) {
                $update['details'] = json_encode($default['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($update !== []) {
                $update['updated_at'] = now();

                DB::table('email_templates')->where('id', $row->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // O conteúdo anterior é editorial; não há como restaurá-lo com segurança.
    }
};
