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
 * Troca, nos modelos de fatura já gravados, o bloco antigo (ICMS/outros
 * tributos) pelo detalhamento em duas camadas: total no cartão, tributos da
 * importação e composição. Só mexe em quem ainda usa o texto padrão anterior;
 * modelos personalizados são preservados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = MailTemplates::defaults();

        foreach (['invoice_rate', 'invoice_due', 'invoice_overdue', 'invoice_paid'] as $key) {
            $row = DB::table('email_templates')->where('key', $key)->first();

            if ($row === null || ! isset($defaults[$key])) {
                continue;
            }

            $details = json_decode((string) $row->details, true);
            $details = is_array($details) ? $details : [];

            $isLegacy = array_key_exists('Outros tributos', $details)
                && ! array_key_exists('Tributos da importação', $details);

            if (! $isLegacy) {
                continue;
            }

            DB::table('email_templates')->where('id', $row->id)->update([
                'intro' => $defaults[$key]['intro'],
                'details' => json_encode($defaults[$key]['details'], JSON_UNESCAPED_UNICODE),
                'footnote' => $defaults[$key]['footnote'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Conteúdo editorial: não há reversão automática.
    }
};
