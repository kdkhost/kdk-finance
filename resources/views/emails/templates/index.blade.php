{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : Marcelo Brad - RJ
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
@extends('admin.layouts.app')

@section('title', 'Modelos de e-mail')
@section('eyebrow', 'Configurações · E-mail')
@section('subtitle', 'Textos dos avisos automáticos do sistema, enviados com o tema visual configurado.')

@section('actions')
    <a href="{{ route('settings.index', ['tab' => 'mail']) }}" class="btn btn-outline-secondary">
        <i class="bi bi-sliders2 me-1"></i> SMTP e tema
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="page-eyebrow">Envio automático</div>
                    <p class="text-muted-soft mb-0" style="font-size:.86rem;">
                        Cada modelo é disparado por um evento real do sistema. Edite os textos sem tocar em código;
                        os alertas usam o SMTP e o tema salvos na aba E-mail.
                    </p>
                </div>
                <div class="text-end">
                    @if ($mailConfigured)
                        <span class="badge text-bg-success"><i class="bi bi-send-check me-1"></i> SMTP pronto</span>
                    @else
                        <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i> SMTP não configurado</span>
                    @endif
                    <div class="cell-sub d-block mt-1">
                        @if ($alertRecipients !== [])
                            Alertas para {{ implode(', ', array_slice($alertRecipients, 0, 2)) }}{{ count($alertRecipients) > 2 ? ' +'.(count($alertRecipients) - 2) : '' }}
                        @else
                            Nenhum destinatário de alerta definido
                        @endif
                    </div>
                </div>
            </div>

            @unless ($mailConfigured)
                <div class="hint-banner mt-3" style="background: var(--kdk-amber-soft, #fff7e6); border-color: color-mix(in srgb, #b45309 30%, transparent);">
                    <i class="bi bi-info-circle"></i>
                    <div>Os modelos ficam salvos mesmo sem SMTP, mas nada é enviado até o servidor e o remetente serem preenchidos na aba E-mail.</div>
                </div>
            @endunless
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Modelos transacionais</div>
                <div class="text-muted-soft" style="font-size:.76rem;">{{ $templates->count() }} modelos · placeholders entre chaves duplas</div>
            </div>
            <span class="badge text-bg-light border">{{ $templates->where('enabled', true)->count() }} ativos</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Modelo</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>
                                <a href="{{ route('email-templates.edit', $template) }}" class="cell-title d-block">{{ $template->name ?: ucfirst(str_replace('_', ' ', $template->key)) }}</a>
                                <span class="cell-sub d-block" style="max-width:680px;">{{ $template->description ?? '' }}</span>
                                <span class="cell-sub mono d-block" style="font-size:.72rem;">{{ $template->key }} · {{ count($template->variables()) }} variáveis</span>
                            </td>
                            <td>
                                @if ($template->enabled)
                                    <span class="badge-status badge-status--paid">Ativo</span>
                                @else
                                    <span class="badge-status badge-status--canceled">Desativado</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('email-templates.preview', $template) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Prévia">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="POST" action="{{ route('email-templates.toggle', $template) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" title="{{ $template->enabled ? 'Desativar' : 'Ativar' }}">
                                            <i class="bi {{ $template->enabled ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('email-templates.edit', $template) }}" class="btn btn-ghost btn-sm" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="hint-banner mt-3">
        <i class="bi bi-people"></i>
        <div>
            Os alertas automáticos vão para os destinatários definidos em <strong>Configurações → E-mail → Alertas</strong>.
            Sem endereço preenchido, o sistema usa o e-mail de todo desenvolvedor e gestor financeiro ativo.
        </div>
    </div>
@endsection
