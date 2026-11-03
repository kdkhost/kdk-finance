{{--
 ============================================================================
 SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 ============================================================================
 Desenvolvedor : George Marcelo de Oliveira Pimentel
 Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 ============================================================================
--}}
@extends('admin.layouts.app')

@section('title', 'Meu perfil')
@section('eyebrow', 'Conta')
@section('subtitle', 'Dados de acesso, identificação e segurança da sua conta.')

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <span class="avatar mx-auto" style="width:72px;height:72px;font-size:1.4rem;">{{ $user->initials() }}</span>
                    <h2 class="fs-5 fw-bold mt-3 mb-1">{{ $user->name }}</h2>
                    <div class="text-muted-soft" style="font-size:.82rem;">{{ $user->email }}</div>
                    <span class="badge text-bg-primary mt-2">{{ $user->roleLabel() }}</span>

                    <div class="surface-muted text-start mt-4 p-3" style="font-size:.8rem;">
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Situação</span>
                            <span class="fw-semibold">{{ $user->is_active ? 'Ativa' : 'Inativa' }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Criada em</span>
                            <span class="fw-semibold">{{ $user->created_at?->format('d/m/Y') }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted-soft">Última atualização</span>
                            <span class="fw-semibold">{{ $user->updated_at?->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">Dados pessoais</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Nome completo</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="150">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">E-mail de acesso</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="180">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar dados</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Alterar senha</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}" autocomplete="off">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label" for="current_password">Senha atual</label>
                                <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" required autocomplete="current-password">
                                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Nova senha</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required minlength="8" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Confirmar nova senha</label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="hint-banner mt-3">
                            <i class="bi bi-shield-lock"></i>
                            <div>Use no mínimo 8 caracteres. Após a alteração, a sessão atual continua ativa; outros dispositivos precisarão entrar novamente.</div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary" data-confirm="Confirmar a alteração da sua senha de acesso?" data-confirm-button="Alterar senha">
                                <i class="bi bi-key me-1"></i> Alterar senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
