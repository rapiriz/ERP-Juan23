(() => {
    'use strict';
    const key = 'pyb.catalog.selection.v1'; const pendingKey = 'pyb.catalog.pending.v1';
    const read = () => { try { const data = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(data) ? data.filter(x => Number.isInteger(x.id) && x.id > 0 && Number.isInteger(x.cantidad) && x.cantidad > 0 && x.cantidad <= 999).slice(0,50) : []; } catch { return []; } };
    const save = items => { try { localStorage.setItem(key,JSON.stringify(items)); } catch {} document.querySelectorAll('[data-cart-count]').forEach(el => el.textContent = String(items.reduce((sum,x) => sum+x.cantidad,0))); };
    const announce = message => { const region = document.querySelector('[data-cart-message], [data-cart-announcement]'); if(region) region.textContent = message; };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-add-cart]'); if (!button) return;
        const id = Number(button.dataset.addCart); if (!Number.isInteger(id) || id <= 0) return;
        const items = read(); const item = items.find(i => i.id === id);
        if (item) item.cantidad = Math.min(999,item.cantidad+1); else if(items.length < 50) items.push({id,cantidad:1}); else {announce('Podés seleccionar hasta 50 productos.');return;}
        save(items); announce('Producto agregado a tu selección. Podés revisar las cantidades en Tu selección.');
    });
    save(read());
    const app = document.querySelector('[data-catalog-cart]'); if (!app) return;
    const form = app.querySelector('[data-cart-checkout]'); const rows = app.querySelector('[data-cart-rows]');
    const node = (tag,value,parent) => {const el = document.createElement(tag); el.textContent = value; parent.append(el); return el;};
    const money = value => Number(value).toLocaleString('es-AR',{style:'currency',currency:'ARS'});
    let ready = false, loading = 0;
    async function render() {
        const version = ++loading; ready = false; form.querySelector('button').disabled = true; const items = read(); rows.replaceChildren();
        if(!items.length) {node('p','Tu selección está vacía.',rows);app.querySelector('[data-cart-total]').textContent = '';return;}
        let total = 0, unpriced = false;
        try {
            for (const item of items) {
                const response = await fetch(app.dataset.productUrl + '/' + item.id,{headers:{Accept:'application/json'}});
                if(!response.ok) throw new Error('Un producto ya no está disponible para consulta. Quitalo o volvé a intentarlo.');
                const product = (await response.json()).data; if(version !== loading) return;
                const row = node('article','',rows); row.className = 'cat-cart-row';
                if(product.imagen) {const img = document.createElement('img');img.src=product.imagen;img.alt='';img.width=72;img.height=72;row.append(img);}
                node('strong',product.nombre,row); node('span','Cód. '+product.codigo+' · '+product.presentacion,row);
                const label = node('label','Unidades individuales ',row); const input = document.createElement('input'); input.type='number';input.min='1';input.max='999';input.step='1';input.value=String(item.cantidad);label.append(input);
                input.addEventListener('change',()=>{const count=Number(input.value);if(!Number.isInteger(count)||count<1||count>999){input.value=String(item.cantidad);return;}save(read().map(i=>i.id===item.id?{...i,cantidad:count}:i));render();});
                const remove = node('button','Quitar',row);remove.type='button';remove.className='cat-button';remove.addEventListener('click',()=>{save(read().filter(i=>i.id!==item.id));render();});
                node('span',product.precio === null ? 'Precio a confirmar' : money(product.precio)+' por unidad',row);
                total += Math.round(Number(product.precio || 0)*100)*item.cantidad;unpriced ||= product.precio === null;
            }
            app.querySelector('[data-cart-total]').textContent = unpriced ? 'Hay precios a confirmar.' : 'Total de referencia: '+money(total/100);
            ready = true; form.querySelector('button').disabled = false;
        } catch(error) {
            announce(error.message);node('p','No se pudo actualizar toda la selección. No se envió ninguna solicitud.',rows);
            const clear = node('button','Vaciar selección',rows);clear.type='button';clear.addEventListener('click',()=>{save([]);render();});
        }
    }
    const draft = () => {
        let data; try {data=JSON.parse(sessionStorage.getItem(pendingKey));} catch {}
        if(!data?.request_key || !data?.receipt_token) {const bytes=crypto.getRandomValues(new Uint8Array(32));data={request_key:crypto.randomUUID(),receipt_token:Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('')};sessionStorage.setItem(pendingKey,JSON.stringify(data));}
        return data;
    };
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(!ready || !form.reportValidity())return;const button=form.querySelector('button');button.disabled=true;
        try {
            const data={...Object.fromEntries(new FormData(form)),...draft(),items:read()};
            const response=await fetch(app.dataset.orderUrl,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:JSON.stringify(data)});
            const result=await response.json();if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join(' ')||result.message||'No se pudo enviar la solicitud.');
            save([]);sessionStorage.removeItem(pendingKey);location.assign(result.receipt_url);
        } catch(error) {announce(error.message);button.disabled=false;}
    });
    render();
})();
