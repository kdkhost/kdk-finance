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

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTreeviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_drops_the_user_block_and_renders_a_treeview(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertDontSee('sidebar-user', false);
        $response->assertDontSee('DESENVOLVEDOR / ADMINISTRADOR');
        $response->assertSee('data-treeview-toggle', false);
        $response->assertSee('aria-controls="sidebar-menu-financeiro"', false);
        $response->assertSee('aria-expanded="true"', false);
        $response->assertSee('Operação');
        $response->assertSee('Financeiro');
        $response->assertDontSee('data-tree-item="sistema"', false);
        $response->assertDontSee('data-tree-item="crons"', false);
    }

    public function test_licenses_live_under_infrastructure_instead_of_finance(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/');
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('data-tree-item="infraestrutura"', false);
        $response->assertSee('data-tree-item="licencas"', false);
        $response->assertSeeInOrder([
            'data-tree-item="infraestrutura"',
            '<span>Serviços OVH</span>',
            'data-tree-item="licencas"',
            'Tabela de preços',
        ], false);

        // Licenças não pode aparecer antes do fim do bloco Financeiro.
        $posFinanceiro = strpos($html, 'data-tree-item="financeiro"');
        $posInfra = strpos($html, 'data-tree-item="infraestrutura"');
        $posLicencas = strpos($html, 'data-tree-item="licencas"');

        $this->assertNotFalse($posFinanceiro);
        $this->assertNotFalse($posInfra);
        $this->assertNotFalse($posLicencas);
        $this->assertGreaterThan($posFinanceiro, $posInfra);
        $this->assertGreaterThan($posInfra, $posLicencas);
    }

    public function test_internal_tabs_never_become_sidebar_entries(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        // Nenhuma página do menu aponta para uma aba interna.
        $dashboard = $this->actingAs($user)->get('/');
        $dashboard->assertOk();
        $dashboard->assertDontSee('?tab=', false);
        $dashboard->assertDontSee('data-tree-item="configuracoes"', false);
        $dashboard->assertDontSee('data-tree-item="config-cambio"', false);
        $dashboard->assertDontSee('data-tree-item="config-seguranca"', false);

        $response = $this->actingAs($user)->get(route('settings.index', ['tab' => 'security']));

        $response->assertOk();

        // Configurações é uma página só, com item ativo próprio.
        $response->assertSeeInOrder([
            'data-tree-item="sistema"',
            '<span>Configurações</span>',
            '<span>Central de cron</span>',
            '<span>Modelos de e-mail</span>',
            '<span>Usuários e acessos</span>',
        ], false);
        $response->assertSee('data-active-path="true"', false);
        $response->assertSee('aria-current="page"', false);

        // A própria página continua com as abas (pílulas) internas.
        $response->assertSee(route('settings.index', ['tab' => 'security']), false);
        $response->assertSee('data-settings-tabs', false);
        $response->assertSee('data-settings-tab="security"', false);
        $response->assertSee('id="settings-panel"', false);
        $response->assertSee('kdk-tabs', false);

        // A troca de aba é registrada no próprio HTML, sem depender do bundle:
        // é isso que impede o clique de recarregar a página e mexer no menu.
        $response->assertSee("document.querySelector('[data-settings-tabs]')", false);
        $response->assertSee('panel.innerHTML = next.innerHTML;', false);
        $response->assertSee('pushState', false);
    }

    public function test_financial_manager_sees_settings_without_developer_tabs_or_links(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('<span>Configurações</span>', false);
        $response->assertDontSee('data-tree-item="configuracoes"', false);
        $response->assertSee('Câmbio e tributos');
        $response->assertSee('Conversão e cartão');
        $response->assertDontSee(route('settings.index', ['tab' => 'ovh']), false);
        $response->assertDontSee(route('crons.index'), false);
        $response->assertDontSee(route('users.index'), false);
    }
}
