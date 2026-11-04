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

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\ExchangeRateService;
use App\Services\LicenseBoyPanelService;
use App\Services\OvhService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(ExchangeRateService $exchange, OvhService $ovh): View
    {
        $settings = AppSetting::query()->orderBy('group')->orderBy('key')->get();

        return view('settings.index', [
            'settings' => $settings,
            'rates' => $exchange->rates(),
            'pair' => $exchange->pair('EUR', 'USD'),
            'ovhConfigured' => $ovh->isConfigured(),
            'licenseboyConfigured' => app(LicenseBoyPanelService::class)->isConfigured(),
            'licenseboySyncedAt' => AppSetting::get('licenseboy_panel_synced_at'),
            'licenseboyEmail' => (string) AppSetting::get('licenseboy_panel_email', ''),
            'masked' => [
                'ovh_application_key' => AppSetting::mask((string) AppSetting::get('ovh_application_key', '')),
                'ovh_application_secret' => AppSetting::mask((string) AppSetting::get('ovh_application_secret', '')),
                'ovh_consumer_key' => AppSetting::mask((string) AppSetting::get('ovh_consumer_key', '')),
                'ovh_v2_bearer_token' => AppSetting::mask((string) AppSetting::get('ovh_v2_bearer_token', '')),
                'licenseboy_panel_password' => AppSetting::mask((string) AppSetting::get('licenseboy_panel_password', '')),
            ],
        ]);
    }

    public function updateTaxes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'spread_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'iof_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'icms_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'other_taxes_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::set($key, number_format((float) $value, 2, '.', ''), 'taxes');
        }

        return redirect()
            ->route('settings.index', ['tab' => 'taxes'])
            ->with('success', 'Percentuais atualizados. Recalcule as faturas pendentes para aplicar.');
    }

    public function updateConversion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'conversion_spread_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'conversion_iof_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'conversion_fixed_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'conversion_buffer_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::set($key, number_format((float) $value, 3, '.', ''), 'conversion');
        }

        return redirect()
            ->route('settings.index', ['tab' => 'conversion'])
            ->with('success', 'Tarifas de conversão atualizadas. As novas conversões já usam estes valores.');
    }

    public function updateOvh(Request $request): RedirectResponse    {
        $data = $request->validate([
            'ovh_endpoint' => ['required', Rule::in(['ovh-eu', 'ovh-ca', 'ovh-us'])],
            'ovh_application_key' => ['nullable', 'string', 'max:255'],
            'ovh_application_secret' => ['nullable', 'string', 'max:255'],
            'ovh_consumer_key' => ['nullable', 'string', 'max:255'],
            'ovh_v2_bearer_token' => ['nullable', 'string', 'max:4000'],
            'clear_credentials' => ['nullable', 'boolean'],
        ]);

        AppSetting::set('ovh_endpoint', $data['ovh_endpoint'], 'ovh');

        if ($request->boolean('clear_credentials')) {
            foreach (['ovh_application_key', 'ovh_application_secret', 'ovh_consumer_key', 'ovh_v2_bearer_token'] as $key) {
                AppSetting::set($key, '', 'ovh', true);
            }

            return redirect()
                ->route('settings.index', ['tab' => 'ovh'])
                ->with('success', 'Credenciais da OVHcloud removidas do banco de dados.');
        }

        $filled = 0;

        foreach (['ovh_application_key', 'ovh_application_secret', 'ovh_consumer_key', 'ovh_v2_bearer_token'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));

            if ($value !== '') {
                AppSetting::set($key, $value, 'ovh', true);
                $filled++;
            }
        }

        return redirect()
            ->route('settings.index', ['tab' => 'ovh'])
            ->with('success', $filled > 0
                ? $filled.' credencial(is) atualizada(s) e gravada(s) criptografada(s).'
                : 'Endpoint atualizado. Campos em branco foram preservados.');
    }

    public function updateLicenseBoy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'licenseboy_panel_email' => ['nullable', 'email', 'max:190'],
            'licenseboy_panel_password' => ['nullable', 'string', 'max:255'],
            'clear_licenseboy_credentials' => ['nullable', 'boolean'],
        ], [], [
            'licenseboy_panel_email' => 'e-mail do painel',
            'licenseboy_panel_password' => 'senha do painel',
        ]);

        if ($request->boolean('clear_licenseboy_credentials')) {
            AppSetting::set('licenseboy_panel_email', '', 'licenseboy');
            AppSetting::set('licenseboy_panel_password', '', 'licenseboy', true);
            AppSetting::set('licenseboy_panel_synced_at', '', 'licenseboy');

            return redirect()
                ->route('settings.index', ['tab' => 'licenseboy'])
                ->with('success', 'Credenciais do painel da LicenseBoy removidas do banco de dados.');
        }

        $email = trim((string) ($data['licenseboy_panel_email'] ?? ''));
        $password = (string) ($data['licenseboy_panel_password'] ?? '');

        if ($email !== '') {
            AppSetting::set('licenseboy_panel_email', $email, 'licenseboy');
        }

        if ($password !== '') {
            AppSetting::set('licenseboy_panel_password', $password, 'licenseboy', true);
        }

        return redirect()
            ->route('settings.index', ['tab' => 'licenseboy'])
            ->with('success', ($email !== '' || $password !== '')
                ? 'Credenciais do painel atualizadas e gravadas criptografadas. Use "Sincronizar painel" em Licenças.'
                : 'Campos em branco foram preservados.');
    }

    public function testOvh(OvhService $ovh): JsonResponse
    {
        try {
            $data = $ovh->testConnection();

            return response()->json([
                'ok' => true,
                'message' => 'Conexão validada para '.$data['nichandle'].' ('.($data['services_count'] ?? 0).' serviços).',
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Teste de conexão OVHcloud falhou.', ['error' => $exception->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function refreshRates(ExchangeRateService $exchange): RedirectResponse
    {
        $rates = $exchange->rates(true);
        $stale = collect($rates)->contains(fn (array $rate): bool => $rate['stale'] ?? false);

        return redirect()
            ->route('settings.index', ['tab' => 'taxes'])
            ->with($stale ? 'warning' : 'success', $stale
                ? 'API indisponível: mantidas as últimas cotações conhecidas.'
                : 'Cotações atualizadas: USD R$ '.number_format($rates['USD']['rate'], 4, ',', '.').' · EUR R$ '.number_format($rates['EUR']['rate'], 4, ',', '.'));
    }

    public function updateOperational(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rate_variation_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'ovh_auto_import' => ['nullable', 'boolean'],
            'ovh_import_days' => ['required', 'integer', 'min:1', 'max:365'],
            'ovh_import_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'notify_email' => ['nullable', 'email', 'max:255'],
        ]);

        AppSetting::set('rate_variation_threshold', number_format((float) $data['rate_variation_threshold'], 2, '.', ''), 'finance');
        AppSetting::set('ovh_auto_import', $request->boolean('ovh_auto_import') ? '1' : '0', 'ovh');
        AppSetting::set('ovh_import_days', (string) $data['ovh_import_days'], 'ovh');
        AppSetting::set('ovh_import_limit', (string) $data['ovh_import_limit'], 'ovh');
        AppSetting::set('notify_email', $data['notify_email'] ?? '', 'mail');

        return redirect()
            ->route('settings.index', ['tab' => 'rules'])
            ->with('success', 'Regras operacionais salvas.');
    }
}
