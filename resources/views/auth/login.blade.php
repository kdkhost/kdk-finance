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
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="author" content="{{ config('finance.developer.name') }} — {{ config('finance.developer.company') }}">
    <meta name="copyright" content="{{ config('finance.developer.company') }} (CNPJ {{ config('finance.developer.cnpj') }})">
    <meta name="theme-color" content="{{ config('finance.pwa.theme_color') }}">
    <title>Acesso ao painel · {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    @include('partials.theme-boot')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.flash')

    <main class="auth-page">
        <section class="auth-aside">
            <a href="{{ route('login') }}" class="d-flex align-items-center gap-2 text-decoration-none" style="position:relative;z-index:1;">
                <span class="brand-mark">KDK</span>
                <span class="brand-text" style="color:#f1f7ff;">
                    KDK Finance
                    <small>Multimoedas &amp; infraestrutura</small>
                </span>
            </a>

            <div class="auth-copy">
                <div class="page-eyebrow" style="color:#6ef2c9;">Conciliação internacional</div>
                <h1>Faturas em EUR e USD convertidas com precisão.</h1>
                <p>Cotação em tempo real, spread, IOF e ICMS discriminados por fatura — com importação automática das cobranças da OVHcloud.</p>

                <div class="auth-features">
                    <div class="auth-feature">
                        <span class="icon"><i class="bi bi-graph-up-arrow"></i></span>
                        <div><strong>Cotação a cada 15 minutos</strong><br>Cache inteligente com fallback para a última cotação conhecida.</div>
                    </div>
                    <div class="auth-feature">
                        <span class="icon"><i class="bi bi-shield-lock"></i></span>
                        <div><strong>Credenciais criptografadas</strong><br>Chaves da OVHcloud gravadas no banco com AES-256 e auditadas.</div>
                    </div>
                    <div class="auth-feature">
                        <span class="icon"><i class="bi bi-bell"></i></span>
                        <div><strong>Alertas de variação cambial</strong><br>Notificação por e-mail, banco e tempo real quando o total muda.</div>
                    </div>
                </div>
            </div>

            <div style="position:relative;z-index:1;font-size:.72rem;color:#8fa9c3;">
                {{ config('finance.developer.company') }} · CNPJ {{ config('finance.developer.cnpj') }}<br>
                Desenvolvido por {{ config('finance.developer.name') }}
            </div>
        </section>

        <section class="auth-main">
            <button type="button" class="btn-icon auth-theme-toggle" data-theme-toggle title="Alternar tema" aria-label="Alternar tema">
                <i class="bi bi-moon-stars-fill"></i>
            </button>

            <div class="auth-card">
                <div class="card">
                    <div class="card-body p-4">
                        <div class="page-eyebrow">Acesso restrito</div>
                        <h1 class="fs-4 fw-bold mb-1" style="letter-spacing:-.03em;">Entrar no painel</h1>
                        <p class="text-muted-soft mb-4" style="font-size:.84rem;">Use as credenciais fornecidas pelo administrador do sistema.</p>

                        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="email">E-mail</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email"
                                           name="email"
                                           value="{{ old('email') }}"
                                           placeholder="seuemail@kdkhost.com.br"
                                           required
                                           autofocus
                                           autocomplete="username">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           id="password"
                                           name="password"
                                           placeholder="••••••••"
                                           required
                                           autocomplete="current-password">
                                    <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Mostrar ou ocultar a senha">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                                <label class="form-check-label" for="remember" style="font-size:.82rem;">Manter conectado neste dispositivo</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="auth-footer">
                    {{ config('finance.developer.company') }} · Desenvolvido por {{ config('finance.developer.name') }}<br>
                    v1.10.0 · Laravel {{ \Illuminate\Foundation\Application::VERSION }}
                </div>
            </div>
        </section>
    </main>
</body>
</html>
