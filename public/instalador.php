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
 *
 * Instalador para hospedagem compartilhada (cPanel).
 *
 * Não depende do Laravel estar configurado: verifica requisitos, testa o banco,
 * grava o .env, roda as migrações e cria o administrador e a conta de leitura.
 * Depois de instalar, o próprio arquivo é renomeado e a instalação é travada.
 */

declare(strict_types=1);

use App\Support\Install\EnvWriter;

$basePath = dirname(__DIR__);
$lockFile = $basePath.'/storage/app/installed.lock';
$keyFile = $basePath.'/storage/app/instalar.senha';
$attemptFile = $basePath.'/storage/app/install-attempts.json';
$logFile = $basePath.'/storage/logs/instalador.log';
$envFile = $basePath.'/.env';
$autoloadFile = $basePath.'/vendor/autoload.php';

if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

$installerVersion = '1.8.3';

if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', (string) @file_get_contents($basePath.'/CHANGELOG.md'), $versionMatch) === 1) {
    $installerVersion = $versionMatch[1];
}

$isHttps = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, private, max-age=0');

session_name('kdk_instalador');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (! isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = (string) $_SESSION['csrf'];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function readEnvKey(string $path, string $key): ?string
{
    if (! is_file($path)) {
        return null;
    }

    $contents = (string) file_get_contents($path);

    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
        return null;
    }

    return trim($matches[1], " \t\n\r\0\x0B\"'");
}

/**
 * @return list<string>
 */
function strongPasswordErrors(string $password, string $label): array
{
    $errors = [];

    if (strlen($password) < 10) {
        $errors[] = $label.' precisa ter pelo menos 10 caracteres.';
    }

    if (preg_match('/[a-z]/', $password) !== 1 || preg_match('/[A-Z]/', $password) !== 1) {
        $errors[] = $label.' precisa misturar letras maiúsculas e minúsculas.';
    }

    if (preg_match('/\d/', $password) !== 1) {
        $errors[] = $label.' precisa conter ao menos um número.';
    }

    return $errors;
}

function requirements(string $basePath): array
{
    return [
        ['PHP 8.2 ou superior', version_compare(PHP_VERSION, '8.2.0', '>='), 'Encontrado: PHP '.PHP_VERSION],
        ['Extensão openssl', extension_loaded('openssl'), 'Necessária para criptografar credenciais'],
        ['Extensão pdo', extension_loaded('pdo'), 'Necessária para o banco de dados'],
        ['Driver pdo_mysql ou pdo_sqlite', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), 'Instale o driver do banco escolhido'],
        ['Extensão mbstring', extension_loaded('mbstring'), 'Textos em português'],
        ['Extensão fileinfo', extension_loaded('fileinfo'), 'Uploads e arquivos'],
        ['Extensão curl', extension_loaded('curl'), 'Integrações OVH e cotações'],
        ['Extensão tokenizer', extension_loaded('tokenizer'), 'Necessária para o Laravel'],
        ['Extensão xml', extension_loaded('xml'), 'Necessária para o Laravel'],
        ['Extensão ctype', extension_loaded('ctype'), 'Necessária para o Laravel'],
        ['Extensão json', extension_loaded('json'), 'Necessária para o Laravel'],
        ['Extensão dom', extension_loaded('dom'), 'Necessária para o Laravel'],
        ['Extensão filter', extension_loaded('filter'), 'Necessária para o Laravel'],
        ['Extensão hash', extension_loaded('hash'), 'Necessária para o Laravel'],
        ['Extensão session', extension_loaded('session'), 'Necessária para o login'],
        ['Pasta vendor/', is_file($basePath.'/vendor/autoload.php'), 'Envie o pacote completo, com vendor'],
        ['Build do front-end', is_file($basePath.'/public/build/manifest.json'), 'Envie o pacote completo, com public/build'],
        ['Gravação na raiz (para o .env)', is_writable($basePath), 'Ajuste a permissão da pasta'],
        ['Gravação em storage/', is_writable($basePath.'/storage'), 'Ajuste para 755 ou 775'],
        ['Gravação em bootstrap/cache/', is_writable($basePath.'/bootstrap/cache'), 'Ajuste para 755 ou 775'],
        ['Gravação em database/', is_writable($basePath.'/database'), 'Necessária para SQLite'],
    ];
}

