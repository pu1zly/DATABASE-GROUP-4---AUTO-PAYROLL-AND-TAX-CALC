/* script.js - Overhauled Payroll Logic (Phase 1-4) */
const CURRENCY_CONVERSION_RATE = 56.00;
const CURRENCY_MODE_KEY = 'currencyMode';
const AVAILABLE_CURRENCIES = ['USD', 'PHP'];

function getCurrencyMode() {
    try {
        const stored = localStorage.getItem(CURRENCY_MODE_KEY);
        return AVAILABLE_CURRENCIES.includes(stored) ? stored : 'USD';
    } catch (e) {
        return 'USD';
    }
}

function formatCurrency(value, currency) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '';
    const symbol = currency === 'PHP' ? '₱' : '$';
    const formatted = amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return symbol + formatted;
}

function updateCurrencyControls() {
    const currency = getCurrencyMode();
    document.querySelectorAll('[data-currency-toggle]').forEach(control => {
        const tag = control.tagName.toLowerCase();
        if (tag === 'select') {
            control.value = currency;
        } else if (control.type === 'checkbox') {
            control.checked = currency === 'PHP';
        }
    });
}

function updateCurrencyDisplay() {
    const currency = getCurrencyMode();
    document.querySelectorAll('.currency-amount').forEach(el => {
        const usdValue = Number(el.dataset.usd);
        if (!Number.isFinite(usdValue)) return;
        const converted = currency === 'PHP' ? usdValue * CURRENCY_CONVERSION_RATE : usdValue;
        el.textContent = formatCurrency(converted, currency);
    });
    updateCurrencyControls();
}

function setCurrencyMode(currency) {
    if (!AVAILABLE_CURRENCIES.includes(currency)) return;
    try {
        localStorage.setItem(CURRENCY_MODE_KEY, currency);
    } catch (e) {
        // localStorage may be unavailable; still update UI
    }
    updateCurrencyDisplay();
}

window.setCurrencyMode = setCurrencyMode;

document.addEventListener('DOMContentLoaded', function() {
    const positionSelect = document.getElementById('position-select');
    const customTaxContainer = document.getElementById('custom-tax-container');
    const taxRateHidden = document.getElementById('tax_rate_hidden');
    const customTaxInput = document.querySelector('input[name="custom_tax_rate"]');
    const currencyToggles = document.querySelectorAll('[data-currency-toggle]');

    if (currencyToggles.length > 0) {
        currencyToggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const newCurrency = this.type === 'checkbox'
                    ? (this.checked ? 'PHP' : 'USD')
                    : this.value;
                setCurrencyMode(newCurrency);
            });
        });
    }

    updateCurrencyDisplay();

    // Phase 1: Position-based Tax Logic
    if (positionSelect) {
        positionSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const taxValue = selectedOption.getAttribute('data-tax');

            if (this.value === 'Custom') {
                customTaxContainer.style.display = 'block';
                customTaxInput.setAttribute('required', 'required');
                taxRateHidden.value = ''; // Will be handled by custom input
            } else {
                customTaxContainer.style.display = 'none';
                customTaxInput.removeAttribute('required');
                taxRateHidden.value = taxValue;
            }
        });
    }

    // Phase 2: 30-Day Timesheet Logic
    const timesheetForm = document.getElementById('timesheet-form');
    const totalDisplay = document.getElementById('ts-total');

    function calculateTotalHours() {
        let total = 0;
        const rows = document.querySelectorAll('.day-row');
        rows.forEach(row => {
            const isOff = row.querySelector('.day-off-check').checked;
            if (!isOff) {
                const reg = parseFloat(row.querySelector('input[name^="reg_"]').value) || 0;
                const ot = parseFloat(row.querySelector('input[name^="ot_"]').value) || 0;
                total += (reg + ot);
            }
        });
        totalDisplay.textContent = total.toFixed(1);
    }

    if (timesheetForm) {
        timesheetForm.addEventListener('input', calculateTotalHours);

        // Day Off Toggle Logic
        document.querySelectorAll('.day-off-check').forEach(check => {
            check.addEventListener('change', function() {
                const day = this.getAttribute('data-day');
                const row = document.getElementById('row-' + day);
                if (this.checked) {
                    row.classList.add('day-off-active');
                    row.querySelectorAll('.ts-input').forEach(input => input.value = 0);
                } else {
                    row.classList.remove('day-off-active');
                    row.querySelector('input[name^="reg_"]').value = 8;
                }
                calculateTotalHours();
            });
        });

        calculateTotalHours(); // Initial calculation
    }

    // Phase 2-4: General Form Validations
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const numericInputs = form.querySelectorAll('input[type="number"]');

            numericInputs.forEach(input => {
                if (parseFloat(input.value) < 0) {
                    isValid = false;
                    alert(`${input.placeholder || 'Value'} cannot be negative.`);
                    input.style.borderColor = 'red';
                } else {
                    input.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    });
});
