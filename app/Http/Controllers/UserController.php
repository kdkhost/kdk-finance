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

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderBy('role')->orderBy('name')->get(),
            'roles' => User::roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'password' => ['required', 'string', 'max:100', Password::defaults()],
        ]);

        User::query()->create($data + ['is_active' => true]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuário '.$data['email'].' criado com sucesso.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::roles()))],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:100', Password::defaults()],
        ]);

        $isSelf = $user->id === $request->user()->id;
        $willBeDeveloper = $data['role'] === User::ROLE_DEVELOPER && $request->boolean('is_active');

        if ($isSelf && ! $willBeDeveloper) {
            return back()->with('warning', 'Você não pode remover o próprio acesso de desenvolvedor.');
        }

        if (! $willBeDeveloper && $user->isDeveloperAdmin()) {
            $otherDevelopers = User::query()
                ->where('role', User::ROLE_DEVELOPER)
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->count();

            if ($otherDevelopers === 0) {
                return back()->with('warning', 'É necessário manter ao menos um desenvolvedor ativo.');
            }
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $isSelf ? true : $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', 'Usuário atualizado.');
    }
}
