/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : George Marcelo de Oliveira Pimentel
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

const payload = document.getElementById('conversion-calc-data');

if (payload) {
    const config = JSON.parse(payload.textContent);
    const form = document.getElementById('conversion-form');
    const fromInput = document.getElementById('from_currency');
    const toInput = document.getElementById('to_currency');
    const amountInput = document.getElementById('amount_from');
    const rateInput = document.getElementById('market_rate');
    const spreadInput = document.getElementById('spread_percent');
    const iofInput = document.getElementById('iof_percent');
    const fixedInput = document.getElementById('fixed_fee');
    const bufferInput = document.getElementById('buffer_percent');
    const refreshButton = document.getElementById('refresh-quote');
    const swapButton = document.getElementById('swap-currencies');

    const symbols = config.symbols || { EUR: '€', USD: '$' };
    const state = {
        rate: Number(config.rate || 0),
        toBrlRate: Number(config.toBrlRate || 0),
        source: config.source || '',
        updatedAt: config.updatedAt || '',
    };

    const money = (currency, value) => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: currency || 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value || 0);

    const decimal = (value, digits) => new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(value || 0);

    const set = (key, value) => {
        document.querySelectorAll(`[data-calc="${key}"]`).forEach((element) => {
            element.textContent = value;
        });
    };

    const parseNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return 0;
        }

        return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
    };

    const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;

    const currentPair = () => `${fromInput?.value || 'EUR'}-${toInput?.value || 'USD'}`;

    const paintLabels = () => {
        const from = fromInput?.value || 'EUR';
        const to = toInput?.value || 'USD';

        set('from-code', from);
        set('to-code', to);
        set('from-symbol', symbols[from] || from);
        set('pair-label', `(${from}-${to})`);
        set('pair-chip', `${from} → ${to}`);
    };

    const paintQuoteMeta = () => {
        set('quote-source', state.source || '—');
        set('quote-at', state.updatedAt
            ? new Date(state.updatedAt).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
            : '—');
    };

    const recalc = () => {
        const from = fromInput?.value || 'EUR';
        const to = toInput?.value || 'USD';
        const amount = parseNumber(amountInput?.value);
        const rate = parseNumber(rateInput?.value) || state.rate;
        const spreadPercent = parseNumber(spreadInput?.value);
        const iofPercent = parseNumber(iofInput?.value);
        const fixedFee = parseNumber(fixedInput?.value);
        const bufferPercent = parseNumber(bufferInput?.value);

        const base = round2(amount * rate);
        const spread = round2(base * (spreadPercent / 100));
        const subtotal = round2(base + spread);
        const iof = round2(subtotal * (iofPercent / 100));
        const fixed = round2(fixedFee);
        const buffer = round2((subtotal + iof + fixed) * (bufferPercent / 100));
        const total = round2(subtotal + iof + fixed + buffer);
        const effective = amount > 0 ? total / amount : 0;
        const cost = base > 0 ? ((total / base) - 1) * 100 : 0;
        const brl = total * state.toBrlRate;
        const vet = amount > 0 && brl > 0 ? brl / amount : 0;

        set('total', money(to, total));
        set('base', money(to, base));
        set('spread', money(to, spread));
        set('iof', money(to, iof));
        set('fixed', money(to, fixed));
        set('buffer', money(to, buffer));
        set('fees', money(to, total - base));
        set('spread-percent', `${decimal(spreadPercent, 3)}%`);
        set('iof-percent', `${decimal(iofPercent, 3)}%`);
        set('buffer-percent', `${decimal(bufferPercent, 3)}%`);
        set('effective', decimal(effective, 6));
        set('cost', `${decimal(cost, 3)}%`);
        set('brl', brl > 0 ? money('BRL', brl) : '—');
        set('vet', vet > 0 ? `R$ ${decimal(vet, 6)}` : '—');
        set('amount-label', `${money(from, amount)}`);
    };

    const applyQuote = (quote) => {
        state.rate = Number(quote.rate || 0);
        state.toBrlRate = Number(quote.to_brl_rate || 0);
        state.source = quote.source || '';
        state.updatedAt = quote.updated_at || '';

        if (rateInput) {
            rateInput.value = state.rate ? state.rate.toFixed(6) : '';
        }

        paintQuoteMeta();
        recalc();
    };

    const fetchQuote = async (fresh = false) => {
        if (!form) {
            return;
        }

        const from = fromInput?.value || 'EUR';
        const to = toInput?.value || 'USD';

        if (from === to) {
            return;
        }

        const button = refreshButton;
        const icon = button?.querySelector('i');

        if (button) {
            button.disabled = true;
        }

        icon?.classList.add('spin');

        try {
            const url = new URL(config.quoteUrl, window.location.origin);
            url.searchParams.set('from', from);
            url.searchParams.set('to', to);

            if (fresh) {
                url.searchParams.set('fresh', '1');
            }

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const quote = await response.json();
            applyQuote(quote);

            if (quote.stale) {
                window.toastr?.warning('A API de câmbio não respondeu; usamos a última cotação conhecida.', 'Cotação desatualizada');
            } else if (fresh) {
                window.toastr?.success(`Cotação ${from}-${to}: ${decimal(state.rate, 6)}.`, 'Câmbio atualizado');
            }
        } catch (error) {
            window.toastr?.error('Não foi possível consultar a cotação agora. Mantive o valor atual.', 'Falha no câmbio');
        } finally {
            if (button) {
                button.disabled = false;
            }

            icon?.classList.remove('spin');
        }
    };

    const ensureDifferent = (changed) => {
        if (!fromInput || !toInput) {
            return false;
        }

        if (fromInput.value !== toInput.value) {
            return false;
        }

        const options = Array.from(changed === fromInput ? toInput.options : fromInput.options);
        const alternative = options.find((option) => option.value !== changed.value);

        if (alternative) {
            (changed === fromInput ? toInput : fromInput).value = alternative.value;
        }

        return true;
    };

    [amountInput, rateInput, spreadInput, iofInput, fixedInput, bufferInput].forEach((input) => {
        input?.addEventListener('input', recalc);
        input?.addEventListener('change', recalc);
    });

    [fromInput, toInput].forEach((select) => {
        select?.addEventListener('change', () => {
            ensureDifferent(select);
            paintLabels();
            fetchQuote(false);
        });
    });

    refreshButton?.addEventListener('click', () => fetchQuote(true));

    swapButton?.addEventListener('click', () => {
        if (!fromInput || !toInput) {
            return;
        }

        const from = fromInput.value;
        fromInput.value = toInput.value;
        toInput.value = from;

        paintLabels();
        fetchQuote(false);
    });

    paintLabels();
    paintQuoteMeta();
    recalc();
}
