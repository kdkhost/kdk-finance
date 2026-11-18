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

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Support\Settings\MailTemplates;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MailTemplates::defaults() as $key => $template) {
            EmailTemplate::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'subject' => $template['subject'],
                    'heading' => $template['heading'],
                    'intro' => $template['intro'],
                    'details' => $template['details'],
                    'action_text' => $template['action_text'],
                    'action_url' => $template['action_url'],
                    'footnote' => $template['footnote'],
                    'enabled' => $template['enabled'],
                ]
            );
        }
    }
}
