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
    const natureInput = document.getElementById('tax_nature');
    const serviceToggle = document.getElementById('apply_service_taxes');

    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

    const taxes = config.taxes || {};
    const serviceRates = taxes.service || {};
    const enabled = taxes.enabled || {};

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

    const setPercent = (key, percent) => {
        set(`${key}-percent`, `${Number(percent || 0).toFixed(2).replace('.', ',')}%`);
    };

    const showLine = (key, visible) => {
        document.querySelectorAll(`[data-line="${key}"]`).forEach((row) => {
            row.hidden = !visible;
        });
    };

    const recalc = () => {
        const currency = currencyInput?.value || 'EUR';
        const rates = config.rates || {};

        const currentRate = Number(rates[currency]?.rate || 0);
        const rate = parseNumber(rateInput?.value) || currentRate;
        const amount = parseNumber(amountInput?.value);
        const nature = natureInput?.value === 'goods' ? 'goods' : 'service';
        const applyService = !!serviceToggle?.checked;

        if (rateInput) {
            rateInput.placeholder = decimal.format(currentRate);
        }

        // Camada 1 — encargos do cartão (fatura do banco).
        const base = amount * rate;
        const spread = base * (Number(taxes.spread || 0) / 100);
        const cardFee = Number(taxes.card_fixed_fee || 0);
        const cardSubtotal = base + spread + cardFee;
        const iof = cardSubtotal * (Number(taxes.iof || 0) / 100);
        const cardTotal = cardSubtotal + iof;

        // Camada 2 — tributos da importação.
        const serviceBase = base + spread;
        let importTotal = 0;
        let importTaxAmount = 0;
        let importDeduction = 0;

        if (nature === 'service' && applyService) {
            Object.entries(serviceRates).forEach(([key, percent]) => {
                const active = enabled[key] !== false && Number(percent) > 0;
                const value = active ? serviceBase * (Number(percent) / 100) : 0;
                importTotal += value;
                set(key.replace('_', '-'), money.format(value));
                setPercent(key.replace('_', '-'), percent);
                showLine(key, active);
            });
        } else {
            Object.keys(serviceRates).forEach((key) => {
                set(key.replace('_', '-'), money.format(0));
                setPercent(key.replace('_', '-'), 0);
                showLine(key, false);
            });
        }

        if (nature === 'service') {
            showLine('icms', false);
            showLine('import_tax', false);
            showLine('import_deduction', false);
        } else {
            const icmsActive = enabled.icms !== false && Number(taxes.icms) > 0;
            const icms = icmsActive ? serviceBase * (Number(taxes.icms) / 100) : 0;
            importTotal += icms;
            set('icms', money.format(icms));
            setPercent('icms', taxes.icms);
            showLine('icms', icmsActive);

            const importActive = enabled.import_tax !== false && Number(taxes.import_tax) > 0;
            const deductionUsd = Number(taxes.import_tax_deduction_usd || 0);
            importDeduction = importActive && amount > 50 ? deductionUsd * rate : 0;
            importTaxAmount = importActive
                ? Math.max(0, serviceBase * (Number(taxes.import_tax) / 100) - importDeduction)
                : 0;
            importTotal += importTaxAmount;
            set('import-tax', money.format(importTaxAmount));
            setPercent('import-tax', taxes.import_tax);
            set('import-deduction', `− ${money.format(importDeduction)}`);
            showLine('import_tax', importActive);
            showLine('import_deduction', importDeduction > 0);
        }

        const total = cardTotal + importTotal;

        set('rate', decimal.format(rate));
        set('base', money.format(base));
        set('spread', money.format(spread));
        setPercent('spread', taxes.spread);
        set('card-fee', money.format(cardFee));
        set('iof', money.format(iof));
        setPercent('iof', taxes.iof);
        set('card-total', money.format(cardTotal));
        set('import-total', money.format(importTotal));
        set('total', money.format(total));
        set('vet', `R$ ${decimal.format(amount > 0 ? total / amount : 0)}`);
        set('currency-code', currency);
    };

    [amountInput, currencyInput, rateInput, natureInput, serviceToggle].forEach((input) => {
        input?.addEventListener('input', recalc);
        input?.addEventListener('change', recalc);
    });

    recalc();
}
