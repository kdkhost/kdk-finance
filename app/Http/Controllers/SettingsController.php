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

use App\Mail\ThemedMail;
use App\Models\AppSetting;
use App\Services\ExchangeRateService;
use App\Services\LicenseBoyPanelService;
use App\Services\OvhService;
use App\Support\Settings\MailSettings;
use App\Support\Settings\MailTheme;
use App\Support\Settings\SecuritySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(ExchangeRateService $exchange, OvhService $ovh): View
    {
        $settings = AppSetting::query()->orderBy('group')->orderBy('key')->get();
        $user = request()->user();
        $mailSettings = MailSettings::all();
        unset($mailSettings['mail_password']);

        return view('settings.index', [
            'settings' => $settings,
            'rates' => $exchange->rates(),
            'pair' => $exchange->pair('EUR', 'USD'),
            'ovhConfigured' => $ovh->isConfigured(),
            'licenseboyConfigured' => app(LicenseBoyPanelService::class)->isConfigured(),
            'licenseboySyncedAt' => AppSetting::get('licenseboy_panel_synced_at'),
            'licenseboyEmail' => (string) AppSetting::get('licenseboy_panel_email', ''),
            'mailSettings' => $mailSettings,
            'mailConfigured' => MailSettings::configured(),
            'mailDriver' => MailSettings::driver(),
            'mailMaskedPassword' => AppSetting::mask(MailSettings::password()),
            'mailTheme' => MailTheme::all(),
            'securitySettings' => SecuritySettings::all(),
            'sessions' => $user?->isDeveloperAdmin() ? $this->activeSessions((int) $user->id) : collect(),
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

    /**
     * Grava as opções de segurança operacional (antes espalhadas pelo .env).
     */
    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'security_force_https' => ['nullable', 'boolean'],
            'security_hsts_enabled' => ['nullable', 'boolean'],
            'security_hsts_max_age' => ['required', 'integer', 'min:1', 'max:730'],
            'security_headers_enabled' => ['nullable', 'boolean'],
            'security_csp_enabled' => ['nullable', 'boolean'],
            'security_frame_options' => ['required', Rule::in(['SAMEORIGIN', 'DENY'])],
            'security_trust_hosts' => ['nullable', 'boolean'],
            'security_allowed_hosts' => ['nullable', 'string', 'max:1000'],
            'security_trusted_proxies' => ['nullable', 'string', 'max:1000'],
            'security_no_store' => ['nullable', 'boolean'],
            'security_session_lifetime' => ['required', 'integer', 'min:5', 'max:43200'],
            'security_session_encrypt' => ['nullable', 'boolean'],
            'security_session_secure' => ['nullable', 'boolean'],
            'security_session_same_site' => ['required', Rule::in(['lax', 'strict', 'none'])],
            'security_session_expire_on_close' => ['nullable', 'boolean'],
            'security_login_max_attempts' => ['required', 'integer', 'min:3', 'max:50'],
            'security_login_decay_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
            'security_password_min' => ['required', 'integer', 'min:8', 'max:72'],
            'security_password_mixed_case' => ['nullable', 'boolean'],
            'security_password_numbers' => ['nullable', 'boolean'],
            'security_password_symbols' => ['nullable', 'boolean'],
        ], [], [
            'security_hsts_max_age' => 'validade do HSTS',
            'security_frame_options' => 'proteção contra enquadramento',
            'security_allowed_hosts' => 'hosts permitidos',
            'security_trusted_proxies' => 'proxies confiáveis',
            'security_session_lifetime' => 'duração da sessão',
            'security_session_same_site' => 'SameSite',
            'security_login_max_attempts' => 'tentativas de login',
            'security_login_decay_seconds' => 'bloqueio de login',
            'security_password_min' => 'tamanho mínimo da senha',
        ]);

        foreach ([
            'security_force_https',
            'security_hsts_enabled',
            'security_headers_enabled',
            'security_csp_enabled',
            'security_trust_hosts',
            'security_no_store',
            'security_session_encrypt',
            'security_session_secure',
            'security_session_expire_on_close',
            'security_password_mixed_case',
            'security_password_numbers',
            'security_password_symbols',
        ] as $key) {
            AppSetting::set($key, $request->boolean($key) ? '1' : '0', 'security');
        }

        AppSetting::set('security_hsts_max_age', (string) $data['security_hsts_max_age'], 'security');
        AppSetting::set('security_frame_options', $data['security_frame_options'], 'security');
        AppSetting::set(
            'security_allowed_hosts',
            implode(', ', SecuritySettings::normalizeHosts((string) ($data['security_allowed_hosts'] ?? ''))),
            'security'
        );
        AppSetting::set(
            'security_trusted_proxies',
            implode(', ', SecuritySettings::trustedProxiesFrom((string) ($data['security_trusted_proxies'] ?? ''))),
            'security'
        );
        AppSetting::set('security_session_lifetime', (string) $data['security_session_lifetime'], 'security');
        AppSetting::set('security_session_same_site', $data['security_session_same_site'], 'security');
        AppSetting::set('security_login_max_attempts', (string) $data['security_login_max_attempts'], 'security');
        AppSetting::set('security_login_decay_seconds', (string) $data['security_login_decay_seconds'], 'security');
        AppSetting::set('security_password_min', (string) $data['security_password_min'], 'security');

        return redirect()
            ->route('settings.index', ['tab' => 'security'])
            ->with('success', 'Segurança atualizada no banco. As novas regras valem na próxima requisição; mudanças de sessão podem pedir um novo login.');
    }

    /**
     * Encerra todas as sessões do usuário atual, exceto a que fez a requisição.
     */
    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        if (config('session.driver') !== 'database') {
            return redirect()
                ->route('settings.index', ['tab' => 'security'])
                ->with('error', 'O driver de sessão atual não permite revogação pelo banco. Use o driver "database".');
        }

        $deleted = DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        if ($deleted === 0) {
            return redirect()
                ->route('settings.index', ['tab' => 'security'])
                ->with('info', 'Nenhuma outra sessão ativa foi encontrada.');
        }

        return redirect()
            ->route('settings.index', ['tab' => 'security'])
            ->with('success', $deleted === 1
                ? '1 outra sessão foi encerrada.'
                : $deleted.' outras sessões foram encerradas.');
    }

    /**
     * SMTP completo, remetente e tema visual dos e-mails.
     */
    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_enabled' => ['nullable', 'boolean'],
            'mail_host' => ['nullable', 'string', 'max:255', 'required_if:mail_enabled,1', 'regex:/^[a-zA-Z0-9.\-]+$/'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mail_auth' => ['nullable', 'boolean'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_timeout' => ['required', 'integer', 'min:3', 'max:120'],
            'mail_from_address' => ['nullable', 'email', 'max:255', 'required_if:mail_enabled,1'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'mail_reply_to_address' => ['nullable', 'email', 'max:255'],
            'mail_reply_to_name' => ['nullable', 'string', 'max:120'],
            'clear_mail_password' => ['nullable', 'boolean'],
            'theme_brand' => ['required', 'string', 'max:80'],
            'theme_logo_enabled' => ['nullable', 'boolean'],
            'theme_primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_text' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_muted' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_footer' => ['nullable', 'string', 'max:500'],
            'theme_signature' => ['nullable', 'string', 'max:255'],
            'theme_support_email' => ['nullable', 'email', 'max:255'],
            'theme_support_url' => ['nullable', 'url:http,https', 'max:255'],
            'theme_company' => ['nullable', 'string', 'max:255'],
        ], [], [
            'mail_host' => 'servidor SMTP',
            'mail_port' => 'porta SMTP',
            'mail_encryption' => 'criptografia',
            'mail_timeout' => 'tempo limite',
            'mail_from_address' => 'e-mail de envio',
            'mail_from_name' => 'nome do remetente',
            'mail_reply_to_address' => 'e-mail de resposta',
            'theme_brand' => 'marca',
            'theme_primary' => 'cor principal',
            'theme_accent' => 'cor de destaque',
            'theme_background' => 'cor de fundo',
            'theme_text' => 'cor do texto',
            'theme_muted' => 'cor secundária',
        ]);

        foreach (['mail_enabled', 'mail_auth', 'theme_logo_enabled'] as $key) {
            AppSetting::set($key, $request->boolean($key) ? '1' : '0', $key === 'theme_logo_enabled' ? 'mail_theme' : 'mail');
        }

        AppSetting::set('mail_host', strtolower(trim((string) ($data['mail_host'] ?? ''))), 'mail');
        AppSetting::set('mail_port', (string) $data['mail_port'], 'mail');
        AppSetting::set('mail_encryption', $data['mail_encryption'], 'mail');
        AppSetting::set('mail_username', trim((string) ($data['mail_username'] ?? '')), 'mail');
        AppSetting::set('mail_timeout', (string) $data['mail_timeout'], 'mail');
        AppSetting::set('mail_from_address', strtolower(trim((string) ($data['mail_from_address'] ?? ''))), 'mail');
        AppSetting::set('mail_from_name', trim((string) ($data['mail_from_name'] ?? '')), 'mail');
        AppSetting::set('mail_reply_to_address', strtolower(trim((string) ($data['mail_reply_to_address'] ?? ''))), 'mail');
        AppSetting::set('mail_reply_to_name', trim((string) ($data['mail_reply_to_name'] ?? '')), 'mail');

        if ($request->boolean('clear_mail_password')) {
            AppSetting::set('mail_password', '', 'mail', true);
        } elseif (($data['mail_password'] ?? '') !== '') {
            AppSetting::set('mail_password', (string) $data['mail_password'], 'mail', true);
        }

        foreach ([
            'theme_brand',
            'theme_primary',
            'theme_accent',
            'theme_background',
            'theme_text',
            'theme_muted',
            'theme_footer',
            'theme_signature',
            'theme_support_email',
            'theme_support_url',
            'theme_company',
        ] as $key) {
            AppSetting::set($key, trim((string) ($data[$key] ?? '')), 'mail_theme');
        }

        return redirect()
            ->route('settings.index', ['tab' => 'mail'])
            ->with('success', $request->boolean('mail_enabled')
                ? 'SMTP e tema salvos. Use "Enviar teste" para validar a entrega.'
                : 'Configuração salva. O envio está desativado; os e-mails são gravados apenas no log.');
    }

    /**
     * Envia uma mensagem real usando exatamente o SMTP salvo no banco.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], [], [
            'test_email' => 'e-mail de teste',
        ]);

        if (! MailSettings::configured()) {
            return redirect()
                ->route('settings.index', ['tab' => 'mail'])
                ->with('error', 'Salve o SMTP ativo, com servidor e remetente, antes de enviar o teste.');
        }

        MailSettings::apply();

        try {
            Mail::to($data['test_email'])->send(new ThemedMail(
                heading: 'Teste de envio concluído',
                intro: 'Se você recebeu esta mensagem, o servidor SMTP configurado no painel está respondendo e o tema dos e-mails está sendo aplicado.',
                details: [
                    'Servidor' => MailSettings::host().':'.MailSettings::port(),
                    'Criptografia' => strtoupper(MailSettings::encryption()),
                    'Remetente' => MailSettings::fromName().' <'.MailSettings::fromAddress().'>',
                ],
                actionText: 'Abrir o painel',
                actionUrl: route('dashboard'),
                footnote: 'Mensagem de teste gerada pelo painel de configurações. Nenhum dado financeiro foi incluído.',
                preheader: 'Teste do SMTP e do tema de e-mails do painel.',
                subject: 'Teste de e-mail · '.MailTheme::brand(),
            ));

            AppSetting::set('mail_last_test_at', now()->toIso8601String(), 'mail');
            AppSetting::set('mail_last_test_status', 'ok', 'mail');
            AppSetting::set('mail_last_test_message', 'Teste entregue ao servidor SMTP para '.$data['test_email'].'.', 'mail');

            return redirect()
                ->route('settings.index', ['tab' => 'mail'])
                ->with('success', 'Teste enviado para '.$data['test_email'].'. Confira a caixa de entrada e o spam.');
        } catch (Throwable $exception) {
            Log::warning('Teste de SMTP falhou.', ['error' => $exception->getMessage()]);

            AppSetting::set('mail_last_test_at', now()->toIso8601String(), 'mail');
            AppSetting::set('mail_last_test_status', 'falha', 'mail');
            AppSetting::set('mail_last_test_message', mb_substr($exception->getMessage(), 0, 300), 'mail');

            return redirect()
                ->route('settings.index', ['tab' => 'mail'])
                ->with('error', 'Falha no envio: '.mb_substr($exception->getMessage(), 0, 220));
        }
    }

    /**
     * Prévia isolada do e-mail, usada dentro de um iframe no painel.
     */
    public function previewMail(): View
    {
        return view('emails.themed', [
            'theme' => MailTheme::all(),
            'heading' => 'Sua fatura foi atualizada',
            'intro' => 'Este é um exemplo de como os alertas do painel chegam na caixa de entrada. O layout se adapta a celulares e clientes de e-mail.',
            'details' => [
                'Fatura' => 'OVH-2026-001',
                'Vencimento' => '15/11/2026',
                'Valor atualizado' => 'R$ 1.284,90',
            ],
            'actionText' => 'Abrir a fatura',
            'actionUrl' => route('dashboard'),
            'footnote' => 'Mensagem de exemplo. Ajuste cores, textos e assinatura na aba E-mail.',
            'preheader' => 'Prévia do tema de e-mail do painel financeiro.',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function activeSessions(int $userId): \Illuminate\Support\Collection
    {
        try {
            return DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (string) $row->id,
                    'ip' => $row->ip_address ?: '—',
                    'agent' => $this->describeAgent($row->user_agent),
                    'last_activity' => Carbon::createFromTimestamp((int) $row->last_activity),
                    'current' => (string) $row->id === session()->getId(),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Não foi possível listar as sessões ativas.', ['error' => $exception->getMessage()]);

            return collect();
        }
    }

    private function describeAgent(?string $agent): string
    {
        $agent = (string) $agent;

        if ($agent === '') {
            return 'Dispositivo não identificado';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') && ! str_contains($agent, 'Chromium') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'sistema não identificado',
        };

        return $browser.' em '.$platform;
    }
}
