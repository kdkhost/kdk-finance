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

@section('title', $template->name ?: 'Modelo de e-mail')
@section('eyebrow', 'Modelos de e-mail')
@section('subtitle', $template->description ?: 'Edite o conteúdo enviado por este evento.')

@section('actions')
    <a href="{{ route('email-templates.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('email-templates.update', $template) }}" id="template-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="0">

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Conteúdo</span>
                        @if ($template->enabled)
                            <span class="badge-status badge-status--paid">Ativo</span>
                        @else
                            <span class="badge-status badge-status--canceled">Desativado</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="subject">Assunto</label>
                                <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject', $template->subject) }}" required maxlength="200">
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="heading">Título interno</label>
                                <input type="text" class="form-control @error('heading') is-invalid @enderror" id="heading" name="heading" value="{{ old('heading', $template->heading) }}" required maxlength="150">
                                @error('heading')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="intro">Texto de abertura</label>
                                <textarea class="form-control @error('intro') is-invalid @enderror" id="intro" name="intro" rows="4" required maxlength="1500">{{ old('intro', $template->intro) }}</textarea>
                                @error('intro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="details">Bloco de detalhes</label>
                                <textarea class="form-control mono @error('details') is-invalid @enderror" id="details" name="details" rows="5" maxlength="2000" placeholder="Fatura|{{ \App\Support\Settings\MailTemplates::placeholder('fatura') }}">{{ old('details', $detailsText) }}</textarea>
                                @error('details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Uma linha por item, no formato <span class="mono">Rótulo|Valor</span>. Linhas sem barra vertical são ignoradas.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="action_text">Texto do botão</label>
                                <input type="text" class="form-control" id="action_text" name="action_text" value="{{ old('action_text', $template->action_text) }}" maxlength="80" placeholder="Abrir fatura">
                                <div class="form-text">Deixe vazio para esconder o botão.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="action_url">Link do botão</label>
                                <input type="text" class="form-control mono" id="action_url" name="action_url" value="{{ old('action_url', $template->action_url) }}" maxlength="255" placeholder="{{ \App\Support\Settings\MailTemplates::placeholder('link') }}">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="footnote">Assinatura / rodapé</label>
                                <textarea class="form-control" id="footnote" name="footnote" rows="2" maxlength="500">{{ old('footnote', $template->footnote) }}</textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" @checked(old('enabled', $template->enabled))>
                                    <label class="form-check-label" for="enabled" style="font-size:.86rem;">Modelo ativo — os alertas podem enviar esta mensagem</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Salvar modelo
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-restore>
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar padrão
                    </button>
                </div>
            </form>

            <div class="card mt-3">
                <div class="card-header">Variáveis disponíveis</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @foreach ($variables as $variable)
                        <button type="button" class="btn btn-outline-secondary btn-sm mono js-insert-variable" data-variable="{{ \App\Support\Settings\MailTemplates::placeholder($variable) }}">{{ \App\Support\Settings\MailTemplates::placeholder($variable) }}</button>
                    @endforeach
                </div>
                <div class="card-body border-top text-muted-soft" style="font-size:.78rem;">
                    Clique em uma variável para copiá-la; cole no campo desejado. Placeholders desconhecidos aparecem como estão, sem gerar erro.
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Prévia com dados de exemplo</span>
                    <a href="{{ route('email-templates.preview', $template) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div class="card-body p-2">
                    <iframe src="{{ route('email-templates.preview', $template) }}?v={{ now()->timestamp }}" title="Prévia do modelo" loading="lazy" style="width:100%;height:520px;border:1px solid var(--bs-border-color);border-radius:10px;background:#fff;"></iframe>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Enviar teste</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('email-templates.test', $template) }}">
                        @csrf
                        <label class="form-label" for="test_email">Enviar este modelo para</label>
                        <input type="email" class="form-control mb-2" id="test_email" name="test_email" value="{{ old('test_email', auth()->user()->email) }}" required>
                        <button type="submit" class="btn btn-outline-secondary w-100" @disabled(! $mailConfigured)>
                            <i class="bi bi-send me-1"></i> Enviar modelo de teste
                        </button>
                        @unless ($mailConfigured)
                            <div class="form-text text-warning mt-2">Configure o SMTP na aba E-mail para liberar o envio.</div>
                        @endunless
                    </form>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('email-templates.restore', $template) }}" id="restore-form" data-confirm="Restaurar o conteúdo padrão deste modelo? As alterações atuais serão descartadas." data-confirm-button="Restaurar">
        @csrf
    </form>

    @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            document.querySelectorAll('.js-insert-variable').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const value = button.dataset.variable;

                    try {
                        await navigator.clipboard.writeText(value);
                        window.toastr.success(value + ' copiado.', 'Variável');
                    } catch (error) {
                        window.toastr.info('Copie manualmente: ' + value, 'Variável');
                    }
                });
            });

            document.querySelector('[data-restore]')?.addEventListener('click', function (event) {
                event.preventDefault();
                document.getElementById('restore-form')?.requestSubmit();
            });
        </script>
    @endpush
@endsection
