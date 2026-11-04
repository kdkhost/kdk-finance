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

use App\Models\CronJob;
use App\Models\CurrencyConversion;
use App\Models\EmailTemplate;
use App\Models\LicenseService;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O Summernote é global: todo texto rico precisa ser reduzido a uma lista
 * segura antes de ir para o banco e renderizado como HTML depois.
 */
class RichTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.50', 'pctChange' => '0.10'],
                'EURBRL' => ['bid' => '6.00', 'pctChange' => '0.10'],
                'EURUSD' => ['bid' => '1.10', 'pctChange' => '0.10'],
                'USDEUR' => ['bid' => '0.909091', 'pctChange' => '0.10'],
            ]),
        ]);
    }

    private function user(string $role = User::ROLE_DEVELOPER): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_sanitize_keeps_safe_formatting_and_drops_every_dangerous_part(): void
    {
        $dirty = '<p onclick="alert(1)">Texto <strong>forte</strong> <span style="color:red">solto</span></p>'
            .'<script>alert(1)</script>'
            .'<img src="x" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">mau</a>'
            .'<a href="https://exemplo.com" target="_blank" rel="qualquer">bom</a>';

        $clean = RichText::sanitize($dirty);

        $this->assertIsString($clean);
        $this->assertStringContainsString('<strong>forte</strong>', $clean);
        $this->assertStringContainsString('solto', $clean);
        $this->assertStringContainsString('href="https://exemplo.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_plain_text_is_kept_untouched_and_rendered_with_line_breaks(): void
    {
        $this->assertSame('Cartão Bradesco', RichText::sanitize('Cartão Bradesco'));

        $html = RichText::render("Linha 1\nLinha 2 < 10");

        $this->assertStringContainsString('Linha 1<br>', $html);
        $this->assertStringContainsString('Linha 2 &lt; 10', $html);
    }

    public function test_text_flattens_html_for_titles_exports_and_search(): void
    {
        $text = RichText::text('<p>Fatura <strong>123</strong></p><ul><li>Primeira</li><li>Segunda</li></ul>');

        $this->assertSame("Fatura 123\nPrimeira\nSegunda", $text);
    }

    public function test_conversion_stores_sanitized_notes_and_renders_them(): void
    {
        $manager = $this->user(User::ROLE_FINANCIAL);

        $this->actingAs($manager)->post('/conversoes', [
            'description' => 'Servidor OVH',
            'from_currency' => 'EUR',
            'to_currency' => 'USD',
            'amount_from' => '100.00',
            'market_rate' => '1.10',
            'notes' => '<p>Banco <strong>Bradesco</strong></p><script>alert(1)</script>',
        ]);

        $conversion = CurrencyConversion::query()->firstOrFail();

        $this->assertSame('<p>Banco <strong>Bradesco</strong></p>', $conversion->notes);

        $this->actingAs($manager)
            ->get(route('conversions.show', $conversion))
            ->assertOk()
            ->assertSee('<strong>Bradesco</strong>', false)
            ->assertDontSee('alert(1)', false);
    }

    public function test_license_stores_sanitized_notes_and_renders_them(): void
    {
        $manager = $this->user(User::ROLE_FINANCIAL);

        $this->actingAs($manager)->post('/licencas', [
            'provider' => 'LicenseBoy',
            'product' => 'cPanel',
            'name' => 'cPanel · BR-02',
            'quantity' => '1',
            'unit_price' => '24.90',
            'currency' => 'USD',
            'billing_cycle' => LicenseService::CYCLE_MONTHLY,
            'status' => LicenseService::STATUS_ACTIVE,
            'notes' => '<ul><li>Limite de <em>30 contas</em></li></ul><iframe src="https://malicioso.test"></iframe>',
        ]);

        $license = LicenseService::query()->firstOrFail();

        $this->assertSame('<ul><li>Limite de <em>30 contas</em></li></ul>', $license->notes);

        $this->actingAs($manager)
            ->get(route('licenses.show', $license))
            ->assertOk()
            ->assertSee('<em>30 contas</em>', false)
            ->assertDontSee('iframe', false);
    }

    public function test_cron_description_is_sanitized_and_rendered(): void
    {
        $developer = $this->user();

        $this->actingAs($developer)->post('/crons', [
            'name' => 'Rotina rica',
            'type' => CronJob::TYPE_ARTISAN,
            'command' => 'inspire',
            'expression' => '0 3 * * *',
            'timezone' => 'America/Sao_Paulo',
            'description' => '<p>Tarefa <strong>diária</strong></p><script>alert(1)</script>',
        ]);

        $cron = CronJob::query()->firstOrFail();

        $this->assertSame('<p>Tarefa <strong>diária</strong></p>', $cron->description);

        $this->actingAs($developer)
            ->get(route('crons.show', $cron))
            ->assertOk()
            ->assertSee('<strong>diária</strong>', false);
    }

    public function test_email_template_sanitizes_intro_and_footnote_but_keeps_details_plain(): void
    {
        $developer = $this->user();

        $template = EmailTemplate::query()->where('key', 'invoice_due')->firstOrFail();

        $this->actingAs($developer)
            ->put(route('email-templates.update', $template), [
                'subject' => 'Assunto',
                'heading' => 'Título',
                'intro' => '<p>Olá, <strong>{{ usuario }}</strong></p><script>alert(1)</script>',
                'details' => "Fatura|<strong>{{ fatura }}</strong>",
                'footnote' => '<em>Equipe financeira</em>',
                'enabled' => '1',
            ])
            ->assertRedirect(route('email-templates.edit', $template));

        $template->refresh();

        $this->assertSame('<p>Olá, <strong>{{ usuario }}</strong></p>', $template->intro);
        $this->assertSame('<em>Equipe financeira</em>', $template->footnote);
        $this->assertSame(['Fatura' => '<strong>{{ fatura }}</strong>'], $template->details);

        $payload = $template->render(['usuario' => 'Marcelo', 'fatura' => 'OVH-1']);
        $this->assertSame('Olá, Marcelo', $payload['preheader']);
    }

    public function test_forms_use_the_rich_editor_except_on_machine_readable_fields(): void
    {
        $developer = $this->user();

        $cronForm = $this->actingAs($developer)->get(route('crons.create'));
        $cronForm->assertOk();
        $cronForm->assertSee('id="description" name="description" rows="2" maxlength="3000"', false);
        $this->assertStringNotContainsString('data-editor="plain"', (string) $cronForm->getContent());

        $template = EmailTemplate::query()->where('key', 'invoice_due')->firstOrFail();
        $mailForm = $this->actingAs($developer)->get(route('email-templates.edit', $template));
        $mailForm->assertOk();
        $mailForm->assertSee('id="intro" name="intro" rows="4" required maxlength="5000"', false);
        $mailForm->assertSee('id="details" name="details" rows="5" maxlength="2000" data-editor="plain"', false);

        $security = $this->actingAs($developer)->get(route('settings.index', ['tab' => 'security']));
        $security->assertOk();
        $this->assertSame(2, substr_count((string) $security->getContent(), 'data-editor="plain"'));

        $ovh = $this->actingAs($developer)->get(route('settings.index', ['tab' => 'ovh']));
        $ovh->assertOk();
        $this->assertSame(1, substr_count((string) $ovh->getContent(), 'data-editor="plain"'));

        $mail = $this->actingAs($developer)->get(route('settings.index', ['tab' => 'mail']));
        $mail->assertOk();
        $mail->assertSee('id="theme_footer" name="theme_footer" rows="3" maxlength="2500"', false);
        $mail->assertSee('id="theme_signature" name="theme_signature" rows="3" maxlength="1200"', false);
        $this->assertStringNotContainsString('data-editor="plain"', (string) $mail->getContent());
    }
}
