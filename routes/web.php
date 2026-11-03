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

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    // ---------------------------------------------------------------------
    // Faturas — leitura para todos, escrita para gestores/desenvolvedores
    // ---------------------------------------------------------------------
    Route::get('/faturas', [InvoiceController::class, 'index'])->name('invoices.index');

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
    });

    Route::middleware('role:DEVELOPER_ADMIN')->group(function (): void {
        Route::put('/configuracoes/ovh', [SettingsController::class, 'updateOvh'])->name('settings.ovh');
        Route::post('/configuracoes/ovh/testar', [SettingsController::class, 'testOvh'])->name('settings.ovh.test');
        Route::post('/configuracoes/cotacoes/atualizar', [SettingsController::class, 'refreshRates'])->name('settings.rates.refresh');
        Route::put('/configuracoes/regras', [SettingsController::class, 'updateOperational'])->name('settings.rules');

        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
