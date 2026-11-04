/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 *
 * Summernote global.
 *
 * Todo <textarea> do painel ganha o editor automaticamente, inclusive os que
 * forem inseridos depois (modais, seções dinâmicas). Campos que guardam
 * valores de máquina — token, lista de hosts/proxies, bloco Rótulo|Valor —
 * declaram data-editor="plain" e continuam como texto puro.
 */

import $ from './jquery';
import 'summernote/dist/summernote-bs5.min.js';
import 'summernote/dist/lang/summernote-pt-BR.min.js';

const OPT_OUT = '[data-editor="plain"], [data-editor="off"]';
const READY = 'summernoteReady';

function isEligible(element) {
    return element instanceof HTMLTextAreaElement
        && element.dataset[READY] !== '1'
        && !element.disabled
        && !element.readOnly
        && !element.matches(OPT_OUT)
        && !element.closest(OPT_OUT)
        && !element.closest('.note-editor');
}

function heightFor(element) {
    const rows = Number.parseInt(element.getAttribute('rows') || '3', 10) || 3;

    return Math.min(340, Math.max(150, rows * 36));
}

/* O Summernote devolve <p><br></p> ao apagar tudo. Gravar isso faria o campo
 * parecer preenchido nas telas de detalhe, então vazio volta a ser vazio. */
function normalize(html) {
    if (typeof html !== 'string') {
        return '';
    }

    const text = html.replace(/<[^>]+>/g, '').replace(/&nbsp;/gi, ' ').trim();

    return text === '' ? '' : html;
}

function sync(element, contents) {
    const value = normalize(contents);

    if (element.value === value) {
        return;
    }

    element.value = value;
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

function enhance(element) {
    if (!isEligible(element)) {
        return;
    }

    const height = heightFor(element);

    $(element).summernote({
        lang: 'pt-BR',
        placeholder: element.getAttribute('placeholder') || '',
        height,
        minHeight: height,
        dialogsInBody: true,
        disableDragAndDrop: true,
        /* O tema usa Bootstrap 5 via ES modules, sem os plug-ins jQuery do
         * pacote bundle. Sem esta opção o Summernote chama $.fn.tooltip e
         * aborta a criação da barra de ferramentas. */
        tooltip: false,
        toolbar: [
            ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']],
            ['misc', ['undo', 'redo', 'codeview']],
        ],
        callbacks: {
            onInit: function () {
                element.dataset[READY] = '1';
            },
            onChange: function (contents) {
                sync(element, contents);
            },
        },
    });

    const editor = element.nextElementSibling;

    if (editor) {
        editor.classList.add('kdk-summernote');

        if (element.classList.contains('is-invalid')) {
            editor.classList.add('is-invalid');
        }
    }
}

function enhanceAll(root = document) {
    root.querySelectorAll('textarea').forEach(enhance);
}

/* Mantém o <textarea> sincronizado mesmo quando o formulário é enviado sem
 * passar pelo onChange (ex.: restauração de rascunho do navegador). */
document.addEventListener('submit', () => {
    document.querySelectorAll(`textarea[data-${READY.replace(/[A-Z]/g, (m) => '-' + m.toLowerCase())}="1"]`).forEach((element) => {
        const code = $(element).summernote('code');

        if (typeof code === 'string') {
            element.value = normalize(code);
        }
    });
}, true);

enhanceAll();

new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) {
                return;
            }

            if (node.matches('textarea')) {
                enhance(node);
            }

            node.querySelectorAll?.('textarea').forEach(enhance);
        });
    });
}).observe(document.body, { childList: true, subtree: true });

window.KdkEditor = {
    init: enhance,
    initAll: enhanceAll,
};
