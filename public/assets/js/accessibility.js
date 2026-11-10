(() => {
    let active = null, previous = null;
    const visible = el => el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
    const controls = el => [...el.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')].filter(visible);
    const update = () => {
        const dialogs = [...document.querySelectorAll('dialog,[role="dialog"],[id^="modal"].fixed,.clay-modal-backdrop,.modal-overlay')];
        for (const dialog of dialogs) {
            if (!dialog.hasAttribute('role')) dialog.setAttribute('role','dialog');
            if (!dialog.hasAttribute('aria-label') && !dialog.hasAttribute('aria-labelledby')) {
                const title = dialog.querySelector('h1,h2,h3,[id*="Titulo"]');
                if (title) { title.id ||= `${dialog.id || 'erp-dialog'}-title`; dialog.setAttribute('aria-labelledby',title.id); }
            }
        }
        const open = dialogs.filter(visible).at(-1) || null;
        if (open !== active) {
            if (open) { previous = document.activeElement; active = open; open.setAttribute('aria-modal','true'); (controls(open)[0] || open).focus(); }
            else { active = null; if (previous?.isConnected) previous.focus(); previous = null; }
        }
    };
    document.addEventListener('keydown', event => {
        if (!active || !visible(active)) return;
        if (event.key === 'Escape' && active.tagName !== 'DIALOG') {
            const close = active.querySelector('button[aria-label^="Cerrar"],button[onclick^="cerrar"],button.modal-close,button.cerrar');
            if (visible(close)) {event.preventDefault();close.click();}
            return;
        }
        if (event.key !== 'Tab') return;
        const items = controls(active); if (!items.length) return;
        const first=items[0],last=items.at(-1);
        if (event.shiftKey && (document.activeElement===first || !active.contains(document.activeElement))) {event.preventDefault();last.focus();}
        else if (!event.shiftKey && document.activeElement===last) {event.preventDefault();first.focus();}
    });
    new MutationObserver(update).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style','hidden','open']});
    update();
})();
