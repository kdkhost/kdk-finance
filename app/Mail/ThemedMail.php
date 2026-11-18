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

namespace App\Mail;

use App\Support\Settings\MailSettings;
use App\Support\Settings\MailTheme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail transacional com o tema editável no painel.
 *
 * O conteúdo é montado em blocos (título, introdução, detalhes, ação e
 * rodapé) para que qualquer notificação use o mesmo layout responsivo.
 */
class ThemedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $heading,
        public string $intro = '',
        public array $details = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public string $footnote = '',
        public string $preheader = '',
        ?string $subject = null,
    ) {
        if ($subject !== null && $subject !== '') {
            $this->subject($subject);
        }
    }

    public function build(): self
    {
        if (MailSettings::replyToAddress() !== '') {
            $this->replyTo(
                MailSettings::replyToAddress(),
                MailSettings::replyToName() !== '' ? MailSettings::replyToName() : MailSettings::fromName()
            );
        }

        return $this->view('emails.themed', [
            'theme' => MailTheme::all(),
            'heading' => $this->heading,
            'intro' => $this->intro,
            'details' => $this->details,
            'actionText' => $this->actionText,
            'actionUrl' => $this->actionUrl,
            'footnote' => $this->footnote,
            'preheader' => $this->preheader !== '' ? $this->preheader : $this->intro,
        ]);
    }
}
