/* script.js - Overhauled Payroll Logic (Phase 1-4) */
document.addEventListener('DOMContentLoaded', function() {
    const positionSelect = document.getElementById('position-select');
    const customTaxContainer = document.getElementById('custom-tax-container');
    const taxRateHidden = document.getElementById('tax_rate_hidden');
    const customTaxInput = document.querySelector('input[name="custom_tax_rate"]');

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
