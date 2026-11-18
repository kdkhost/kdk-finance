<?php

declare(strict_types=1);

$token = 'dd415c4c85df1cc2950b7744';

if (! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
set_time_limit(120);

$base = dirname(__DIR__);

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$out = [];

$out['now'] = now()->toIso8601String();
$out['version'] = Illuminate\Foundation\Application::VERSION;
$out['heartbeat'] = App\Models\AppSetting::get('cron_runner_heartbeat');

$out['crons'] = App\Models\CronJob::query()->orderBy('id')->get([
    'id', 'name', 'type', 'command', 'expression', 'timezone', 'enabled',
    'last_run_at', 'last_finished_at', 'last_exit_code', 'run_count', 'failure_count',
])->map(fn ($job) => $job->toArray())->all();

$out['runs'] = App\Models\CronJobRun::query()
    ->orderByDesc('started_at')
    ->limit(10)
    ->get(['cron_job_id', 'trigger', 'started_at', 'exit_code', 'duration_ms'])
    ->map(fn ($run) => $run->toArray())
    ->all();

try {
    Illuminate\Support\Facades\Artisan::call('schedule:list');
    $out['schedule'] = Illuminate\Support\Facades\Artisan::output();
} catch (Throwable $e) {
    $out['schedule'] = 'erro: '.$e->getMessage();
}

$out['mail'] = [
    'enabled' => App\Models\AppSetting::get('mail_enabled'),
    'host' => App\Models\AppSetting::get('mail_host'),
    'from' => App\Models\AppSetting::get('mail_from_address'),
    'last_test_at' => App\Models\AppSetting::get('mail_last_test_at'),
    'last_test_status' => App\Models\AppSetting::get('mail_last_test_status'),
];

try {
    $out['tables'] = array_map(
        static fn (object $row): array => array_values((array) $row),
        Illuminate\Support\Facades\DB::select('SHOW TABLES')
    );
} catch (Throwable $e) {
    $out['tables'] = 'erro: '.$e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