$requirements = requirements($basePath);
$requirementsOk = ! in_array(false, array_column($requirements, 1), true);

$alreadyInstalled = is_file($lockFile)
    || (readEnvKey($envFile, 'APP_KEY') ?? '') !== '';

$keyPresent = is_file($keyFile);
$keyExpected = $keyPresent ? trim((string) file_get_contents($keyFile)) : '';

$lockedOut = false;
$attempts = [];

if (is_file($attemptFile)) {
    $decoded = json_decode((string) file_get_contents($attemptFile), true);
    $attempts = is_array($decoded) ? array_values(array_filter($decoded, 'is_int')) : [];
    $attempts = array_values(array_filter($attempts, static fn (int $time): bool => $time > time() - 3600));

    if (count($attempts) >= 10) {
        $lockedOut = true;
    }
}

$errors = [];
$done = null;
$form = static function (string $key, string $default = '') use ($isHttps, $basePath): string {
    if (isset($_POST[$key]) && is_string($_POST[$key])) {
        return trim($_POST[$key]);
    }

    return $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $alreadyInstalled && $requirementsOk && $keyPresent && ! $lockedOut) {
    if (! hash_equals($csrf, (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'Sessão expirada. Recarregue a página e tente novamente.';
    }

    if (post('website') !== '') {
        $errors[] = 'Não foi possível validar o formulário.';
    }

    if (! hash_equals($keyExpected, post('install_key'))) {
        $errors[] = 'Senha de instalação incorreta. Confira o conteúdo de storage/app/instalar.senha.';

        // Só tentativas com senha errada contam para o bloqueio.
        $attempts[] = time();
        file_put_contents($attemptFile, json_encode($attempts), LOCK_EX);
    }

    // A pasta public/ nunca compõe a URL do sistema: se o endereço informado
    // terminar em /public, ele é removido antes de gravar o APP_URL.
    $appUrl = preg_replace('#/public/?$#i', '', rtrim(post('app_url'), '/'));
    $appUrl = is_string($appUrl) ? rtrim($appUrl, '/') : '';
    $appScheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
    $appHost = (string) parse_url($appUrl, PHP_URL_HOST);

    if (! filter_var($appUrl, FILTER_VALIDATE_URL) || ! in_array($appScheme, ['http', 'https'], true) || $appHost === '') {
        $errors[] = 'Informe o endereço completo do sistema, por exemplo https://financeiro.seudominio.com.';
    }

    $dbDriver = post('db_driver', 'mysql');
    $dbHost = post('db_host', 'localhost');
    $dbPort = post('db_port', '3306');
    $dbName = post('db_name');
    $dbUser = post('db_user');
    $dbPassword = (string) ($_POST['db_password'] ?? '');

    if (! in_array($dbDriver, ['mysql', 'sqlite'], true)) {
        $errors[] = 'Banco de dados inválido.';
    }

    if ($dbDriver === 'mysql') {
        if ($dbHost === '' || preg_match('/^[a-zA-Z0-9._:\-]+$/', $dbHost) !== 1) {
            $errors[] = 'Informe o servidor do banco de dados (normalmente localhost).';
        }

        if (preg_match('/^\d{1,5}$/', $dbPort) !== 1) {
            $errors[] = 'Porta do banco inválida.';
        }

        if ($dbName === '' || $dbUser === '') {
            $errors[] = 'Informe o nome do banco e o usuário criados no cPanel.';
        }
    }

    $adminName = post('admin_name');
    $adminEmail = strtolower(post('admin_email'));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirmation = (string) ($_POST['admin_password_confirmation'] ?? '');

    if (strlen($adminName) < 3) {
        $errors[] = 'Informe o nome do administrador.';
    }

    if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail do administrador inválido.';
    }

    if ($adminPassword !== $adminPasswordConfirmation) {
        $errors[] = 'A confirmação da senha do administrador não confere.';
    }

    $errors = array_merge($errors, strongPasswordErrors($adminPassword, 'A senha do administrador'));

    $readerEnabled = isset($_POST['reader_enabled']);
    $readerName = post('reader_name', 'Acesso somente leitura');
    $readerEmail = strtolower(post('reader_email'));
    $readerPassword = (string) ($_POST['reader_password'] ?? '');
    $readerPasswordConfirmation = (string) ($_POST['reader_password_confirmation'] ?? '');

    if ($readerEnabled) {
        if (strlen($readerName) < 3) {
            $errors[] = 'Informe o nome do usuário somente leitura.';
        }

        if (! filter_var($readerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail do usuário somente leitura inválido.';
        }

        if (strcasecmp($readerEmail, $adminEmail) === 0) {
            $errors[] = 'O usuário somente leitura precisa ter um e-mail diferente do administrador.';
        }

        if ($readerPassword !== $readerPasswordConfirmation) {
            $errors[] = 'A confirmação da senha do usuário somente leitura não confere.';
        }

        $errors = array_merge($errors, strongPasswordErrors($readerPassword, 'A senha do usuário somente leitura'));
    }

    $mailEnabled = isset($_POST['mail_enabled']);
    $mailHost = post('mail_host');
    $mailPort = post('mail_port', '587');
    $mailUser = post('mail_username');
    $mailPassword = (string) ($_POST['mail_password'] ?? '');
    $mailEncryption = post('mail_encryption', 'tls');
    $mailFrom = strtolower(post('mail_from', $adminEmail));

    if ($mailEnabled) {
        if ($mailHost === '' || preg_match('/^[a-zA-Z0-9._\-]+$/', $mailHost) !== 1) {
            $errors[] = 'Informe o servidor SMTP (por exemplo mail.seudominio.com).';
        }

        if (preg_match('/^\d{1,5}$/', $mailPort) !== 1) {
            $errors[] = 'Porta SMTP inválida.';
        }

        if (! in_array($mailEncryption, ['tls', 'ssl', 'none'], true)) {
            $errors[] = 'Criptografia SMTP inválida.';
        }

        if (! filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail de envio inválido.';
        }
    }

    // ---------------------------------------------------------------------
    // Testa o banco antes de gravar qualquer configuração.
    // ---------------------------------------------------------------------
    if ($errors === []) {
        $sqlitePath = $basePath.'/database/database.sqlite';

        try {
            if ($dbDriver === 'sqlite') {
                if (! is_writable(dirname($sqlitePath))) {
                    throw new RuntimeException('A pasta database não permite gravação.');
                }

                if (! is_file($sqlitePath)) {
                    touch($sqlitePath);
                }

                $pdo = new PDO('sqlite:'.$sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $existing = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchColumn();
                $dbName = $sqlitePath;
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
                $pdo = new PDO($dsn, $dbUser, $dbPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 10,
                ]);
                $existing = $pdo->query("SHOW TABLES LIKE 'migrations'")->fetchColumn();
            }

            if ($existing !== false && $existing !== null && $existing !== '') {
                throw new RuntimeException('O banco informado já possui uma instalação (tabela migrations). Use um banco vazio.');
            }
        } catch (Throwable $exception) {
            $errors[] = 'Não foi possível conectar ao banco: '.$exception->getMessage();
        }
    }

    // ---------------------------------------------------------------------
    // Grava o .env e conclui a instalação.
    // ---------------------------------------------------------------------
    if ($errors === []) {
        try {
            $env = [
                'APP_NAME' => 'KDK Finance',
                'APP_ENV' => 'production',
                'APP_KEY' => EnvWriter::appKey(),
                'APP_DEBUG' => false,
                'APP_URL' => $appUrl,
                'APP_TIMEZONE' => 'America/Sao_Paulo',
                'APP_LOCALE' => 'pt_BR',
                'APP_FALLBACK_LOCALE' => 'pt_BR',
                'APP_FAKER_LOCALE' => 'pt_BR',
                'APP_MAINTENANCE_DRIVER' => 'file',
                'BCRYPT_ROUNDS' => '12',
                'LOG_CHANNEL' => 'stack',
                'LOG_STACK' => 'single',
                'LOG_LEVEL' => 'warning',
                'DB_CONNECTION' => $dbDriver,
                'DB_HOST' => $dbDriver === 'mysql' ? $dbHost : null,
                'DB_PORT' => $dbDriver === 'mysql' ? $dbPort : null,
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbDriver === 'mysql' ? $dbUser : null,
                'DB_PASSWORD' => $dbDriver === 'mysql' ? $dbPassword : null,
                'DB_CHARSET' => 'utf8mb4',
                'DB_COLLATION' => 'utf8mb4_unicode_ci',
                'SESSION_DRIVER' => 'database',
                'SESSION_LIFETIME' => '120',
                'SESSION_ENCRYPT' => true,
                'SESSION_SECURE_COOKIE' => $isHttps,
                'SESSION_HTTP_ONLY' => true,
                'SESSION_SAME_SITE' => 'lax',
                'SESSION_COOKIE' => 'kdk_finance_session',
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
                'BROADCAST_CONNECTION' => 'log',
                'FILESYSTEM_DISK' => 'local',
                // O SMTP real é gravado em app_settings (senha criptografada).
                // O .env fica apenas com um transporte seguro de fallback.
                'MAIL_MAILER' => 'log',
                'MAIL_FROM_ADDRESS' => $mailFrom,
                'MAIL_FROM_NAME' => 'KDK Finance',
                'FINANCE_VERIFY_SSL' => true,
                'VITE_APP_NAME' => 'KDK Finance',
            ];

            EnvWriter::write($envFile, $env);

            // Caches de uma tentativa anterior não podem prevalecer sobre o .env novo.
            foreach (glob($basePath.'/bootstrap/cache/*.php') ?: [] as $cacheFile) {
                if (basename($cacheFile) !== 'packages.php') {
                    @unlink($cacheFile);
                }
            }

            if (! is_file($autoloadFile)) {
                throw new RuntimeException('A pasta vendor não está presente.');
            }

            $app = require $basePath.'/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            $status = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true]);
            $migrationOutput = $kernel->output();

            if ($status !== 0) {
                throw new RuntimeException('As migrações falharam. '.mb_substr(trim($migrationOutput), -400));
            }

            $status = $kernel->call('db:seed', [
                '--class' => 'Database\\Seeders\\AppSettingsSeeder',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($status !== 0) {
                throw new RuntimeException('Não foi possível gravar as configurações iniciais.');
            }

            // Segurança operacional e SMTP ficam no banco (painel admin).
            $installHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
            $installHosts = array_values(array_unique(array_filter([
                $installHost,
                $installHost !== '' && str_starts_with($installHost, 'www.') ? substr($installHost, 4) : ($installHost !== '' ? 'www.'.$installHost : null),
            ])));

            App\Models\AppSetting::set('security_force_https', $isHttps ? '1' : '0', 'security');
            App\Models\AppSetting::set('security_trust_hosts', '1', 'security');
            App\Models\AppSetting::set('security_allowed_hosts', implode(', ', $installHosts), 'security');
            App\Models\AppSetting::set('security_session_encrypt', '1', 'security');
            App\Models\AppSetting::set('security_session_secure', '1', 'security');
            App\Models\AppSetting::set('security_session_lifetime', '120', 'security');

            App\Models\AppSetting::set('mail_enabled', $mailEnabled ? '1' : '0', 'mail');
            App\Models\AppSetting::set('mail_host', $mailEnabled ? strtolower($mailHost) : '', 'mail');
            App\Models\AppSetting::set('mail_port', $mailEnabled ? $mailPort : '587', 'mail');
            App\Models\AppSetting::set('mail_encryption', $mailEnabled ? $mailEncryption : 'tls', 'mail');
            App\Models\AppSetting::set('mail_auth', $mailEnabled && $mailUser !== '' ? '1' : '0', 'mail');
            App\Models\AppSetting::set('mail_username', $mailEnabled ? $mailUser : '', 'mail');
            App\Models\AppSetting::set('mail_timeout', '15', 'mail');
            App\Models\AppSetting::set('mail_from_address', $mailFrom, 'mail');
            App\Models\AppSetting::set('mail_from_name', 'KDK Finance', 'mail');

            if ($mailEnabled && $mailPassword !== '') {
                App\Models\AppSetting::set('mail_password', $mailPassword, 'mail', true);
            }

            $admin = App\Models\User::query()->create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
                'role' => App\Models\User::ROLE_DEVELOPER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $reader = null;

            if ($readerEnabled) {
                $reader = App\Models\User::query()->create([
                    'name' => $readerName,
                    'email' => $readerEmail,
                    'password' => $readerPassword,
                    'role' => App\Models\User::ROLE_VIEWER,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            $lockContents = json_encode([
                'installed_at' => gmdate('c'),
                'version' => $installerVersion,
                'app_url' => $appUrl,
                'database' => $dbDriver,
                'admin' => $adminEmail,
                'reader' => $reader?->email,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (file_put_contents($lockFile, $lockContents, LOCK_EX) === false) {
                throw new RuntimeException('Não foi possível gravar a trava de instalação.');
            }

            @chmod($lockFile, 0640);
            @unlink($attemptFile);
            @unlink($keyFile);

            $renamed = @rename(__FILE__, $basePath.'/public/instalador.php.instalado');

            $done = [
                'app_url' => $appUrl,
                'admin' => $adminEmail,
                'reader' => $reader?->email,
                'renamed' => $renamed,
                'version' => $installerVersion,
                'db' => $dbDriver === 'sqlite' ? 'SQLite' : 'MySQL/MariaDB',
            ];
        } catch (Throwable $exception) {
            @file_put_contents(
                $logFile,
                '['.gmdate('c').'] '.$exception->getMessage().PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            $errors[] = 'A instalação não foi concluída: '.$exception->getMessage();
        }
    }
}

function pageStart(string $title, string $version): void
{
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Instalador KDK Finance</title>
    <style>
        :root {
            --ink: #0f1f2e;
            --muted: #5b6b7b;
            --line: #dfe6ee;
            --bg: #f4f7fb;
            --card: #ffffff;
            --teal: #0f766e;
            --teal-dark: #0a2540;
            --danger: #b42318;
            --danger-bg: #fef3f2;
            --success: #067647;
            --success-bg: #ecfdf3;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
            line-height: 1.55; -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 900px; margin: 0 auto; padding: 32px 20px 64px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .brand-mark {
            width: 46px; height: 46px; border-radius: 12px;
            background: linear-gradient(145deg, #16a394, #0a2540);
            color: #fff; display: grid; place-items: center;
            font-weight: 800; letter-spacing: -.5px;
        }
        .brand strong { display: block; font-size: 1.05rem; }
        .brand span { color: var(--muted); font-size: .82rem; }
        .card {
            background: var(--card); border: 1px solid var(--line); border-radius: 16px;
            padding: 26px; box-shadow: 0 1px 2px rgba(15, 31, 46, .05);
        }
        h1 { font-size: 1.55rem; margin: 0 0 4px; letter-spacing: -.02em; }
        h2 { font-size: 1.05rem; margin: 26px 0 10px; }
        p { margin: 0 0 12px; }
        .muted { color: var(--muted); font-size: .9rem; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1.4fr .6fr 1fr; gap: 14px; }
        label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: 5px; }
        input, select {
            width: 100%; padding: 10px 12px; border: 1px solid var(--line);
            border-radius: 10px; font: inherit; background: #fff; color: var(--ink);
        }
        input:focus, select:focus { outline: 2px solid #99e5dc; border-color: var(--teal); }
        .field { margin-bottom: 14px; }
        .hint { color: var(--muted); font-size: .78rem; margin-top: 4px; }
        .check { display: flex; gap: 9px; align-items: flex-start; font-weight: 500; font-size: .9rem; }
        .check input { width: 17px; height: 17px; margin-top: 2px; }
        .btn {
            display: inline-block; border: 0; border-radius: 10px; padding: 12px 22px;
            font: inherit; font-weight: 700; color: #fff; cursor: pointer;
            background: linear-gradient(180deg, #12a594, #0b7f74);
        }
        .btn:hover { filter: brightness(.96); }
        .actions { margin-top: 22px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .alert { border-radius: 10px; padding: 12px 14px; margin: 0 0 16px; font-size: .9rem; }
        .alert-error { background: var(--danger-bg); color: var(--danger); border: 1px solid #fecdca; }
        .alert-ok { background: var(--success-bg); color: var(--success); border: 1px solid #abefc6; }
        .alert-warn { background: #fffaeb; color: #b54708; border: 1px solid #fedf89; }
        ul { margin: 8px 0 0 20px; padding: 0; }
        li { margin: 4px 0; }
        .req { list-style: none; margin: 0; padding: 0; }
        .req li { display: flex; justify-content: space-between; gap: 14px; padding: 8px 0; border-bottom: 1px solid var(--line); font-size: .9rem; }
        .req li:last-child { border-bottom: 0; }
        .req small { display: block; color: var(--muted); }
        .pill { border-radius: 999px; padding: 2px 10px; font-size: .75rem; font-weight: 700; height: fit-content; }
        .pill-ok { background: var(--success-bg); color: var(--success); }
        .pill-bad { background: var(--danger-bg); color: var(--danger); }
        code { background: #eef3f8; border-radius: 6px; padding: 2px 6px; font-size: .86rem; }
        .mono { font-family: ui-monospace, Consolas, "Courier New", monospace; }
        footer { color: var(--muted); font-size: .78rem; text-align: center; margin-top: 22px; }
        .notice { border-left: 3px solid var(--teal); background: #f0fdfa; padding: 12px 14px; border-radius: 8px; margin: 14px 0; font-size: .9rem; }
        @media (max-width: 720px) {
            .grid, .grid-3 { grid-template-columns: 1fr; }
            .card { padding: 20px; }
        }
        @media print { body { background: #fff; } .card { border: 0; box-shadow: none; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <div class="brand-mark">KDK</div>
        <div>
            <strong>KDK Finance</strong>
            <span>Instalador para hospedagem compartilhada · v<?= e($version) ?></span>
        </div>
    </div>
    <?php
}

function pageEnd(): void
{
    ?>
    <footer>
        KDKHost Soluções · CNPJ 33.986.975/0001-90 · contato@kdkhost.com.br<br>
        Instalador seguro — nenhuma senha é exibida ou gravada fora do servidor.
    </footer>
</div>
</body>
</html>
    <?php
}
?>
<?php pageStart('Instalação', $installerVersion); ?>

<?php if ($alreadyInstalled): ?>
    <div class="card">
        <h1>Instalação já concluída</h1>
        <p>Este sistema já está configurado. Por segurança, o instalador não permite reinstalar por cima dos dados existentes.</p>
        <div class="notice">
            Para reinstalar do zero, faça um backup, remova o arquivo <code>.env</code> e a trava
            <code>storage/app/installed.lock</code> pelo Gerenciador de Arquivos do cPanel.
        </div>
        <div class="actions">
            <a class="btn" href="<?= e(rtrim((string) readEnvKey($envFile, 'APP_URL'), '/')) ?>/login">Ir para o login</a>
        </div>
    </div>
<?php elseif (! $keyPresent): ?>
    <div class="card">
        <h1>Instalador bloqueado</h1>
        <p>Falta a senha de instalação. Ela impede que qualquer visitante configure o sistema antes de você.</p>
        <h2>Como liberar</h2>
        <ul>
            <li>Abra o <strong>Gerenciador de Arquivos</strong> do cPanel.</li>
            <li>Entre em <code>storage/app/</code>.</li>
            <li>Crie o arquivo <code>instalar.senha</code> com uma senha longa e aleatória (mínimo 16 caracteres).</li>
            <li>Volte a esta página e informe a mesma senha no formulário.</li>
        </ul>
        <p class="hint">Se você recebeu o pacote pronto, o arquivo <code>instalar.senha</code> já vem dentro dele, com uma senha gerada.</p>
    </div>
<?php elseif (! $requirementsOk): ?>
    <div class="card">
        <h1>Requisitos pendentes</h1>
        <p class="muted">Ajuste os itens abaixo no cPanel (seletor de PHP, extensões e permissões) e recarregue esta página.</p>
        <ul class="req">
            <?php foreach ($requirements as [$label, $ok, $hint]): ?>
                <li>
                    <span><?= e($label) ?><small><?= e($hint) ?></small></span>
                    <span class="pill <?= $ok ? 'pill-ok' : 'pill-bad' ?>"><?= $ok ? 'OK' : 'Pendente' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php elseif ($lockedOut): ?>
    <div class="card">
        <h1>Muitas tentativas</h1>
        <p>O instalador bloqueou novas tentativas por uma hora. Aguarde e tente novamente.</p>
    </div>
<?php elseif ($done): ?>
    <div class="card">
        <h1>Sistema instalado com sucesso</h1>
        <p class="muted">Versão <?= e($done['version']) ?> · banco <?= e($done['db']) ?></p>

        <div class="alert alert-ok">
            O endereço do painel é <strong><?= e($done['app_url']) ?></strong>.
        </div>

        <h2>Contas criadas</h2>
        <ul>
            <li><strong>Administrador:</strong> <?= e($done['admin']) ?> (acesso total)</li>
            <?php if ($done['reader']): ?>
                <li><strong>Somente leitura:</strong> <?= e($done['reader']) ?> (consulta, sem alterações)</li>
            <?php endif; ?>
        </ul>

        <h2>Próximos passos</h2>
        <ul>
            <li>Entre no painel e troque as senhas em <strong>Meu perfil</strong> se necessário.</li>
            <li>Configure OVHcloud e LicenseBoy em <strong>Configurações</strong>.</li>
            <li>Cadastre no cPanel → Tarefas Cron: <code class="mono">* * * * * cd <?= e($basePath) ?> &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></li>
            <li>Cadastre também: <code class="mono">* * * * * cd <?= e($basePath) ?> &amp;&amp; php artisan queue:work --stop-when-empty --tries=3 &gt;&gt; /dev/null 2&gt;&amp;1</code></li>
            <li>Ative o SSL (AutoSSL) e mantenha o site em HTTPS.</li>
            <li>Faça backup periódico do banco e do arquivo <code>.env</code>.</li>
        </ul>

        <div class="notice">
            <?php if ($done['renamed']): ?>
                O arquivo do instalador foi renomeado para <code>instalador.php.instalado</code>. Você pode apagá-lo pelo Gerenciador de Arquivos.
            <?php else: ?>
                Apague manualmente o arquivo <code>public/instalador.php</code> pelo Gerenciador de Arquivos.
            <?php endif; ?>
            A senha de instalação já foi removida do servidor.
        </div>

        <div class="actions">
            <a class="btn" href="<?= e($done['app_url']) ?>/login">Abrir o painel</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <h1>Instalar o KDK Finance</h1>
        <p class="muted">Preencha os dados da hospedagem. Nada é gravado até você clicar em Instalar.</p>

        <?php if ($errors !== []): ?>
            <div class="alert alert-error">
                <strong>Corrija os itens abaixo:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <ul class="req">
            <?php foreach ($requirements as [$label, $ok, $hint]): ?>
                <li>
                    <span><?= e($label) ?><small><?= e($hint) ?></small></span>
                    <span class="pill <?= $ok ? 'pill-ok' : 'pill-bad' ?>"><?= $ok ? 'OK' : 'Pendente' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" autocomplete="off">
            <input type="hidden" name="_token" value="<?= e($csrf) ?>">
            <div class="field" style="position:absolute;left:-9999px" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h2>Senha de instalação</h2>
            <div class="field">
                <label for="install_key">Conteúdo de storage/app/instalar.senha</label>
                <input type="password" id="install_key" name="install_key" required autocomplete="off">
            </div>

            <h2>Endereço do sistema</h2>
            <div class="field">
                <label for="app_url">URL completa</label>
                <input type="url" id="app_url" name="app_url" value="<?= e($form('app_url', $isHttps ? 'https://'.($_SERVER['HTTP_HOST'] ?? '') : 'https://')) ?>" placeholder="https://financeiro.seudominio.com" required>
                <div class="hint">Use o domínio definitivo, com HTTPS. O redirecionamento seguro é ativado automaticamente.</div>
            </div>

            <h2>Banco de dados</h2>
            <div class="grid-3">
                <div class="field">
                    <label for="db_driver">Tipo</label>
                    <select id="db_driver" name="db_driver">
                        <option value="mysql" <?= $form('db_driver', 'mysql') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option>
                        <option value="sqlite" <?= $form('db_driver') === 'sqlite' ? 'selected' : '' ?>>SQLite (arquivo local)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="db_port">Porta</label>
                    <input type="text" id="db_port" name="db_port" value="<?= e($form('db_port', '3306')) ?>" inputmode="numeric">
                </div>
                <div class="field">
                    <label for="db_host">Servidor</label>
                    <input type="text" id="db_host" name="db_host" value="<?= e($form('db_host', 'localhost')) ?>">
                </div>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="db_name">Nome do banco</label>
                    <input type="text" id="db_name" name="db_name" value="<?= e($form('db_name')) ?>" placeholder="usuario_kdkfinance">
                </div>
                <div class="field">
                    <label for="db_user">Usuário do banco</label>
                    <input type="text" id="db_user" name="db_user" value="<?= e($form('db_user')) ?>" placeholder="usuario_kdk">
                </div>
            </div>
            <div class="field">
                <label for="db_password">Senha do banco</label>
                <input type="password" id="db_password" name="db_password" autocomplete="new-password">
                <div class="hint">Crie o banco e o usuário no cPanel → Bancos de Dados MySQL. O banco precisa estar vazio.</div>
            </div>

            <h2>Administrador</h2>
            <div class="grid">
                <div class="field">
                    <label for="admin_name">Nome</label>
                    <input type="text" id="admin_name" name="admin_name" value="<?= e($form('admin_name')) ?>" required>
                </div>
                <div class="field">
                    <label for="admin_email">E-mail de acesso</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= e($form('admin_email')) ?>" required autocomplete="username">
                </div>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="admin_password">Senha</label>
                    <input type="password" id="admin_password" name="admin_password" required autocomplete="new-password">
                    <div class="hint">Mínimo 10 caracteres, com maiúscula, minúscula e número.</div>
                </div>
                <div class="field">
                    <label for="admin_password_confirmation">Repita a senha</label>
                    <input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required autocomplete="new-password">
                </div>
            </div>

            <h2>Usuário somente leitura</h2>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="reader_enabled" value="1" <?= isset($_POST['reader_enabled']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
                    <span>Criar agora uma conta que só consulta os dados (sem criar, editar, excluir ou configurar).</span>
                </label>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="reader_name">Nome</label>
                    <input type="text" id="reader_name" name="reader_name" value="<?= e($form('reader_name', 'Acesso somente leitura')) ?>">
                </div>
                <div class="field">
                    <label for="reader_email">E-mail de acesso</label>
                    <input type="email" id="reader_email" name="reader_email" value="<?= e($form('reader_email')) ?>">
                </div>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="reader_password">Senha</label>
                    <input type="password" id="reader_password" name="reader_password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="reader_password_confirmation">Repita a senha</label>
                    <input type="password" id="reader_password_confirmation" name="reader_password_confirmation" autocomplete="new-password">
                </div>
            </div>

            <h2>E-mail (SMTP) — opcional</h2>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="mail_enabled" value="1" <?= isset($_POST['mail_enabled']) ? 'checked' : '' ?>>
                    <span>Configurar envio de alertas por e-mail agora.</span>
                </label>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="mail_host">Servidor SMTP</label>
                    <input type="text" id="mail_host" name="mail_host" value="<?= e($form('mail_host')) ?>" placeholder="mail.seudominio.com">
                </div>
                <div class="field">
                    <label for="mail_port">Porta</label>
                    <input type="text" id="mail_port" name="mail_port" value="<?= e($form('mail_port', '587')) ?>" inputmode="numeric">
                </div>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="mail_username">Usuário SMTP</label>
                    <input type="text" id="mail_username" name="mail_username" value="<?= e($form('mail_username')) ?>" autocomplete="off">
                </div>
                <div class="field">
                    <label for="mail_password">Senha SMTP</label>
                    <input type="password" id="mail_password" name="mail_password" autocomplete="new-password">
                </div>
            </div>
            <div class="grid">
                <div class="field">
                    <label for="mail_encryption">Criptografia</label>
                    <select id="mail_encryption" name="mail_encryption">
                        <option value="tls" <?= $form('mail_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS (porta 587)</option>
                        <option value="ssl" <?= $form('mail_encryption') === 'ssl' ? 'selected' : '' ?>>SSL (porta 465)</option>
                        <option value="none" <?= $form('mail_encryption') === 'none' ? 'selected' : '' ?>>Sem criptografia</option>
                    </select>
                </div>
                <div class="field">
                    <label for="mail_from">E-mail de envio</label>
                    <input type="email" id="mail_from" name="mail_from" value="<?= e($form('mail_from')) ?>" placeholder="alertas@seudominio.com">
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Instalar agora</button>
                <span class="muted">Após instalar, o instalador é bloqueado e renomeado.</span>
            </div>
        </form>
    </div>
<?php endif; ?>
<?php pageEnd(); ?>
