/* Confirmación accesible compartida por el POS y el editor de ventas. */
(() => {
    let pending = null;
    function confirmGift() {
        if (pending) return pending;
        pending = new Promise(resolve => {
            const previousFocus = document.activeElement;
            const dialog = document.createElement('dialog');
            dialog.className = 'erp-gift-dialog';
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-labelledby', 'gift-confirm-title');
            dialog.setAttribute('aria-describedby', 'gift-confirm-description');
            dialog.innerHTML = '<h2 id="gift-confirm-title">¿Estás seguro de que querés regalar los productos?</h2>' +
                '<p id="gift-confirm-description">El descuento bonifica el 100% de uno o más productos, o del pedido completo. No se cobrará ese importe, pero la mercadería igualmente saldrá del stock.</p>' +
                '<p>Esta decisión quedará registrada con tu usuario.</p>' +
                '<div class="gift-actions"><button type="button" data-gift-cancel>Volver y revisar</button><button type="button" data-gift-accept>Sí, confirmar bonificación</button></div>';
            const finish = accepted => {
                dialog.close();
                dialog.remove();
                previousFocus?.focus();
                pending = null;
                resolve(accepted);
            };
            dialog.querySelector('[data-gift-cancel]').addEventListener('click', () => finish(false));
            dialog.querySelector('[data-gift-accept]').addEventListener('click', () => finish(true));
            dialog.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
            dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
            document.body.appendChild(dialog);
            dialog.showModal();
            dialog.querySelector('[data-gift-cancel]').focus();
        });
        return pending;
    }
    window.erpSales = Object.freeze({ confirmGift });
})();
