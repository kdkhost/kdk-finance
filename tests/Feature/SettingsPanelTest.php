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

use App\Mail\ThemedMail;
use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceRateVariationNotification;
use App\Support\Settings\MailSettings;
use App\Support\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class SettingsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_group_cache_is_invalidated_on_write(): void
    {
        AppSetting::set('sample_key', 'um', 'sample');

        $this->assertSame('um', AppSetting::group('sample', ['sample_key' => 'x'])['sample_key']);

        AppSetting::set('sample_key', 'dois', 'sample');

        $this->assertSame('dois', AppSetting::group('sample', ['sample_key' => 'x'])['sample_key']);
    }

    public function test_mail_tab_renders_for_developers(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)
            ->get('/configuracoes?tab=mail')
            ->assertOk()
            ->assertSee('Servidor SMTP')
            ->assertSee('Tema dos e-mails')
            ->assertSee('mail_password', false)
            ->assertSee(route('settings.mail.preview'), false)
            ->assertSee('Enviar teste');
    }

    public function test_security_tab_renders_for_developers(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)
            ->get('/configuracoes?tab=security')
            ->assertOk()
            ->assertSee('Conexão e cabeçalhos')
            ->assertSee('Sessões ativas')
            ->assertSee('security_login_max_attempts', false)
            ->assertSee('Política de senhas');
    }

    public function test_mail_password_is_encrypted_and_blank_keeps_the_current_value(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)
            ->put(route('settings.mail'), $this->mailPayload(['mail_password' => 'SmtpSegredo2026']))
            ->assertRedirect(route('settings.index', ['tab' => 'mail']))
            ->assertSessionHas('success');

        $stored = (string) DB::table('app_settings')->where('key', 'mail_password')->value('value');

        $this->assertNotSame('SmtpSegredo2026', $stored);
        $this->assertNotSame('', $stored);
        $this->assertSame('SmtpSegredo2026', MailSettings::password());
        $this->assertTrue((bool) DB::table('app_settings')->where('key', 'mail_password')->value('is_secret'));

        $this->actingAs($developer)
            ->put(route('settings.mail'), $this->mailPayload(['mail_password' => '']))
            ->assertRedirect(route('settings.index', ['tab' => 'mail']));

        $this->assertSame('SmtpSegredo2026', MailSettings::password());
    }

    public function test_saved_smtp_is_applied_to_the_runtime_mailer(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)->put(route('settings.mail'), $this->mailPayload());

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.kdkhost.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('alertas@kdkhost.test', config('mail.mailers.smtp.username'));
        $this->assertSame('SmtpSegredo2026', config('mail.mailers.smtp.password'));
        $this->assertSame('alertas@kdkhost.test', config('mail.from.address'));

        AppSetting::set('mail_enabled', '0', 'mail');
        MailSettings::apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_test_mail_endpoint_sends_the_themed_message(): void
    {
        Mail::fake();
        $developer = $this->developer();

        $this->actingAs($developer)->put(route('settings.mail'), $this->mailPayload());

        $this->actingAs($developer)
            ->post(route('settings.mail.test'), ['test_email' => 'destino@kdkhost.test'])
            ->assertRedirect(route('settings.index', ['tab' => 'mail']))
            ->assertSessionHas('success');

        Mail::assertSent(ThemedMail::class, function (ThemedMail $mail): bool {
            return $mail->hasTo('destino@kdkhost.test')
                && $mail->subject === 'Teste de e-mail · KDK Finance';
        });

        $this->assertSame('ok', AppSetting::get('mail_last_test_status'));
        $this->assertNotNull(AppSetting::get('mail_last_test_at'));
    }

    public function test_test_mail_requires_a_complete_smtp_configuration(): void
    {
        Mail::fake();
        $developer = $this->developer();

        $this->actingAs($developer)
            ->post(route('settings.mail.test'), ['test_email' => 'destino@kdkhost.test'])
            ->assertRedirect(route('settings.index', ['tab' => 'mail']))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_email_theme_is_editable_and_used_by_the_preview(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)->put(route('settings.mail'), $this->mailPayload([
            'theme_brand' => 'KDK Teste',
            'theme_primary' => '#123456',
            'theme_footer' => 'Rodapé personalizado do teste.',
            'theme_support_url' => 'https://suporte.kdkhost.test',
        ]));

        $this->actingAs($developer)
            ->get(route('settings.mail.preview'))
            ->assertOk()
            ->assertSee('KDK Teste')
            ->assertSee('#123456')
            ->assertSee('Rodapé personalizado do teste.')
            ->assertSee('width=device-width', false)
            ->assertSee('@media only screen', false)
            ->assertSee('suporte.kdkhost.test');
    }

    public function test_alert_recipients_are_saved_and_parsed(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)->put(route('settings.mail'), $this->mailPayload([
            'mail_alert_recipients' => 'financeiro@kdkhost.test, socio@kdkhost.test',
        ]));

        $this->assertSame(
            ['financeiro@kdkhost.test', 'socio@kdkhost.test'],
            MailSettings::alertRecipients()
        );

        $this->actingAs($developer)
            ->get('/configuracoes?tab=mail')
            ->assertOk()
            ->assertSee('Alertas automáticos')
            ->assertSee('mail_alert_recipients', false)
            ->assertSee(route('email-templates.index'), false);
    }

    public function test_security_options_are_saved_in_app_settings(): void
    {
        $developer = $this->developer();

        $this->actingAs($developer)
            ->put(route('settings.security'), $this->securityPayload([
                'security_login_max_attempts' => 8,
                'security_allowed_hosts' => 'https://kdkhost.com.br, *.kdkhost.com.br',
            ]))
            ->assertRedirect(route('settings.index', ['tab' => 'security']))
            ->assertSessionHas('success');

        $this->assertSame('8', AppSetting::get('security_login_max_attempts'));
        $this->assertSame('kdkhost.com.br, *.kdkhost.com.br', AppSetting::get('security_allowed_hosts'));
        $this->assertSame('strict', AppSetting::get('security_session_same_site'));
    }

    public function test_csp_and_static_headers_can_be_turned_off_from_the_panel(): void
    {
        AppSetting::set('security_csp_enabled', '0', 'security');
        AppSetting::set('security_headers_enabled', '0', 'security');

        $this->get('/login')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('X-Content-Type-Options');

        AppSetting::set('security_headers_enabled', '1', 'security');
        AppSetting::set('security_csp_enabled', '1', 'security');

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertNotNull($this->get('/login')->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_is_sent_only_on_https_when_enabled(): void
    {
        $this->get('https://painel.kdkhost.test/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        AppSetting::set('security_hsts_enabled', '0', 'security');

        $this->get('https://painel.kdkhost.test/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_host_restriction_blocks_unknown_domains(): void
    {
        AppSetting::set('security_trust_hosts', '1', 'security');
        AppSetting::set('security_allowed_hosts', 'kdkhost.test, *.kdkhost.test', 'security');

        $this->get('http://invasor.test/login')
            ->assertStatus(400)
            ->assertSee('Endereço não permitido');

        $this->get('http://app.kdkhost.test/login')
            ->assertOk();
    }

    public function test_login_lockout_uses_the_attempts_saved_in_the_panel(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SenhaCorreta2026'),
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        AppSetting::set('security_login_max_attempts', '3', 'security');
        AppSetting::set('security_login_decay_seconds', '120', 'security');

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'errada']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'SenhaCorreta2026']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', (string) session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_password_policy_comes_from_the_panel(): void
    {
        AppSetting::set('security_password_min', '16', 'security');
        AppSetting::set('security_password_mixed_case', '1', 'security');
        AppSetting::set('security_password_numbers', '1', 'security');
        AppSetting::set('security_password_symbols', '1', 'security');

        SecuritySettings::applyPasswordPolicy();

        $short = Validator::make(['password' => 'SenhaForte12345'], ['password' => ['required', Password::defaults()]]);
        $this->assertTrue($short->fails());

        $strong = Validator::make(['password' => 'SenhaForte2026!KDK'], ['password' => ['required', Password::defaults()]]);
        $this->assertFalse($strong->fails());
    }

    public function test_session_revocation_reports_the_active_driver(): void
    {
        $developer = $this->developer();

        config(['session.driver' => 'array']);

        $this->actingAs($developer)
            ->post(route('settings.sessions.revoke'))
            ->assertRedirect(route('settings.index', ['tab' => 'security']))
            ->assertSessionHas('error');
    }

    public function test_non_developers_cannot_change_mail_or_security(): void
    {
        foreach ([User::ROLE_FINANCIAL, User::ROLE_VIEWER] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)->put(route('settings.mail'), [])->assertForbidden();
            $this->actingAs($user)->put(route('settings.security'), [])->assertForbidden();
            $this->actingAs($user)->get(route('settings.mail.preview'))->assertForbidden();
            $this->actingAs($user)->post(route('settings.sessions.revoke'))->assertForbidden();
        }
    }

    public function test_invoice_rate_notification_uses_the_themed_mailable(): void
    {
        $user = $this->developer();

        $invoice = Invoice::query()->create([
            'external_reference' => 'THEME-1',
            'description' => 'Fatura OVHcloud',
            'currency' => 'EUR',
            'amount_original' => 100.00,
            'exchange_rate' => 5.5000,
            'total_brl' => 0,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(5),
            'rate_locked' => false,
        ]);

        $mail = (new InvoiceRateVariationNotification($invoice, 2.5, 100.0, 102.5))->toMail($user);

        $this->assertInstanceOf(ThemedMail::class, $mail);
        $this->assertStringContainsString('Variação cambial', (string) $mail->subject);
        $this->assertSame('R$ 102,50', $mail->details['Valor atualizado']);
        $this->assertSame(route('invoices.show', $invoice), $mail->actionUrl);
    }

    private function developer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mailPayload(array $overrides = []): array
    {
        return array_merge([
            'mail_enabled' => '1',
            'mail_host' => 'mail.kdkhost.test',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_auth' => '1',
            'mail_username' => 'alertas@kdkhost.test',
            'mail_password' => 'SmtpSegredo2026',
            'mail_timeout' => 15,
            'mail_from_address' => 'alertas@kdkhost.test',
            'mail_from_name' => 'KDK Finance',
            'mail_reply_to_address' => '',
            'mail_reply_to_name' => '',
            'theme_brand' => 'KDK Finance',
            'theme_logo_enabled' => '1',
            'theme_primary' => '#0f9d76',
            'theme_accent' => '#0a2540',
            'theme_background' => '#f1f5f9',
            'theme_text' => '#1f2937',
            'theme_muted' => '#64748b',
            'theme_footer' => 'Mensagem automática do painel financeiro.',
            'theme_signature' => 'Equipe KDKHost',
            'theme_support_email' => 'contato@kdkhost.com.br',
            'theme_support_url' => 'https://kdkhost.com.br',
            'theme_company' => 'KDKHost Soluções',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function securityPayload(array $overrides = []): array
    {
        return array_merge([
            'security_force_https' => '1',
            'security_hsts_enabled' => '1',
            'security_hsts_max_age' => 365,
            'security_headers_enabled' => '1',
            'security_csp_enabled' => '1',
            'security_frame_options' => 'SAMEORIGIN',
            'security_trust_hosts' => '1',
            'security_allowed_hosts' => 'kdkhost.com.br',
            'security_trusted_proxies' => '',
            'security_no_store' => '1',
            'security_session_lifetime' => 120,
            'security_session_encrypt' => '1',
            'security_session_secure' => '1',
            'security_session_same_site' => 'strict',
            'security_session_expire_on_close' => '0',
            'security_login_max_attempts' => 5,
            'security_login_decay_seconds' => 60,
            'security_password_min' => 10,
            'security_password_mixed_case' => '1',
            'security_password_numbers' => '1',
            'security_password_symbols' => '0',
        ], $overrides);
    }
}
