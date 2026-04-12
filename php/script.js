/* script.js - Frontend Validations (Student D) */
document.addEventListener('DOMContentLoaded', function() {
    const hourlyInput = document.getElementById('hourly_rate');
    const taxInput = document.getElementById('tax_rate');
    const previewSpan = document.getElementById('net_hourly_preview');

    function updatePreview() {
        const hourly = parseFloat(hourlyInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const net = hourly - (hourly * (tax / 100));
        previewSpan.textContent = '$' + net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    if (hourlyInput && taxInput) {
        hourlyInput.addEventListener('input', updatePreview);
        taxInput.addEventListener('input', updatePreview);
        updatePreview(); // Initial calculation
    }

    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = form.querySelectorAll('input[required], select[required]');
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'red';
                } else {
                    input.style.borderColor = '#ddd';
                }

                // Numeric validations
                if (input.type === 'number' && parseFloat(input.value) < 0) {
                    isValid = false;
                    alert(`${input.placeholder || 'Field'} cannot be negative.`);
                    input.style.borderColor = 'red';
                }
            });

            // Special validation for clock in/out
            const clockIn = form.querySelector('input[name="clock_in"]');
            const clockOut = form.querySelector('input[name="clock_out"]');
            if (clockIn && clockOut && clockIn.value && clockOut.value) {
                if (new Date(clockIn.value) >= new Date(clockOut.value)) {
                    isValid = false;
                    alert('Clock-out time must be after clock-in time.');
                    clockOut.style.borderColor = 'red';
                }
            }

            if (!isValid) {
                e.preventDefault();
                console.warn('Form validation failed.');
            }
        });
    });
});
