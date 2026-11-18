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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renomeia o rótulo "Moeda original" para "Valor original" nos modelos de
 * fatura. O valor continua o mesmo; a troca deixa claro que a linha mostra
 * o montante na moeda do fornecedor (US$ ou €), não o nome da moeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_rate', 'invoice_due', 'invoice_overdue', 'invoice_paid'] as $key) {
            $row = DB::table('email_templates')->where('key', $key)->first();

            if ($row === null) {
                continue;
            }

            $details = json_decode((string) $row->details, true) ?: [];

            if (! array_key_exists('Moeda original', $details)) {
                continue;
            }

            $renamed = [];

            foreach ($details as $label => $value) {
                $renamed[$label === 'Moeda original' ? 'Valor original' : $label] = $value;
            }

            DB::table('email_templates')->where('id', $row->id)->update([
                'details' => json_encode($renamed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // A renomeação é editorial; não há necessidade de reverter.
    }
};
