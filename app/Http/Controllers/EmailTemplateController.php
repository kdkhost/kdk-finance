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

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\MailTemplateService;
use App\Support\Settings\MailSettings;
use App\Support\Settings\MailTheme;
use App\Support\Settings\MailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Gestão dos modelos transacionais de e-mail dentro do painel.
 *
 * O conteúdo exibido ao público é montado no tema responsivo compartilhado
 * (emails.themed); aqui o administrador edita apenas os textos, os campos do
 * bloco de detalhes, o botão e a assinatura.
 */
class EmailTemplateController extends Controller
{
    public function index(MailTemplateService $mailTemplates): View
    {
        // Autocura: instalações antigas que ainda não rodaram o seeder recebem
        // os modelos padrão na primeira abertura da tela.
        foreach (MailTemplates::defaults() as $key => $default) {
            if (! EmailTemplate::query()->where('key', $key)->exists()) {
                EmailTemplate::query()->create(array_merge($default, ['key' => $key]));
            }
        }

        $stored = EmailTemplate::query()->get()->keyBy('key');

        $templates = collect(array_keys(MailTemplates::defaults()))
            ->map(fn (string $key): EmailTemplate => $stored->get($key) ?? $mailTemplates->template($key));

        return view('emails.templates.index', [
            'templates' => $templates,
            'mailConfigured' => MailSettings::configured(),
            'alertRecipients' => MailSettings::alertRecipients(),
        ]);
    }

    public function edit(EmailTemplate $template): View
    {
        $defaults = MailTemplates::defaults()[$template->key] ?? null;

        return view('emails.templates.form', [
            'template' => $template,
            'detailsText' => EmailTemplate::detailsAsText($template->details),
            'variables' => $template->variables(),
            'defaults' => $defaults,
            'mailConfigured' => MailSettings::configured(),
        ]);
    }

    public function update(Request $request, EmailTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'heading' => ['required', 'string', 'max:150'],
            'intro' => ['required', 'string', 'max:1500'],
            'details' => ['nullable', 'string', 'max:2000'],
            'action_text' => ['nullable', 'string', 'max:80'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'footnote' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
        ], [], [
            'subject' => 'assunto',
            'heading' => 'título',
            'intro' => 'texto de abertura',
            'details' => 'detalhes',
            'action_text' => 'texto do botão',
            'action_url' => 'link do botão',
            'footnote' => 'assinatura',
        ]);

        $template->fill([
            'subject' => trim($data['subject']),
            'heading' => trim($data['heading']),
            'intro' => trim($data['intro']),
            'details' => EmailTemplate::parseDetails($data['details'] ?? ''),
            'action_text' => trim((string) ($data['action_text'] ?? '')),
            'action_url' => trim((string) ($data['action_url'] ?? '')),
            'footnote' => trim((string) ($data['footnote'] ?? '')),
            'enabled' => $request->boolean('enabled'),
        ])->save();

        return redirect()
            ->route('email-templates.edit', $template)
            ->with('success', 'Modelo "'.$template->name.'" salvo. A prévia ao lado já usa o texto novo.');
    }

    public function toggle(EmailTemplate $template): RedirectResponse
    {
        $template->update(['enabled' => ! $template->enabled]);

        return back()->with(
            'success',
            $template->enabled
                ? 'Modelo ativado; ele volta a ser enviado pelos alertas automáticos.'
                : 'Modelo desativado; os alertas deixam de enviar esta mensagem.'
        );
    }

    public function restore(EmailTemplate $template): RedirectResponse
    {
        $defaults = MailTemplates::defaults()[$template->key] ?? null;

        if ($defaults === null) {
            return back()->with('warning', 'Este modelo não possui um padrão de fábrica para restaurar.');
        }

        $template->fill([
            'subject' => $defaults['subject'],
            'heading' => $defaults['heading'],
            'intro' => $defaults['intro'],
            'details' => $defaults['details'],
            'action_text' => (string) ($defaults['action_text'] ?? ''),
            'action_url' => (string) ($defaults['action_url'] ?? ''),
            'footnote' => (string) ($defaults['footnote'] ?? ''),
            'enabled' => true,
        ])->save();

        return redirect()
            ->route('email-templates.edit', $template)
            ->with('success', 'Conteúdo restaurado para o padrão de fábrica.');
    }

    public function preview(EmailTemplate $template, MailTemplateService $mailTemplates): View
    {
        $payload = $mailTemplates->sample($template->key) ?? [];

        return view('emails.themed', [
            'theme' => MailTheme::all(),
            'heading' => $payload['heading'] ?? $template->heading,
            'intro' => $payload['intro'] ?? $template->intro,
            'details' => $payload['details'] ?? [],
            'actionText' => $payload['action_text'] ?? null,
            'actionUrl' => $payload['action_url'] ?? route('dashboard'),
            'footnote' => $payload['footnote'] ?? '',
            'preheader' => $payload['preheader'] ?? '',
        ]);
    }

    /**
     * Envia o modelo com os dados de exemplo para validar texto e entrega.
     */
    public function test(Request $request, EmailTemplate $template, MailTemplateService $mailTemplates): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], [], [
            'test_email' => 'e-mail de teste',
        ]);

        if (! MailSettings::configured()) {
            return back()->with('error', 'Salve o SMTP ativo, com servidor e remetente, antes de enviar o modelo.');
        }

        try {
            $sent = $mailTemplates->send(
                $template->key,
                MailTemplates::samples($template->key),
                $data['test_email'],
                true
            );

            if (! $sent) {
                throw new \RuntimeException('O modelo não pôde ser montado para envio.');
            }

            return back()->with('success', 'Modelo enviado para '.$data['test_email'].'. Confira a caixa de entrada e o spam.');
        } catch (Throwable $exception) {
            Log::warning('Teste de modelo de e-mail falhou.', [
                'template' => $template->key,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Falha no envio: '.mb_substr($exception->getMessage(), 0, 220));
        }
    }
}
