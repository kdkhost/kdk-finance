/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const appKey = import.meta.env.VITE_REVERB_APP_KEY;
// Só conecta quando o servidor realmente usa Reverb. Em hospedagem
// compartilhada o broadcast fica em "log" e o painel atualiza por polling.
const runtimeBroadcaster = document.body?.dataset.broadcast;
const enabled = Boolean(appKey) && runtimeBroadcaster === 'reverb';

if (enabled) {
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo.connector.pusher.connection.bind('connected', () => {
        document.dispatchEvent(new CustomEvent('finance:live-connected'));
    });

    const userId = window.__financeUser?.id;

    if (userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification) => {
                document.dispatchEvent(new CustomEvent('finance:notification', { detail: notification }));
            });
    }

    window.Echo.private('invoices')
        .listen('.invoice.updated', (payload) => {
            document.dispatchEvent(new CustomEvent('finance:invoice-updated', { detail: payload }));
        });
}

window.__financeLive = enabled;

export { enabled as reverbEnabled };
