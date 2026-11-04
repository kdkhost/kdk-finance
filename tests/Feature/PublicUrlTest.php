<?php

/**
 * ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pasta public/ nunca pode aparecer na URL, em nenhum ambiente.
 *
 * Os testes cobrem o middleware (que atende NGINX, proxies e o servidor
 * embutido). No Apache, o .htaccess da raiz e o de public/ fazem o mesmo
 * redirecionamento antes do PHP; a validação em produção é feita por curl.
 */
class PublicUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_prefix_redirects_to_the_clean_login_url(): void
    {
        $response = $this->get('/public/login');

        $response->assertStatus(301);
        $response->assertRedirect('/login');
    }

    public function test_bare_public_directory_redirects_to_the_root(): void
    {
        $this->get('/public')->assertStatus(301)->assertRedirect('/');
        $this->get('/public/')->assertStatus(301)->assertRedirect('/');
    }

    public function test_redirect_keeps_the_query_string(): void
    {
        $this->get('/public/login?next=%2Fpainel')
            ->assertStatus(301)
            ->assertRedirect('/login?next=%2Fpainel');
    }

    public function test_post_requests_are_also_redirected(): void
    {
        $this->post('/public/login')->assertStatus(301)->assertRedirect('/login');
    }

    public function test_clean_url_keeps_working(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_redirect_carries_the_security_headers(): void
    {
        $response = $this->get('/public/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_generated_urls_never_contain_the_public_folder(): void
    {
        $this->assertStringNotContainsString('/public/', route('login'));
        $this->assertStringNotContainsString('/public/', asset('build/app.css'));
    }
}
