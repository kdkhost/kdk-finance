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
@stack('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    // Service Worker — instalação no Windows (Edge/Chrome) e Android.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' }).catch(function () {
                // Falha silenciosa: PWA é um recurso progressivo.
            });
        });
    }

    // Handler global AJAX + SweetAlert2 2FA para sincronização do painel IDrive e2 (S3)
    (function () {
        const executeIDriveSync = async (form, otpCode = '') => {
            const button = form.querySelector('[data-idrivee2-sync-btn]') || form.querySelector('button[type="submit"]');
            const originalHtml = button ? button.innerHTML : '';
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Conectando ao S3...';
            }

            try {
                const body = new URLSearchParams();
                if (otpCode) {
                    body.set('otp_code', otpCode);
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body.toString(),
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (data && data.requires_2fa && !data.ok) {
                    if (window.Swal) {
                        const result = await window.Swal.fire({
                            title: 'Autenticação de 2 Fatores (2FA)',
                            html: '<div class="text-start" style="font-size:.9rem;">' +
                                  '<p class="mb-2">O console <strong>IDrive e2 (S3)</strong> solicitou o código de verificação em duas etapas.</p>' +
                                  '<p class="mb-0 text-muted">Digite o código de 6 dígitos gerado no seu aplicativo autenticador para sincronizar os serviços e faturas:</p>' +
                                  '</div>',
                            input: 'text',
                            inputPlaceholder: '000000',
                            inputAttributes: {
                                maxlength: '8',
                                autocapitalize: 'off',
                                autocorrect: 'off',
                                autocomplete: 'one-time-code',
                                style: 'text-align:center;font-size:1.35rem;letter-spacing:.25em;font-family:monospace;'
                            },
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Verificar e Sincronizar',
                            cancelButtonText: 'Cancelar',
                            reverseButtons: true,
                            focusConfirm: false,
                            inputValidator: (value) => {
                                if (!value || value.trim().length < 6) {
                                    return 'Informe o código de 6 dígitos do aplicativo autenticador.';
                                }
                                return null;
                            }
                        });

                        if (result.isConfirmed && result.value) {
                            return await executeIDriveSync(form, result.value.trim());
                        }
                    } else if (window.toastr) {
                        window.toastr.warning(data.message || 'Informe o código 2FA.', 'Autenticação 2FA necessária');
                    }
                    return;
                }

                if (data && data.redirect && !data.ok) {
                    if (window.toastr) {
                        window.toastr.warning(data.message || 'Configure as credenciais do IDrive e2.', 'Atenção');
                    }
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 900);
                    return;
                }

                if (response.ok && data && data.ok) {
                    if (window.toastr) {
                        window.toastr.success(data.message || 'Sincronização IDrive e2 concluída com sucesso.', 'IDrive e2 (S3)');
                    }
                    if (window.location.pathname.startsWith('/s3')) {
                        setTimeout(() => window.location.reload(), 800);
                    }
                } else {
                    if (window.toastr) {
                        window.toastr.error((data && data.message) || 'Não foi possível sincronizar o painel IDrive e2.', 'Falha na sincronização');
                    }
                }
            } catch (error) {
                if (window.toastr) {
                    window.toastr.error('Não foi possível contatar o servidor para sincronizar o IDrive e2.', 'Erro de conexão');
                }
            } finally {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            }
        };

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-idrivee2-sync-form')) {
                return;
            }
            if (event.defaultPrevented) {
                return;
            }
            event.preventDefault();
            const otpInput = form.querySelector('input[name="otp_code"]');
            executeIDriveSync(form, otpInput ? otpInput.value : '');
        });
    })();
</script>
</body>
</html>
