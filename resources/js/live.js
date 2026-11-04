/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 *
 * Atualização em segundo plano de todas as páginas do painel.
 *
 * - Relógio em tempo real no fuso de São Paulo (America/Sao_Paulo).
 * - Consulta periódica a /painel/ao-vivo e escrita apenas dos textos que
 *   mudaram, sem recarregar, redesenhar ou piscar nenhum elemento.
 */

const TIME_ZONE = 'America/Sao_Paulo';
const REFRESH_MS = 60000;

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const decimal = (value, digits = 2) => Number(value || 0).toLocaleString('pt-BR', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
});

const clockTime = new Intl.DateTimeFormat('pt-BR', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
});

const clockDate = new Intl.DateTimeFormat('pt-BR', {
    timeZone: TIME_ZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const shortTime = new Intl.DateTimeFormat('pt-BR', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

const clockElement = document.querySelector('[data-live-clock-time]');
const dateElement = document.querySelector('[data-live-clock-date]');
const indicatorLabel = document.querySelector('[data-live-label]');
const liveUrl = document.body?.dataset.liveUrl;

let serverOffset = 0;

function setText(element, value) {
    if (element && element.textContent !== String(value)) {
        element.textContent = String(value);
    }
}

function paintClock() {
    const now = new Date(Date.now() + serverOffset);

    setText(clockElement, clockTime.format(now));
    setText(dateElement, clockDate.format(now));

    if (clockElement) {
        clockElement.setAttribute('datetime', now.toISOString());
    }
}

paintClock();
window.setInterval(paintClock, 1000);

function paintRates(rates) {
    Object.entries(rates || {}).forEach(([currency, rate]) => {
        document.querySelectorAll(`[data-live="rate-${currency}"]`).forEach((element) => {
            setText(element, decimal(rate.rate, 4));
        });

        document.querySelectorAll(`[data-live="rate-${currency}-pct"]`).forEach((element) => {
            setText(element, `${decimal(Math.abs(rate.pct_change || 0), 2)}%`);
        });

        document.querySelectorAll(`[data-live="rate-${currency}-change"]`).forEach((element) => {
            const up = Number(rate.pct_change || 0) >= 0;
            element.classList.toggle('is-up', up);
            element.classList.toggle('is-down', !up);

            const icon = element.querySelector('i');
            if (icon) {
                icon.className = up ? 'bi bi-arrow-up-right' : 'bi bi-arrow-down-right';
            }
        });
    });

    const updated = new Date();
    const latest = Object.values(rates || {}).find((rate) => rate.updated_at);
    document.querySelectorAll('[data-live="rates-at"]').forEach((element) => {
        setText(element, shortTime.format(latest ? new Date(latest.updated_at) : updated));
    });

    document.querySelectorAll('[data-live="rates-stale"]').forEach((element) => {
        const stale = Object.values(rates || {}).some((rate) => rate.stale);
        element.hidden = !stale;
    });
}

function paintTotals(totals) {
    if (!totals) {
        return;
    }

    const currency = {
        'pending-total': totals.pending_total,
        'overdue-total': totals.overdue_total,
        'paid-month-total': totals.paid_month_total,
        'taxes-pending': totals.taxes_pending,
    };

    Object.entries(currency).forEach(([key, value]) => {
        if (value === undefined) {
            return;
        }

        document.querySelectorAll(`[data-live="${key}"]`).forEach((element) => {
            setText(element, money.format(value));
        });
    });

    const counters = {
        'pending-count': totals.pending_count,
        'paid-month-count': totals.paid_month_count,
        'overdue-count': totals.overdue_count,
    };

    Object.entries(counters).forEach(([key, value]) => {
        if (value === undefined) {
            return;
        }

        document.querySelectorAll(`[data-live="${key}"]`).forEach((element) => {
            setText(element, value);
        });
    });
}

function paintPreview(pair) {
    if (!pair) {
        return;
    }

    document.querySelectorAll('[data-live="conversion-rate"]').forEach((element) => {
        setText(element, decimal(pair.rate, 6));
    });

    document.querySelectorAll('[data-live="conversion-inverse"]').forEach((element) => {
        setText(element, `1 USD = ${decimal(pair.inverse, 6)} EUR`);
    });

    document.querySelectorAll('[data-live="conversion-at"]').forEach((element) => {
        setText(element, shortTime.format(new Date(pair.updated_at)));
    });
}

function paintInvoices(invoices) {
    Object.entries(invoices || {}).forEach(([id, total]) => {
        document.querySelectorAll(`[data-invoice-total="${id}"]`).forEach((element) => {
            const suffix = element.querySelector('i');

            if (suffix) {
                const text = element.firstChild;
                if (text && text.nodeType === Node.TEXT_NODE && text.textContent !== ` ${money.format(total)} `) {
                    text.textContent = ` ${money.format(total)} `;
                }
            } else {
                setText(element, money.format(total));
            }
        });
    });
}

function paintIndicator(serverTime) {
    if (!indicatorLabel) {
        return;
    }

    const label = `Atualizado ${shortTime.format(new Date(serverTime))}`;
    setText(indicatorLabel, label);
}

function applySnapshot(data) {
    serverOffset = new Date(data.server_time).getTime() - Date.now();
    paintClock();

    const unread = Number(data.unread || 0);

    document.querySelectorAll('[data-live="unread-count"]').forEach((element) => {
        setText(element, unread > 9 ? '9+' : unread);
        element.hidden = unread === 0;
    });

    document.querySelectorAll('[data-live="unread-dot"]').forEach((element) => {
        setText(element, unread > 9 ? '9+' : unread);
        element.hidden = unread === 0;
    });

    paintRates(data.rates);
    paintTotals(data.totals);
    paintPreview(data.eur_usd);
    paintInvoices(data.invoices);
    paintIndicator(data.server_time);
}

async function refreshSnapshot() {
    if (!liveUrl || document.hidden) {
        return;
    }

    const ids = Array.from(document.querySelectorAll('[data-invoice-total]'))
        .map((element) => element.dataset.invoiceTotal)
        .filter(Boolean);

    const url = new URL(liveUrl, window.location.origin);
    if (ids.length > 0) {
        url.searchParams.set('invoices', ids.join(','));
    }

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (!response.ok) {
            return;
        }

        applySnapshot(await response.json());
    } catch (error) {
        // Sem conexão: mantém os valores atuais e tenta de novo no próximo ciclo.
    }
}

if (liveUrl) {
    window.setInterval(refreshSnapshot, REFRESH_MS);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            refreshSnapshot();
        }
    });

    window.setTimeout(refreshSnapshot, 1200);
}
