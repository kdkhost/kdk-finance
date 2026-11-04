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

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\Settings\MailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateCenterTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = User::ROLE_DEVELOPER): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_migration_installs_every_default_template(): void
    {
        $this->assertSame(
            count(MailTemplates::defaults()),
            EmailTemplate::query()->count()
        );

        $this->assertDatabaseHas('email_templates', ['key' => 'invoice_due', 'enabled' => true]);
        $this->assertDatabaseHas('email_templates', ['key' => 'sync_failure', 'enabled' => true]);
    }

    public function test_developer_opens_the_template_center(): void
    {
        $response = $this->actingAs($this->user())->get(route('email-templates.index'));

        $response->assertOk();
        $response->assertSee('Modelos transacionais');
        $response->assertSee('Pagamento confirmado');
        $response->assertSee('Falha em cron ou sincronização');
    }

    public function test_edit_page_lists_the_available_variables(): void
    {
        $template = EmailTemplate::query()->where('key', 'invoice_due')->firstOrFail();

        $response = $this->actingAs($this->user())->get(route('email-templates.edit', $template));

        $response->assertOk();
        $response->assertSee('{{ fatura }}', false);
        $response->assertSee('{{ vencimento }}', false);
        $response->assertSee('Bloco de detalhes');
    }

    public function test_developer_updates_the_content_and_preview_uses_it(): void
    {
        $template = EmailTemplate::query()->where('key', 'invoice_due')->firstOrFail();

        $this->actingAs($this->user())
            ->put(route('email-templates.update', $template), [
                'subject' => 'Aviso de vencimento {{ fatura }}',
                'heading' => 'Vencimento personalizado',
                'intro' => 'Olá, {{ usuario }}. A fatura {{ fatura }} vence em {{ vencimento }}.',
                'details' => "Fatura|{{ fatura }}\nValor|{{ valor }}",
                'action_text' => 'Ver fatura',
                'action_url' => '{{ link }}',
                'footnote' => 'Modelo ajustado pelo painel.',
                'enabled' => '1',
            ])
            ->assertRedirect(route('email-templates.edit', $template))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('email_templates', [
            'key' => 'invoice_due',
            'heading' => 'Vencimento personalizado',
            'enabled' => true,
        ]);

        $preview = $this->actingAs($this->user())->get(route('email-templates.preview', $template));

        $preview->assertOk();
        $preview->assertSee('Vencimento personalizado');
        $preview->assertSee('OVH-2026-001');
    }

    public function test_details_text_is_parsed_into_label_and_value_pairs(): void
    {
        $template = EmailTemplate::query()->where('key', 'invoice_due')->firstOrFail();

        $this->actingAs($this->user())
            ->put(route('email-templates.update', $template), [
                'subject' => 'Assunto',
                'heading' => 'Título',
                'intro' => 'Texto',
                'details' => "Fatura|{{ fatura }}\nLinha sem barra\n\nValor|{{ valor }}",
                'action_text' => '',
                'action_url' => '',
                'footnote' => '',
                'enabled' => '1',
            ]);

        $template->refresh();

        $this->assertSame(['Fatura' => '{{ fatura }}', 'Valor' => '{{ valor }}'], $template->details);
    }

    public function test_toggle_disables_and_restore_brings_the_factory_text_back(): void
    {
        $template = EmailTemplate::query()->where('key', 'license_expiring')->firstOrFail();

        $this->actingAs($this->user())
            ->post(route('email-templates.toggle', $template))
            ->assertSessionHas('success');

        $this->assertFalse($template->fresh()->enabled);

        $this->actingAs($this->user())
            ->put(route('email-templates.update', $template), [
                'subject' => 'Editado',
                'heading' => 'Editado',
                'intro' => 'Editado',
                'details' => '',
                'enabled' => '0',
            ]);

        $this->actingAs($this->user())
            ->post(route('email-templates.restore', $template))
            ->assertRedirect(route('email-templates.edit', $template))
            ->assertSessionHas('success');

        $defaults = MailTemplates::defaults()['license_expiring'];
        $template->refresh();

        $this->assertSame($defaults['subject'], $template->subject);
        $this->assertSame($defaults['heading'], $template->heading);
        $this->assertTrue($template->enabled);
    }

    public function test_test_send_requires_configured_smtp(): void
    {
        $template = EmailTemplate::query()->where('key', 'test')->firstOrFail();

        $this->actingAs($this->user())
            ->post(route('email-templates.test', $template), ['test_email' => 'pessoa@exemplo.com'])
            ->assertSessionHas('error');
    }

    public function test_test_send_returns_success_when_smtp_is_configured(): void
    {
        AppSetting::set('mail_enabled', '1', 'mail');
        AppSetting::set('mail_host', 'smtp.exemplo.com', 'mail');
        AppSetting::set('mail_from_address', 'alertas@exemplo.com', 'mail');

        \Illuminate\Support\Facades\Mail::fake();

        $template = EmailTemplate::query()->where('key', 'test')->firstOrFail();

        $this->actingAs($this->user())
            ->post(route('email-templates.test', $template), ['test_email' => 'pessoa@exemplo.com'])
            ->assertSessionHas('success');

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ThemedMail::class);
    }

    public function test_financial_manager_cannot_manage_templates(): void
    {
        $this->actingAs($this->user(User::ROLE_FINANCIAL))
            ->get(route('email-templates.index'))
            ->assertForbidden();
    }

    public function test_unknown_placeholder_is_left_untouched(): void
    {
        $rendered = MailTemplates::interpolate('Olá {{ usuario }}, {{ token_desconhecido }}', ['usuario' => 'Ana']);

        $this->assertSame('Olá Ana, {{ token_desconhecido }}', $rendered);
    }

    public function test_notify_alerts_command_is_safe_without_smtp(): void
    {
        $this->artisan('finance:notify-alerts')
            ->expectsOutputToContain('SMTP não configurado')
            ->assertSuccessful();
    }

    public function test_welcome_email_is_skipped_without_smtp_when_creating_a_user(): void
    {
        $this->actingAs($this->user())
            ->post(route('users.store'), [
                'name' => 'Nova Pessoa',
                'email' => 'nova@exemplo.com',
                'role' => User::ROLE_VIEWER,
                'password' => 'SenhaForte#2026',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'nova@exemplo.com']);
    }
}
