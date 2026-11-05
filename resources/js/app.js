/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

import * as bootstrap from 'bootstrap';
import 'admin-lte';
import toastr from 'toastr';
import Swal from 'sweetalert2';
import './echo';
import './live';
import './dashboard';
import './invoice-form';
import './conversion-form';
import './editor';

window.bootstrap = bootstrap;
window.toastr = toastr;
window.Swal = Swal;

/* ---------------------------------------------------------------------------
 * Tema claro/escuro — persistência e evento único para gráficos
 * ------------------------------------------------------------------------- */
const THEME_KEY = 'kdk-theme';

const currentTheme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';

function paintTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.style.colorScheme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        button.setAttribute('aria-label', theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro');
    });
    document.dispatchEvent(new CustomEvent('finance:theme-changed', { detail: { theme } }));
}

paintTheme(localStorage.getItem(THEME_KEY) || 'light');

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    paintTheme(next);
});

/* ---------------------------------------------------------------------------
 * Toastr
 * ------------------------------------------------------------------------- */
toastr.options = {
    closeButton: true,
    newestOnTop: true,
    // Mensagens podem conter descrições digitadas pelo usuário: trata como texto.
    escapeHtml: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 5200,
    extendedTimeOut: 1600,
    preventDuplicates: true,
    showMethod: 'slideDown',
    hideMethod: 'fadeOut',
};

/* ---------------------------------------------------------------------------
 * Mensagens vindas do servidor
 * ------------------------------------------------------------------------- */
function showServerMessages() {
    const payload = document.getElementById('finance-flash');
    if (!payload) {
        return;
    }

    let data;
    try {
        data = JSON.parse(payload.textContent);
    } catch (error) {
        return;
    }

    const messages = [
        ['success', data.success, 'Tudo certo'],
        ['error', data.error, 'Não foi possível concluir'],
        ['warning', data.warning, 'Atenção'],
        ['info', data.info, 'Informação'],
    ];

    messages.forEach(([level, message, title]) => {
        if (message) {
            toastr[level](message, title);
        }
    });

    (data.errors || []).slice(0, 3).forEach((message) => {
        toastr.error(message, 'Verifique o formulário');
    });
}

showServerMessages();

/* ---------------------------------------------------------------------------
 * Confirmação com SweetAlert2 (nunca confirm() nativo)
 * ------------------------------------------------------------------------- */
function themeOptions() {
    const styles = getComputedStyle(document.documentElement);

    return {
        background: styles.getPropertyValue('--kdk-card').trim(),
        color: styles.getPropertyValue('--kdk-text').trim(),
        confirmButtonColor: styles.getPropertyValue('--kdk-teal').trim(),
        cancelButtonColor: styles.getPropertyValue('--kdk-muted').trim(),
    };
}

function askConfirmation({ title, text, confirmText, variant }) {
    return Swal.fire({
        title: title || 'Confirmar ação',
        text: text || 'Deseja continuar?',
        icon: variant === 'danger' ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: confirmText || 'Confirmar',
        cancelButtonText: 'Voltar',
        reverseButtons: true,
        focusCancel: variant === 'danger',
        ...themeOptions(),
    }).then((result) => result.isConfirmed);
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === '1') {
        return;
    }

    event.preventDefault();

    const confirmed = await askConfirmation({
        title: form.dataset.confirmTitle,
        text: form.dataset.confirm,
        confirmText: form.dataset.confirmButton,
        variant: form.dataset.confirmVariant,
    });

    if (confirmed) {
        form.dataset.confirmed = '1';
        form.submit();
    }
});

document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-confirm]');
    if (!link) {
        return;
    }

    event.preventDefault();

    const confirmed = await askConfirmation({
        title: link.dataset.confirmTitle,
        text: link.dataset.confirm,
        confirmText: link.dataset.confirmButton,
        variant: link.dataset.confirmVariant,
    });

    if (confirmed) {
        window.location.href = link.href;
    }
});

/* ---------------------------------------------------------------------------
 * Copiar para a área de transferência
 * ------------------------------------------------------------------------- */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) {
        return;
    }

    const value = button.dataset.copy || button.closest('.copy-field')?.querySelector('input')?.value || '';

    try {
        await navigator.clipboard.writeText(value);
        toastr.success('Valor copiado para a área de transferência.', 'Copiado');
    } catch (error) {
        toastr.error('O navegador bloqueou o acesso à área de transferência.', 'Não foi possível copiar');
    }
});

