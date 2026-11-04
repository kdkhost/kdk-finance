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

@section('title', 'Usuários e permissões')
@section('eyebrow', 'Acessos')
@section('subtitle', 'Controle de perfis, ativação de contas e níveis de permissão do painel financeiro.')

@section('actions')
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#user-create">
        <i class="bi bi-person-plus me-1"></i> Novo usuário
    </button>
@endsection

@section('content')
    @php
        $roleBadge = [
            \App\Models\User::ROLE_DEVELOPER => 'text-bg-primary',
            \App\Models\User::ROLE_FINANCIAL => 'text-bg-success',
            \App\Models\User::ROLE_VIEWER => 'text-bg-secondary',
        ];
        $activeDevelopers = $users->where('role', \App\Models\User::ROLE_DEVELOPER)->where('is_active', true)->count();
    @endphp

    <div class="row g-3 mb-3">
        @foreach ($roles as $roleKey => $roleLabel)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="page-eyebrow">{{ $roleLabel }}</div>
                                <div class="fs-3 fw-bold tabular">{{ $users->where('role', $roleKey)->count() }}</div>
                            </div>
                            <span class="badge {{ $roleBadge[$roleKey] ?? 'text-bg-secondary' }}">{{ \Illuminate\Support\Str::of($roleKey)->replace('_', ' ') }}</span>
                        </div>
                        <div class="text-muted-soft mt-2" style="font-size:.76rem;">
                            @if ($roleKey === \App\Models\User::ROLE_DEVELOPER)
                                Acesso total: credenciais OVH, regras, usuários e financeiro.
                            @elseif ($roleKey === \App\Models\User::ROLE_FINANCIAL)
                                Câmbio, tributos, faturas e conciliação OVH.
                            @else
                                Somente leitura de painel, faturas e notificações.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Contas cadastradas</span>
            <span class="text-muted-soft" style="font-size:.76rem;">{{ $activeDevelopers }} desenvolvedor(es) ativo(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 users-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th class="d-none d-md-table-cell">Perfil</th>
                        <th class="d-none d-md-table-cell">Situação</th>
                        <th class="d-none d-md-table-cell">Criado em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="user-name-cell">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar">{{ $user->initials() }}</span>
                                    <div>
                                        <span class="cell-title d-block">{{ $user->name }}</span>
                                        <span class="cell-sub">{{ $user->email }}</span>
                                        <span class="user-mobile-meta d-md-none">
                                            <span class="badge {{ $roleBadge[$user->role] ?? 'text-bg-secondary' }}">{{ $user->roleLabel() }}</span>
                                            <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-danger' }}">{{ $user->is_active ? 'Ativo' : 'Inativo' }}</span>
                                            <span>{{ $user->created_at?->format('d/m/Y') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge {{ $roleBadge[$user->role] ?? 'text-bg-secondary' }}">{{ $user->roleLabel() }}</span>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">Ativo</span>
                                @else
                                    <span class="badge text-bg-danger">Inativo</span>
                                @endif
                                @if ($user->id === auth()->id())
                                    <span class="badge text-bg-light border ms-1">Você</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell tabular text-muted-soft">{{ $user->created_at?->format('d/m/Y') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#user-edit-{{ $user->id }}">
                                    <i class="bi bi-pencil me-1"></i> Editar
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal de criação --}}
    <div class="modal fade" id="user-create" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('users.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i> Novo usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="create_name">Nome completo</label>
                            <input type="text" class="form-control" id="create_name" name="name" value="{{ old('name') }}" required maxlength="150">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="create_email">E-mail</label>
                            <input type="email" class="form-control" id="create_email" name="email" value="{{ old('email') }}" required maxlength="180">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="create_role">Perfil</label>
                            <select class="form-select" id="create_role" name="role" required>
                                @foreach ($roles as $roleKey => $roleLabel)
                                    <option value="{{ $roleKey }}" @selected(old('role') === $roleKey)>{{ $roleLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="create_password">Senha inicial</label>
                            <input type="password" class="form-control" id="create_password" name="password" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Mínimo de 8 caracteres. O usuário pode alterar depois em Meu perfil.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Criar usuário</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modais de edição --}}
    @foreach ($users as $user)
        <div class="modal fade" id="user-edit-{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('users.update', $user) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i> Editar usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="edit_name_{{ $user->id }}">Nome completo</label>
                                <input type="text" class="form-control" id="edit_name_{{ $user->id }}" name="name" value="{{ $user->name }}" required maxlength="150">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="edit_email_{{ $user->id }}">E-mail</label>
                                <input type="email" class="form-control" id="edit_email_{{ $user->id }}" name="email" value="{{ $user->email }}" required maxlength="180">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="edit_role_{{ $user->id }}">Perfil</label>
                                <select class="form-select" id="edit_role_{{ $user->id }}" name="role" required @disabled($user->id === auth()->id())>
                                    @foreach ($roles as $roleKey => $roleLabel)
                                        <option value="{{ $roleKey }}" @selected($user->role === $roleKey)>{{ $roleLabel }}</option>
                                    @endforeach
                                </select>
                                @if ($user->id === auth()->id())
                                    <input type="hidden" name="role" value="{{ $user->role }}">
                                    <div class="form-text">Você não pode alterar o próprio perfil.</div>
                                @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="edit_password_{{ $user->id }}">Nova senha (opcional)</label>
                                <input type="password" class="form-control" id="edit_password_{{ $user->id }}" name="password" minlength="8" autocomplete="new-password" placeholder="Deixe em branco para manter">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="edit_active_{{ $user->id }}" name="is_active" value="1" @checked($user->is_active) @disabled($user->id === auth()->id())>
                                    <label class="form-check-label" for="edit_active_{{ $user->id }}" style="font-size:.84rem;">Conta ativa</label>
                                </div>
                                @if ($user->id === auth()->id())
                                    <input type="hidden" name="is_active" value="1">
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
