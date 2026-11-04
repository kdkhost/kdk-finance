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
use App\Models\CronJob;
use App\Models\CronJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CronCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:30', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $role = User::ROLE_DEVELOPER): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function cron(array $attributes = []): CronJob
    {
        return CronJob::query()->create(array_merge([
            'name' => 'Limpeza de cache',
            'type' => CronJob::TYPE_ARTISAN,
            'command' => 'inspire',
            'expression' => '* * * * *',
            'timezone' => 'America/Sao_Paulo',
            'description' => 'Cron de teste',
            'enabled' => true,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Backup diário',
            'type' => CronJob::TYPE_ARTISAN,
            'command' => 'inspire',
            'expression' => '0 3 * * *',
            'timezone' => 'America/Sao_Paulo',
            'description' => 'Executa o comando inspire',
            'enabled' => '1',
        ], $overrides);
    }

    public function test_only_developer_admin_can_access_the_cron_center(): void
    {
        $this->actingAs($this->user(User::ROLE_FINANCIAL))->get('/crons')->assertForbidden();
        $this->actingAs($this->user(User::ROLE_VIEWER))->get('/crons')->assertForbidden();

        $response = $this->actingAs($this->user())->get('/crons');

        $response->assertOk();
        $response->assertSee('Central de cron');
        $response->assertSee('* * * * * cd');
        $response->assertSee('schedule:run');
    }

    public function test_developer_can_register_a_cron_with_valid_expression(): void
    {
        $response = $this->actingAs($this->user())->post('/crons', $this->payload());

        $cron = CronJob::query()->where('name', 'Backup diário')->firstOrFail();

        $response->assertRedirect(route('crons.show', $cron));
        $this->assertSame('0 3 * * *', $cron->expression);
        $this->assertTrue($cron->enabled);
        $this->assertNotNull($cron->created_by);
        $this->assertSame('Diariamente às 03:00', $cron->expressionLabel());
    }

    public function test_invalid_expression_or_timezone_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->post('/crons', $this->payload(['expression' => 'todo dia as 3']))
            ->assertSessionHasErrors('expression');

        $this->actingAs($this->user())
            ->post('/crons', $this->payload(['timezone' => 'Marte/Olympus']))
            ->assertSessionHasErrors('timezone');

        $this->assertDatabaseCount('cron_jobs', 0);
    }

    public function test_toggle_disables_and_enables_the_job(): void
    {
        $cron = $this->cron();

        $this->actingAs($this->user())
            ->post(route('crons.toggle', $cron))
            ->assertRedirect();

        $this->assertFalse($cron->fresh()->enabled);

        $this->actingAs($this->user())->post(route('crons.toggle', $cron));

        $this->assertTrue($cron->fresh()->enabled);
    }

    public function test_manual_run_records_history_and_output(): void
    {
        $cron = $this->cron();

        $this->actingAs($this->user())
            ->post(route('crons.run', $cron))
            ->assertRedirect()
            ->assertSessionHas('success');

        $cron->refresh();

        $this->assertSame(0, $cron->last_exit_code);
        $this->assertSame(1, $cron->run_count);
        $this->assertSame(0, $cron->failure_count);
        $this->assertNotNull($cron->last_run_at);
        $this->assertNotEmpty($cron->last_output);

        $run = CronJobRun::query()->firstOrFail();

        $this->assertSame('manual', $run->trigger);
        $this->assertSame(0, $run->exit_code);
        $this->assertTrue($run->isSuccessful());
        $this->assertNotEmpty($run->output);
    }

    public function test_scheduler_command_runs_due_jobs_and_writes_heartbeat(): void
    {
        $cron = $this->cron();

        $this->artisan('cron:run-due')->assertSuccessful();

        $this->assertNotNull(AppSetting::get('cron_runner_heartbeat'));

        $run = CronJobRun::query()->firstOrFail();

        $this->assertSame('schedule', $run->trigger);
        $this->assertSame(0, $run->exit_code);
        $this->assertSame(1, $cron->fresh()->run_count);
    }

    public function test_scheduler_skips_a_job_already_executed_in_the_same_minute(): void
    {
        $cron = $this->cron();

        $this->artisan('cron:run-due')->assertSuccessful();
        $this->artisan('cron:run-due')->assertSuccessful();

        $this->assertSame(1, $cron->fresh()->run_count);
        $this->assertSame(1, CronJobRun::query()->count());
    }

    public function test_disabled_job_is_not_executed_by_the_scheduler(): void
    {
        $cron = $this->cron(['enabled' => false]);

        $this->artisan('cron:run-due')->assertSuccessful();

        $this->assertSame(0, $cron->fresh()->run_count);
        $this->assertDatabaseCount('cron_job_runs', 0);
    }
}
