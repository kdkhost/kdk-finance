/* ============================================================================
 * SISTEMA FINANCEIRO MULTIMOEDAS & GESTÃO DE INFRAESTRUTURA
 * ============================================================================
 * Desenvolvedor : Marcelo Brad - RJ
 * Empresa       : KDKHost Soluções (CNPJ: 33.986.975/0001-90)
 * Contato       : contato@kdkhost.com.br | https://kdkhost.com.br
 * Função        : Responsável Técnico / Engenheiro de Software Full-Stack
 * ============================================================================
 */

const payload = document.getElementById('invoice-calc-data');

if (payload) {
    const config = JSON.parse(payload.textContent);
    const amountInput = document.getElementById('amount_original');
    const currencyInput = document.getElementById('currency');
    const rateInput = document.getElementById('exchange_rate');

    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

    const set = (key, value) => {
        document.querySelectorAll(`[data-calc="${key}"]`).forEach((element) => {
            element.textContent = value;
        });
    };

    const parseNumber = (value) => {
        if (!value) {
            return 0;
        }

        return Number(String(value).replace(/\s/g, '').replace(',', '.')) || 0;
    };

    const recalc = () => {
        const currency = currencyInput?.value || 'EUR';
        const rates = config.rates || {};
        const taxes = config.taxes || {};

        const currentRate = Number(rates[currency]?.rate || 0);
        const rate = parseNumber(rateInput?.value) || currentRate;
        const amount = parseNumber(amountInput?.value);

        if (rateInput) {
            rateInput.placeholder = decimal.format(currentRate);
        }

        const base = amount * rate;
        const spread = base * (Number(taxes.spread || 0) / 100);
        const baseWithSpread = base + spread;
        const iof = baseWithSpread * (Number(taxes.iof || 0) / 100);
        const icms = baseWithSpread * (Number(taxes.icms || 0) / 100);
        const others = baseWithSpread * (Number(taxes.outros || 0) / 100);
        const total = baseWithSpread + iof + icms + others;

        set('rate', decimal.format(rate));
        set('base', money.format(base));
        set('spread', money.format(spread));
        set('iof', money.format(iof));
        set('icms', money.format(icms));
        set('others', money.format(others));
        set('total', money.format(total));
        set('vet', `R$ ${decimal.format(amount > 0 ? total / amount : 0)}`);
        set('currency-code', currency);
        set('spread-percent', `${Number(taxes.spread || 0).toFixed(2)}%`);
        set('iof-percent', `${Number(taxes.iof || 0).toFixed(2)}%`);
        set('icms-percent', `${Number(taxes.icms || 0).toFixed(2)}%`);
        set('others-percent', `${Number(taxes.outros || 0).toFixed(2)}%`);
    };

    [amountInput, currencyInput, rateInput].forEach((input) => {
        input?.addEventListener('input', recalc);
        input?.addEventListener('change', recalc);
    });

    recalc();
}
