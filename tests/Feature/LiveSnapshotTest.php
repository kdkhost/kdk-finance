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

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_FINANCIAL,
            'is_active' => true,
        ]);
    }

    private function fakeRates(): void
    {
        Http::fake([
            'economia.awesomeapi.com.br/last/*' => Http::response([
                'USDBRL' => ['bid' => '5.4736', 'pctChange' => '0.12'],
                'EURBRL' => ['bid' => '6.1618', 'pctChange' => '-0.08'],
            ]),
            'economia.awesomeapi.com.br/json/last/*' => Http::response([
                'EURUSD' => ['bid' => '1.125840', 'pctChange' => '0.40'],
            ]),
        ]);
    }

    public function test_timezone_padrao_e_sao_paulo(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', now()->getTimezone()->getName());
    }

    public function test_instantaneo_exige_autenticacao(): void
    {
        $this->getJson(route('live.snapshot'))->assertUnauthorized();
    }

    public function test_instantaneo_devolve_cotacoes_totais_e_faturas_solicitadas(): void
    {
        $this->fakeRates();

        $user = $this->user();
        $invoice = Invoice::query()->create([
            'external_reference' => 'LIVE-1',
            'description' => 'Servidor OVH',
            'currency' => 'EUR',
            'amount_original' => 100,
            'exchange_rate' => 6.1618,
            'total_brl' => 616.18,
            'status' => Invoice::STATUS_PENDING,
            'due_date' => now()->addDays(5),
            'rate_locked' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('live.snapshot', ['invoices' => $invoice->id]))
            ->assertOk()
            ->assertJsonPath('timezone', 'America/Sao_Paulo')
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('totals.pending_total', 616.18)
            ->assertJsonPath('invoices.'.$invoice->id, 616.18);

        $this->assertNotEmpty($response->json('server_time'));
        $this->assertNotEmpty($response->json('rates.USD.rate'));
        $this->assertNotEmpty($response->json('eur_usd.rate'));
    }

    public function test_painel_exibe_relogio_e_endereco_de_atualizacao(): void
    {
        $this->fakeRates();

        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-live-url', false)
            ->assertSee('data-live-clock-time', false)
            ->assertSee('São Paulo', false);
    }
}
