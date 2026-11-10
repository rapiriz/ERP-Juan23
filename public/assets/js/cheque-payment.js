(() => {
    document.querySelectorAll('[data-cheque-method]').forEach(select => {
        const fields = select.form?.querySelector('[data-cheque-fields]');
        if (!fields) return;
        function update() {
            const cheque = select.value.toLowerCase() === 'cheque';
            fields.hidden = !cheque;
            fields.querySelectorAll('[data-cheque-input]').forEach(input => {
                input.disabled = !cheque;
                input.required = cheque;
            });
        }
        select.addEventListener('change', update);
        select.form.addEventListener('reset', () => setTimeout(update, 0));
        update();
    });
})();
