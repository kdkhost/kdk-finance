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

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\CurrencyConversionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\InfrastructureController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LiveSnapshotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    // O limite de tentativas é lido de app_settings (aba Segurança).
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/painel/ao-vivo', LiveSnapshotController::class)->name('live.snapshot');

    // ---------------------------------------------------------------------
    // Faturas — leitura para todos, escrita para gestores/desenvolvedores
    // ---------------------------------------------------------------------
    Route::get('/faturas', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/servicos', InfrastructureController::class)->name('infrastructure.index');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        // Antes da rota com parâmetro para não capturar "nova" como id.
        Route::get('/faturas/nova', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/faturas', [InvoiceController::class, 'store'])->name('invoices.store');
    });

    Route::get('/faturas/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/faturas/{invoice}/editar', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/faturas/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/faturas/{invoice}/pagar', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('/faturas/{invoice}/cancelar', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('/faturas/{invoice}/reabrir', [InvoiceController::class, 'reopen'])->name('invoices.reopen');
        Route::post('/faturas/{invoice}/recalcular', [InvoiceController::class, 'recalculate'])->name('invoices.recalculate');
        Route::post('/faturas/{invoice}/travar-cambio', [InvoiceController::class, 'toggleLock'])->name('invoices.lock');
        Route::get('/faturas-exportar', [InvoiceController::class, 'export'])->name('invoices.export');
    });

    // ---------------------------------------------------------------------
    // Licenças (LicenseBoy) — leitura para todos, escrita para gestores
    // ---------------------------------------------------------------------
    Route::get('/licencas', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/licencas/precos', [LicenseController::class, 'prices'])->name('licenses.prices');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/licencas/nova', [LicenseController::class, 'create'])->name('licenses.create');
        Route::post('/licencas', [LicenseController::class, 'store'])->name('licenses.store');
        Route::post('/licencas/precos/atualizar', [LicenseController::class, 'syncPrices'])->name('licenses.prices.sync');
        Route::post('/licencas/painel/atualizar', [LicenseController::class, 'syncPanel'])->name('licenses.panel.sync');
    });

    Route::get('/licencas/{license}', [LicenseController::class, 'show'])->name('licenses.show');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/licencas/{license}/editar', [LicenseController::class, 'edit'])->name('licenses.edit');
        Route::put('/licencas/{license}', [LicenseController::class, 'update'])->name('licenses.update');
        Route::delete('/licencas/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');
        Route::post('/licencas/{license}/fatura', [LicenseController::class, 'generateInvoice'])->name('licenses.invoice');
    });

    // ---------------------------------------------------------------------
    // Conversões EUR ↔ USD com tarifas de cartão internacional
    // ---------------------------------------------------------------------
    Route::get('/conversoes', [CurrencyConversionController::class, 'index'])->name('conversions.index');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/conversoes/nova', [CurrencyConversionController::class, 'create'])->name('conversions.create');
        Route::post('/conversoes', [CurrencyConversionController::class, 'store'])->name('conversions.store');
        Route::get('/conversoes/cotacao', [CurrencyConversionController::class, 'quote'])->name('conversions.quote');
    });

    Route::get('/conversoes/{conversion}', [CurrencyConversionController::class, 'show'])->name('conversions.show');

    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/conversoes/{conversion}/editar', [CurrencyConversionController::class, 'edit'])->name('conversions.edit');
        Route::put('/conversoes/{conversion}', [CurrencyConversionController::class, 'update'])->name('conversions.update');
        Route::delete('/conversoes/{conversion}', [CurrencyConversionController::class, 'destroy'])->name('conversions.destroy');
        Route::post('/conversoes/{conversion}/recalcular', [CurrencyConversionController::class, 'recalculate'])->name('conversions.recalculate');
        Route::post('/conversoes/{conversion}/conferir', [CurrencyConversionController::class, 'calibrate'])->name('conversions.calibrate');
    });

    // ---------------------------------------------------------------------
    // Central de cron — somente desenvolvedor/administrador
    // ---------------------------------------------------------------------
    Route::middleware('role:DEVELOPER_ADMIN')->group(function (): void {
        Route::get('/crons', [CronJobController::class, 'index'])->name('crons.index');
        Route::get('/crons/novo', [CronJobController::class, 'create'])->name('crons.create');
        Route::post('/crons', [CronJobController::class, 'store'])->name('crons.store');
        Route::post('/crons/restaurar-padroes', [CronJobController::class, 'restoreDefaults'])->name('crons.restore');
        Route::post('/crons/ambiente', [CronJobController::class, 'updateEnvironment'])->name('crons.environment');
        Route::get('/crons/{cron}', [CronJobController::class, 'show'])->name('crons.show');
        Route::get('/crons/{cron}/editar', [CronJobController::class, 'edit'])->name('crons.edit');
        Route::put('/crons/{cron}', [CronJobController::class, 'update'])->name('crons.update');
        Route::delete('/crons/{cron}', [CronJobController::class, 'destroy'])->name('crons.destroy');
        Route::post('/crons/{cron}/executar', [CronJobController::class, 'run'])->name('crons.run');
        Route::post('/crons/{cron}/alternar', [CronJobController::class, 'toggle'])->name('crons.toggle');
    });

    // ---------------------------------------------------------------------
    // Central de notificações
    // ---------------------------------------------------------------------
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/{id}/ler', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notificacoes/ler-todas', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ---------------------------------------------------------------------
    // Perfil
    // ---------------------------------------------------------------------
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // ---------------------------------------------------------------------
    // Configurações — gestor financeiro vê impostos; desenvolvedor vê tudo
    // ---------------------------------------------------------------------
    Route::middleware('role:DEVELOPER_ADMIN,FINANCIAL_MANAGER')->group(function (): void {
        Route::get('/configuracoes', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/configuracoes/tributos', [SettingsController::class, 'updateTaxes'])->name('settings.taxes');
        Route::put('/configuracoes/conversao', [SettingsController::class, 'updateConversion'])->name('settings.conversion');
    });

    Route::middleware('role:DEVELOPER_ADMIN')->group(function (): void {
        Route::put('/configuracoes/ovh', [SettingsController::class, 'updateOvh'])->name('settings.ovh');
        Route::post('/configuracoes/ovh/testar', [SettingsController::class, 'testOvh'])->name('settings.ovh.test');
        Route::put('/configuracoes/licenseboy', [SettingsController::class, 'updateLicenseBoy'])->name('settings.licenseboy');
        Route::post('/configuracoes/cotacoes/atualizar', [SettingsController::class, 'refreshRates'])->name('settings.rates.refresh');
        Route::put('/configuracoes/regras', [SettingsController::class, 'updateOperational'])->name('settings.rules');

        // Segurança operacional: tudo gravado em app_settings.
        Route::put('/configuracoes/seguranca', [SettingsController::class, 'updateSecurity'])->name('settings.security');
        Route::post('/configuracoes/seguranca/sessoes', [SettingsController::class, 'revokeOtherSessions'])->name('settings.sessions.revoke');

        // SMTP, remetente e tema dos e-mails.
        Route::put('/configuracoes/email', [SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::post('/configuracoes/email/testar', [SettingsController::class, 'testMail'])->name('settings.mail.test');
        Route::get('/configuracoes/email/previa', [SettingsController::class, 'previewMail'])->name('settings.mail.preview');

        // Modelos transacionais editáveis no painel.
        Route::get('/configuracoes/email/modelos', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('/configuracoes/email/modelos/{template}/editar', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('/configuracoes/email/modelos/{template}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::post('/configuracoes/email/modelos/{template}/alternar', [EmailTemplateController::class, 'toggle'])->name('email-templates.toggle');
        Route::post('/configuracoes/email/modelos/{template}/restaurar', [EmailTemplateController::class, 'restore'])->name('email-templates.restore');
        Route::post('/configuracoes/email/modelos/{template}/testar', [EmailTemplateController::class, 'test'])->name('email-templates.test');
        Route::get('/configuracoes/email/modelos/{template}/previa', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');

        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