/* ---------------------------------------------------------------------------
 * Mostrar/ocultar senha (login e formulários)
 * ------------------------------------------------------------------------- */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle-password]');
    if (!toggle) {
        return;
    }

    const input = document.getElementById(toggle.dataset.togglePassword);
    if (!input) {
        return;
    }

    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.querySelector('i')?.classList.toggle('bi-eye', !reveal);
    toggle.querySelector('i')?.classList.toggle('bi-eye-slash', reveal);
});

/* ---------------------------------------------------------------------------
 * Cabeçalho fixo — sombra suave quando o conteúdo passa por baixo
 * ------------------------------------------------------------------------- */
const appHeader = document.querySelector('.app-header');

if (appHeader) {
    const appMain = document.querySelector('.app-main');
    let queued = false;

    const paintHeader = () => {
        const offset = Math.max(window.scrollY, appMain?.scrollTop || 0);
        appHeader.classList.toggle('is-stuck', offset > 4);
    };

    const onScroll = () => {
        if (queued) {
            return;
        }

        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            paintHeader();
        });
    };

    paintHeader();
    window.addEventListener('scroll', onScroll, { passive: true });
    appMain?.addEventListener('scroll', onScroll, { passive: true });
}

/* ---------------------------------------------------------------------------
 * Sidebar em árvore (até três níveis)
 * O ramo da página atual abre sempre; os demais respeitam a última escolha.
 * ------------------------------------------------------------------------- */
const SIDEBAR_TREE_KEY = 'kdk-sidebar-tree';

function readTreeState() {
    try {
        const stored = JSON.parse(localStorage.getItem(SIDEBAR_TREE_KEY) || '{}');
        return stored && typeof stored === 'object' ? stored : {};
    } catch (error) {
        return {};
    }
}

function writeTreeState(state) {
    try {
        localStorage.setItem(SIDEBAR_TREE_KEY, JSON.stringify(state));
    } catch (error) {
        // Armazenamento indisponível: a árvore continua funcionando na sessão.
    }
}

const treeState = readTreeState();

function setTreeOpen(item, open) {
    item.classList.toggle('is-open', open);
    const toggle = item.querySelector(':scope > [data-treeview-toggle]');
    if (toggle) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

document.querySelectorAll('.sidebar-menu .has-treeview').forEach((item) => {
    const key = item.dataset.treeItem;
    if (!key) {
        return;
    }

    const toggle = item.querySelector(':scope > [data-treeview-toggle]');
    const onActivePath = item.dataset.activePath === 'true';

    if (onActivePath) {
        setTreeOpen(item, true);
    } else if (Object.hasOwn(treeState, key)) {
        setTreeOpen(item, treeState[key] === true);
    } else if (toggle) {
        setTreeOpen(item, toggle.dataset.defaultOpen === 'true');
    }

    toggle?.addEventListener('click', () => {
        const open = !item.classList.contains('is-open');
        setTreeOpen(item, open);
        treeState[key] = open;
        writeTreeState(treeState);
    });
});

/* Ao navegar pelo histórico, o ramo da página atual volta a abrir. */
window.addEventListener('pageshow', () => {
    document.querySelectorAll('.sidebar-menu .has-treeview[data-active-path="true"]').forEach((item) => {
        setTreeOpen(item, true);
    });
});

/* O estado guardado é aplicado antes de liberar as animações da árvore:
 * sem isso o menu "pisca" abrindo e fechando a cada troca de página. */
requestAnimationFrame(() => {
    requestAnimationFrame(() => {
        document.documentElement.classList.add('kdk-ui-ready');
    });
});

/* ---------------------------------------------------------------------------
 * Teste de conexão com a OVHcloud (delegado: funciona após trocar de aba)
 *
 * A troca das abas de Configurações é registrada no próprio Blade da página,
 * antes do primeiro clique possível; por isso não depende do carregamento
 * deste módulo.
 * ------------------------------------------------------------------------- */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-ovh-test]');
    if (!button) {
        return;
    }

    const original = button.innerHTML;
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testando...';

    try {
        const response = await fetch(button.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (response.ok) {
            toastr.success(data.message, 'Conexão validada');
        } else {
            toastr.error(data.message || 'Falha ao validar as credenciais.', 'Falha na conexão');
        }
    } catch (error) {
        toastr.error('Não foi possível contatar o servidor.', 'Falha na conexão');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});

/* ---------------------------------------------------------------------------
 * Componentes Bootstrap
 * ------------------------------------------------------------------------- */
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
    new bootstrap.Tooltip(element);
});
