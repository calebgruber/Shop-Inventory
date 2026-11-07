(() => {
    'use strict';
    const form = document.querySelector('#setup-form');
    const button = document.querySelector('#setup-submit');
    if (!form || !button) return;
    form.addEventListener('submit', event => {
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }
        button.disabled = true;
        button.querySelector('.setup-button-label').hidden = true;
        button.querySelector('.setup-button-loading').hidden = false;
        button.querySelector('.spinner-border').hidden = false;
        document.querySelectorAll('.setup-checklist span').forEach((step, index) => {
            step.textContent = ['Connecting to the database…','Applying secure schema migrations…','Creating your administrator…'][index];
        });
    });
})();
