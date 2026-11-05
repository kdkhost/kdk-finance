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

    public function test_developer_opens_three_levels_and_marks_the_current_tab(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.index', ['tab' => 'security']));

        $response->assertOk();
        $response->assertSee('sidebar-level-3', false);
        $response->assertSee('aria-controls="sidebar-menu-configuracoes"', false);
        $response->assertSee('data-tree-item="sistema"', false);
        $response->assertSee('data-active-path="true"', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('Câmbio e tributos');
        $response->assertSee('Segurança');
        $response->assertSee('Central de cron');
        $response->assertSee('Usuários e acessos');
    }

    public function test_financial_manager_sees_settings_without_developer_links(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('data-tree-item="configuracoes"', false);
        $response->assertSee('Câmbio e tributos');
        $response->assertSee('Conversão e cartão');
        $response->assertDontSee(route('settings.index', ['tab' => 'ovh']), false);
        $response->assertDontSee(route('crons.index'), false);
        $response->assertDontSee(route('users.index'), false);
    }
}
