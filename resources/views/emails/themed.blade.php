{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : Marcelo Brad - RJ
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
 Tema de e-mail responsivo e editável (tabelas + estilos inline).
--}}
@php
    $primary = $theme['theme_primary'];
    $accent = $theme['theme_accent'];
    $background = $theme['theme_background'];
    $text = $theme['theme_text'];
    $muted = $theme['theme_muted'];
    $brand = $theme['theme_brand'];
    $initials = \Illuminate\Support\Str::of($brand)->squish()->explode(' ')->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $initials = $initials !== '' ? $initials : 'K';
    $supportUrl = $theme['theme_support_url'];
    $supportEmail = $theme['theme_support_email'];
    $year = now()->year;
@endphp
<!doctype html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $heading }}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .hero-title { font-size: 22px !important; line-height: 1.3 !important; }
            .btn a { display: block !important; text-align: center !important; }
            .detail-label, .detail-value { display: block !important; width: 100% !important; text-align: left !important; }
            .detail-label { border-radius: 10px 10px 0 0 !important; padding-bottom: 2px !important; }
            .detail-value { border-radius: 0 0 10px 10px !important; padding-top: 2px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:{{ $background }};">
    <div style="display:none;font-size:1px;color:{{ $background }};line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $background }};">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;">

                    {{-- Cabeçalho --}}
                    <tr>
                        <td style="padding:0 6px 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    @if ($theme['theme_logo_enabled'] === '1')
                                        <td width="52" valign="middle" style="width:52px;">
                                            <div style="width:44px;height:44px;border-radius:12px;background-color:{{ $primary }};color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:700;line-height:44px;text-align:center;letter-spacing:.5px;">
                                                {{ $initials }}
                                            </div>
                                        </td>
                                    @endif
                                    <td valign="middle" style="padding-left:{{ $theme['theme_logo_enabled'] === '1' ? '12px' : '0' }};">
                                        <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:{{ $accent }};letter-spacing:-.2px;">
                                            {{ $brand }}
                                        </div>
                                        <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:600;color:{{ $muted }};letter-spacing:.12em;text-transform:uppercase;padding-top:3px;">
                                            Painel financeiro
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Cartão principal --}}
                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:5px;background-color:{{ $primary }};font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="px" style="padding:32px 36px 30px;">
                                        <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:{{ $primary }};letter-spacing:.14em;text-transform:uppercase;padding-bottom:10px;">
                                            {{ $brand }}
                                        </div>
                                        <h1 class="hero-title" style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:26px;line-height:1.25;font-weight:700;color:{{ $accent }};letter-spacing:-.4px;">
                                            {{ $heading }}
                                        </h1>

                                        @if ($intro !== '')
                                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:{{ $text }};padding-bottom:18px;">
                                                {!! nl2br(e($intro)) !!}
                                            </div>
                                        @endif

                                        @if (! empty($details))
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;border-spacing:0 6px;margin:4px 0 20px;">
                                                @foreach ($details as $label => $value)
                                                    <tr>
                                                        <td class="detail-label" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:{{ $muted }};letter-spacing:.08em;text-transform:uppercase;background-color:#f8fafc;border-radius:10px 0 0 10px;padding:11px 14px;">
                                                            {{ $label }}
                                                        </td>
                                                        <td class="detail-value" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:{{ $text }};text-align:right;background-color:#f8fafc;border-radius:0 10px 10px 0;padding:11px 14px;">
                                                            {{ $value }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        @endif

                                        @if ($actionText && $actionUrl)
                                            <table role="presentation" class="btn" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 4px;">
                                                <tr>
                                                    <td style="background-color:{{ $primary }};border-radius:10px;">
                                                        <a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;padding:13px 24px;border-radius:10px;">
                                                            {{ $actionText }}
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif

                                        @if ($footnote !== '')
                                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:{{ $muted }};padding-top:18px;border-top:1px solid #eef2f7;margin-top:20px;">
                                                {!! nl2br(e($footnote)) !!}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Rodapé --}}
                    <tr>
                        <td style="padding:22px 10px 6px;text-align:center;">
                            @if (! empty($theme['theme_footer']))
                                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:{{ $muted }};padding-bottom:8px;">
                                    {!! nl2br(e($theme['theme_footer'])) !!}
                                </div>
                            @endif

                            @if (! empty($theme['theme_signature']))
                                <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:{{ $text }};padding-bottom:8px;">
                                    {{ $theme['theme_signature'] }}
                                </div>
                            @endif

                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.7;color:{{ $muted }};">
                                @if ($supportEmail !== '')
                                    <a href="mailto:{{ $supportEmail }}" style="color:{{ $primary }};text-decoration:none;">{{ $supportEmail }}</a>
                                @endif
                                @if ($supportEmail !== '' && $supportUrl !== '')
                                    <span style="color:{{ $muted }};">&nbsp;·&nbsp;</span>
                                @endif
                                @if ($supportUrl !== '')
                                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener" style="color:{{ $primary }};text-decoration:none;">{{ parse_url($supportUrl, PHP_URL_HOST) ?: $supportUrl }}</a>
                                @endif
                            </div>

                            @if (! empty($theme['theme_company']))
                                <div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:1.6;color:{{ $muted }};padding-top:6px;">
                                    {{ $theme['theme_company'] }}
                                </div>
                            @endif

                            <div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:{{ $muted }};padding-top:6px;">
                                © {{ $year }} {{ $brand }}. Todos os direitos reservados.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
