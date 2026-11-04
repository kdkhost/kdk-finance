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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);
    }

    public function test_restore_creates_every_system_cron_once(): void
    {
        $this->actingAs($this->user())
            ->post(route('crons.restore'))
            ->assertRedirect(route('crons.index'))
            ->assertSessionHas('success');

        $this->assertSame(count(CronJob::systemDefaults()), CronJob::query()->count());

        $commands = CronJob::query()->pluck('command')->all();

        foreach (['finance:refresh-rates', 'finance:sync-invoices', 'licenses:sync-prices', 'licenses:sync-panel', 'finance:notify-alerts'] as $command) {
            $this->assertContains($command, $commands);
        }
    }

    public function test_restore_is_idempotent_and_keeps_existing_jobs(): void
    {
        $this->actingAs($this->user())->post(route('crons.restore'));
        $this->actingAs($this->user())->post(route('crons.restore'));

        $this->assertSame(count(CronJob::systemDefaults()), CronJob::query()->count());
    }

    public function test_every_system_cron_is_enabled_and_scheduled(): void
    {
        $this->actingAs($this->user())->post(route('crons.restore'));

        foreach (CronJob::query()->get() as $cron) {
            $this->assertTrue($cron->enabled, $cron->command.' deveria estar ativo');
            $this->assertTrue(CronJob::isValidExpression($cron->expression), $cron->expression);
            $this->assertNotNull($cron->nextRun(), $cron->command.' sem próxima execução calculada');
        }
    }

    public function test_cron_index_offers_restore_and_lists_the_system_jobs(): void
    {
        $this->actingAs($this->user())->post(route('crons.restore'));

        $response = $this->actingAs($this->user())->get(route('crons.index'));

        $response->assertOk();
        $response->assertSee('Restaurar padrões');
        $response->assertSee('Atualizar cotações de câmbio');
        $response->assertSee('Alertas de vencimento');
    }

    public function test_schedule_only_dispatches_the_panel_runner(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());

        $commands = $events->map(fn ($event): string => (string) $event->command)->implode(' ');

        $this->assertStringContainsString('cron:run-due', $commands);
        $this->assertStringNotContainsString('finance:sync-invoices', $commands);
        $this->assertStringNotContainsString('licenses:sync-prices', $commands);
        $this->assertStringNotContainsString('licenses:sync-panel', $commands);
    }
}
