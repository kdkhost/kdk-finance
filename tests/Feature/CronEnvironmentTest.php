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
use App\Models\User;
use App\Services\CronRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_DEVELOPER,
            'is_active' => true,
        ]);
    }

    public function test_cron_page_shows_the_php_environment(): void
    {
        $runner = app(CronRunnerService::class);

        $this->actingAs($this->developer())
            ->get(route('crons.index'))
            ->assertOk()
            ->assertSee('Ambiente de execução')
            ->assertSee('Binário do PHP')
            ->assertSee($runner->phpBinary());
    }

    public function test_a_valid_php_binary_can_be_saved_and_is_used_by_the_command(): void
    {
        $developer = $this->developer();
        $binary = PHP_BINARY;

        $this->actingAs($developer)
            ->post(route('crons.environment'), ['php_binary' => $binary])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($binary, AppSetting::get('cron_php_binary'));

        $job = CronJob::query()->create([
            'name' => 'Teste',
            'type' => CronJob::TYPE_ARTISAN,
            'command' => 'inspire',
            'expression' => '* * * * *',
            'timezone' => 'America/Sao_Paulo',
            'enabled' => true,
        ]);

        $command = app(CronRunnerService::class)->buildCommand($job);

        $this->assertStringStartsWith(escapeshellarg($binary), $command);
        $this->assertStringContainsString(base_path('artisan'), $command);
        $this->assertStringContainsString('inspire', $command);
    }

    public function test_a_non_executable_path_is_rejected(): void
    {
        $this->actingAs($this->developer())
            ->post(route('crons.environment'), [
                'php_binary' => base_path('nao-existe/php'),
            ])
            ->assertSessionHasErrors('php_binary');

        $this->assertNull(AppSetting::get('cron_php_binary'));
    }

    public function test_an_empty_value_restores_automatic_detection(): void
    {
        AppSetting::set('cron_php_binary', PHP_BINARY, 'cron');

        $this->actingAs($this->developer())
            ->post(route('crons.environment'), ['php_binary' => ''])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('', AppSetting::get('cron_php_binary'));
        $this->assertNotSame('', app(CronRunnerService::class)->phpBinary());
    }

    public function test_version_of_reports_the_running_php_and_rejects_unknown_binaries(): void
    {
        $runner = app(CronRunnerService::class);

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', (string) $runner->versionOf(PHP_BINARY));
        $this->assertNull($runner->versionOf(base_path('nao-existe/php')));
    }
}
