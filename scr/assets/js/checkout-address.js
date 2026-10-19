'use strict';
(() => {
    const select = document.getElementById('address-id');
    if (!select) return;
    const fields = ['fullname', 'phone', 'address'];
    select.addEventListener('change', () => {
        if (!select.value) return;
        const option = select.selectedOptions[0];
        fields.forEach(name => { document.getElementById(name).value = option.dataset[name]; });
    });
    fields.forEach(name => {
        document.getElementById(name).addEventListener('input', () => { select.value = ''; });
    });
})();
