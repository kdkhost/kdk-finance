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

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertStringContainsString('default-src \'self\'', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_inline_scripts_use_the_same_nonce_as_the_policy(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/');
        $response->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $policy);

        preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $policy, $matches);
        $nonce = $matches[1] ?? '';

        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString('nonce="'.$nonce.'"', $response->getContent());

        foreach ($this->inlineScriptTags($response->getContent()) as $tag) {
            $this->assertStringContainsString('nonce="'.$nonce.'"', $tag);
        }
    }

    public function test_error_pages_also_use_the_policy_nonce(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($viewer)->get('/configuracoes');
        $response->assertForbidden();

        $policy = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $policy, $matches);
        $nonce = $matches[1] ?? '';

        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString('nonce="'.$nonce.'"', $response->getContent());
    }

    public function test_authenticated_pages_are_not_cached_by_the_browser(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/faturas');
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_deactivated_user_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_viewer_can_read_every_section(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        foreach (['/', '/faturas', '/servicos', '/licencas', '/conversoes', '/notificacoes', '/perfil', '/painel/ao-vivo'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    public function test_viewer_cannot_write_or_reach_administration(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);
        $invoice = Invoice::query()->create([
            'external_reference' => 'SEC-'.uniqid(),
            'description' => 'Fatura OVHcloud',
            'currency' => 'EUR',
            'amount_original' => 100.00,
            'exchange_rate' => 5.5000,
            'total_brl' => 0,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(5),
            'rate_locked' => false,
        ]);

        $this->actingAs($viewer)->get('/configuracoes')->assertForbidden();
        $this->actingAs($viewer)->get('/usuarios')->assertForbidden();
        $this->actingAs($viewer)->get('/crons')->assertForbidden();
        $this->actingAs($viewer)->get('/faturas/nova')->assertForbidden();
        $this->actingAs($viewer)->post('/faturas', [])->assertForbidden();
        $this->actingAs($viewer)->get('/licencas/nova')->assertForbidden();
        $this->actingAs($viewer)->get('/conversoes/nova')->assertForbidden();
        $this->actingAs($viewer)->post('/faturas/'.$invoice->id.'/pagar')->assertForbidden();
        $this->actingAs($viewer)->get('/faturas-exportar')->assertForbidden();
    }

    public function test_user_password_policy_requires_length_case_and_number(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $base = [
            'name' => 'Novo Usuário',
            'email' => 'novo@kdkhost.com.br',
            'role' => User::ROLE_VIEWER,
        ];

        $this->actingAs($admin)
            ->post('/usuarios', $base + ['password' => 'Curta1'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post('/usuarios', $base + ['password' => 'somente-minusculas1'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post('/usuarios', $base + ['password' => 'SemNumeroAqui'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)
            ->post('/usuarios', $base + ['password' => 'SenhaForte2026'])
            ->assertSessionHasNoErrors();

        $created = User::query()->where('email', 'novo@kdkhost.com.br')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('SenhaForte2026', $created->password));
    }

    public function test_login_page_no_longer_advertises_the_old_password_length(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('Mínimo de 8 caracteres', false);
    }

    /**
     * Scripts executáveis (sem src e sem type="application/json") precisam do nonce.
     *
     * @return list<string>
     */
    private function inlineScriptTags(string $html): array
    {
        preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>.*?<\/script>/is', $html, $matches);

        return array_values(array_filter(
            $matches[0] ?? [],
            static fn (string $tag): bool => ! preg_match('/^<script[^>]*type="application\/json"/i', $tag)
        ));
    }
}
