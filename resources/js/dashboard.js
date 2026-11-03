/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

import Chart from 'chart.js/auto';
import toastr from 'toastr';

const payload = document.getElementById('dashboard-data');
const data = payload ? JSON.parse(payload.textContent) : null;

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

let monthlyChart = null;
let statusChart = null;

function token(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value || fallback;
}

function palette() {
    return {
        teal: token('--kdk-teal', '#0f9d76'),
        tealSoft: token('--kdk-teal-soft', 'rgba(15,157,118,.14)'),
        amber: token('--kdk-amber', '#b45309'),
        amberSoft: token('--kdk-amber-soft', 'rgba(180,83,9,.14)'),
        border: token('--kdk-border', '#e2e8f0'),
        text: token('--kdk-text', '#0f1c2e'),
        muted: token('--kdk-muted', '#64748b'),
        card: token('--kdk-card', '#ffffff'),
    };
}

function renderCharts() {
    if (!data) {
        return;
    }

    const colors = palette();
    const monthlyCanvas = document.getElementById('chart-monthly');
    const statusCanvas = document.getElementById('chart-status');

    if (monthlyCanvas) {
        monthlyChart?.destroy();
        monthlyChart = new Chart(monthlyCanvas, {
            type: 'bar',
            data: {
                labels: data.series.labels,
                datasets: [
                    {
                        label: 'Pago (R$)',
                        data: data.series.paid,
                        backgroundColor: colors.teal,
                        borderRadius: 6,
                        maxBarThickness: 26,
                    },
                    {
                        label: 'Pendente (R$)',
                        data: data.series.pending,
                        backgroundColor: colors.amber,
                        borderRadius: 6,
                        maxBarThickness: 26,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { color: colors.muted, boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' },
                    },
                    tooltip: {
                        backgroundColor: colors.card,
                        titleColor: colors.text,
                        bodyColor: colors.text,
                        borderColor: colors.border,
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: (context) => ` ${context.dataset.label}: ${money.format(context.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: colors.muted, font: { size: 11 } },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.border, drawBorder: false },
                        ticks: {
                            color: colors.muted,
                            font: { size: 11 },
                            callback: (value) => money.format(value).replace(/\s/g, ' '),
                        },
                    },
                },
            },
        });
    }

    if (statusCanvas) {
        statusChart?.destroy();
        statusChart = new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Pendentes', 'Pagas', 'Canceladas'],
                datasets: [
                    {
                        data: [
                            data.distribution.pending,
                            data.distribution.paid,
                            data.distribution.canceled,
                        ],
                        backgroundColor: [colors.amber, colors.teal, colors.muted],
                        borderColor: colors.card,
                        borderWidth: 3,
                        hoverOffset: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: colors.muted, boxWidth: 9, boxHeight: 9, usePointStyle: true, pointStyle: 'circle' },
                    },
                },
            },
        });
    }
}

renderCharts();

document.addEventListener('finance:theme-changed', renderCharts);

document.addEventListener('finance:invoice-updated', (event) => {
    const detail = event.detail || {};
    const invoice = detail.invoice || {};
    const totals = detail.totals || {};

    if (totals.pending_total !== undefined) {
        document.querySelectorAll('[data-live="pending-total"]').forEach((element) => {
            element.textContent = money.format(totals.pending_total);
        });
    }

    if (totals.pending_count !== undefined) {
        document.querySelectorAll('[data-live="pending-count"]').forEach((element) => {
            element.textContent = totals.pending_count;
        });
    }

    if (totals.paid_month_total !== undefined) {
        document.querySelectorAll('[data-live="paid-month-total"]').forEach((element) => {
            element.textContent = money.format(totals.paid_month_total);
        });
    }

    if (totals.overdue_count !== undefined) {
        document.querySelectorAll('[data-live="overdue-count"]').forEach((element) => {
            element.textContent = totals.overdue_count;
        });
    }

    if (invoice.id) {
        document.querySelectorAll(`[data-invoice-total="${invoice.id}"]`).forEach((element) => {
            element.textContent = money.format(invoice.total_brl || 0);
        });

        const row = document.querySelector(`[data-invoice-row="${invoice.id}"]`);
        if (row) {
            row.classList.add('is-highlighted');
            window.setTimeout(() => row.classList.remove('is-highlighted'), 2600);
        }
    }

    if (detail.alert && invoice.description) {
        toastr.warning(
            `${invoice.description} variou ${Number(invoice.variation_percent || 0).toFixed(2)}% e agora totaliza ${money.format(invoice.total_brl || 0)}.`,
            'Variação cambial'
        );
    }
});

document.addEventListener('finance:notification', (event) => {
    const notification = event.detail || {};
    const unread = document.querySelector('[data-live="unread-count"]');
    const dot = document.querySelector('[data-live="unread-dot"]');

    if (unread) {
        unread.textContent = String(Number(unread.textContent || 0) + 1);
        unread.hidden = false;
    }

    if (dot) {
        dot.hidden = false;
    }

    if (notification.title) {
        toastr.info(notification.message || '', notification.title);
    }
});

document.addEventListener('finance:live-connected', () => {
    document.querySelectorAll('[data-live-indicator]').forEach((element) => {
        element.classList.add('is-live');
        const label = element.querySelector('[data-live-label]');
        if (label) {
            label.textContent = 'Tempo real ativo';
        }
    });
});
