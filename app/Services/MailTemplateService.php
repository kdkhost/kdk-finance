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

namespace App\Services;

use App\Mail\ThemedMail;
use App\Models\EmailTemplate;
use App\Support\Settings\MailSettings;
use App\Support\Settings\MailTemplates;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renderiza e envia os modelos transacionais guardados no painel.
 *
 * O envio só acontece com SMTP completo; sem configuração, os alertas são
 * ignorados para não acumular mensagens no log do servidor.
 */
class MailTemplateService
{
    public function template(string $key): ?EmailTemplate
    {
        $template = EmailTemplate::query()->where('key', $key)->first();

        if ($template !== null) {
            return $template;
        }

        $defaults = MailTemplates::defaults()[$key] ?? null;

        if ($defaults === null) {
            return null;
        }

        return new EmailTemplate($defaults + ['key' => $key]);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array{subject: string, heading: string, intro: string, details: array<string, string>, action_text: ?string, action_url: ?string, footnote: string, preheader: string}|null
     */
    public function payload(string $key, array $variables): ?array
    {
        $template = $this->template($key);

        return $template?->render($variables);
    }

    /**
     * @return array{subject: string, heading: string, intro: string, details: array<string, string>, action_text: ?string, action_url: ?string, footnote: string, preheader: string}|null
     */
    public function sample(string $key): ?array
    {
        return $this->payload($key, MailTemplates::samples($key));
    }

    /**
     * @param  array<string, mixed>  $variables
     * @param  string|list<string>  $to
     */
    public function send(string $key, array $variables, string|array $to, bool $force = false): bool
    {
        $template = $this->template($key);
        $recipients = array_values(array_filter((array) $to, static fn ($email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));

        if ($template === null || $recipients === [] || (! $template->enabled && ! $force)) {
            return false;
        }

        if (! MailSettings::configured()) {
            Log::info('Alerta de e-mail ignorado: SMTP não configurado.', ['template' => $key]);

            return false;
        }

        $payload = $template->render($variables);
        MailSettings::apply();

        $message = new ThemedMail(
            heading: $payload['heading'],
            intro: $payload['intro'],
            details: $payload['details'],
            actionText: $payload['action_text'],
            actionUrl: $payload['action_url'],
            footnote: $payload['footnote'],
            preheader: $payload['preheader'],
            subject: $payload['subject'],
        );

        $first = array_shift($recipients);
        $pending = Mail::to($first);

        if ($recipients !== []) {
            $pending->bcc($recipients);
        }

        $pending->send($message);

        return true;
    }

    /**
     * Envia sem interromper o fluxo principal quando o servidor de e-mail
     * estiver indisponível (cadastro de usuário, baixa de fatura e afins).
     *
     * @param  array<string, mixed>  $variables
     * @param  string|list<string>  $to
     */
    public function sendQuietly(string $key, array $variables, string|array $to, bool $force = false): bool
    {
        try {
            return $this->send($key, $variables, $to, $force);
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar o modelo de e-mail.', [
                'template' => $key,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envia um alerta para os destinatários configurados no painel.
     *
     * @param  array<string, mixed>  $variables
     */
    public function sendToAlerts(string $key, array $variables): bool
    {
        $recipients = MailSettings::alertRecipients();

        if ($recipients === []) {
            Log::info('Alerta de e-mail ignorado: nenhum destinatário ativo.', ['template' => $key]);

            return false;
        }

        return $this->sendQuietly($key, $variables, $recipients);
    }
}
