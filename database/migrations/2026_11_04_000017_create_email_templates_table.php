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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('subject', 255);
            $table->string('heading', 180);
            $table->text('intro');
            $table->json('details')->nullable();
            $table->string('action_text', 80)->nullable();
            $table->string('action_url', 255)->nullable();
            $table->text('footnote')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        $now = now();

        foreach (MailTemplates::defaults() as $key => $template) {
            DB::table('email_templates')->insert([
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'subject' => $template['subject'],
                'heading' => $template['heading'],
                'intro' => $template['intro'],
                'details' => json_encode($template['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'action_text' => $template['action_text'],
                'action_url' => $template['action_url'],
                'footnote' => $template['footnote'],
                'enabled' => $template['enabled'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
