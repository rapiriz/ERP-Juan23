(() => {
    const $ = id => document.getElementById(id), status = $('devoluciones-status');
    let sale = null, page = 1, key = null;
    async function api(url, method = 'GET', data) {
        const response = await fetch(url, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, ...(data ? {body: JSON.stringify(data)} : {})});
        const body = await response.json();
        if (!response.ok) { const error = new Error(Object.values(body.errors || {})[0]?.[0] || body.message || 'No se pudo completar la operación.'); error.status = response.status; throw error; }
        return body;
    }
    function cell(row, text) { const td = document.createElement('td'); td.textContent = text; row.appendChild(td); return td; }
    async function list() {
        try {
            const query = new URLSearchParams(new FormData($('filtrar-devoluciones')));
            for (const [k, v] of [...query]) if (!v) query.delete(k);
            query.set('page', page);
            const data = await api('/api/ventas/devoluciones?' + query);
            $('devoluciones-lista').replaceChildren();
            for (const item of data.data) {
                const row = document.createElement('tr');
                [item.id, item.created_at, item.venta_id, item.cliente_id, Number(item.total).toLocaleString('es-AR', {style: 'currency', currency: 'ARS'})].forEach(v => cell(row, v));
                const button = document.createElement('button'); button.className = 'button button-secondary'; button.textContent = 'Ver detalle'; button.onclick = () => detail(item.id);
                cell(row, '').appendChild(button); $('devoluciones-lista').appendChild(row);
            }
            if (!data.data.length) { const tr = document.createElement('tr'); cell(tr, 'No hay devoluciones con estos filtros.').colSpan = 6; $('devoluciones-lista').appendChild(tr); }
            $('dev-pagina').textContent = `Página ${data.current_page} de ${data.last_page}`;
            $('dev-anterior').disabled = data.current_page <= 1; $('dev-siguiente').disabled = data.current_page >= data.last_page;
        } catch (error) { status.textContent = error.message; }
    }
    async function detail(id) {
        try {
            const {data} = await api('/api/ventas/devoluciones/' + Number(id));
            $('devolucion-detalle').hidden = false;
            $('dev-datos').textContent = `${data.nota_credito.numero} · Venta #${data.devolucion.venta_id} · ${data.devolucion.motivo} · Importe acreditado $${data.nota_credito.total}. Deuda actual $${data.cuenta.saldo}; saldo a favor $${data.cuenta.saldo_a_favor}.`;
            $('dev-renglones').replaceChildren();
            for (const item of data.items) { const line = document.createElement('p'); line.textContent = `Producto #${item.producto_id}: ${item.cantidad} unidades, ${item.condicion}. Precio original $${item.precio_venta}; crédito neto $${item.importe_neto}.`; $('dev-renglones').appendChild(line); }
            $('dev-nota').href = '/devoluciones/' + Number(id) + '/nota';
        } catch (error) { status.textContent = error.message; }
    }
    $('buscar-venta-devolucion')?.addEventListener('submit', async event => {
        event.preventDefault();
        try {
            sale = (await api('/api/ventas/' + Number($('devolucion-venta').value))).data;
            $('devolucion-cliente').textContent = `Venta #${sale.id} · ${sale.cliente.nombre} · ${sale.estado.etiqueta}. El crédito se calcula con el precio original y sus descuentos.`;
            $('devolucion-items').replaceChildren();
            for (const item of sale.detalles) {
                const row = document.createElement('tr'); row.dataset.detail = item.id_detalle;
                [item.producto, item.cantidad, item.cantidad_devuelta].forEach(v => cell(row, v));
                const quantity = document.createElement('input'); quantity.type = 'number'; quantity.min = 0; quantity.max = item.cantidad - item.cantidad_devuelta; quantity.value = 0; quantity.required = true; quantity.setAttribute('aria-label', 'Cantidad recibida de '+item.producto); cell(row, '').appendChild(quantity);
                const condition = document.createElement('select'); condition.setAttribute('aria-label', 'Condición de '+item.producto);
                for (const [value, label] of [['apto','Apto para venta'],['danado','Dañado'],['vencido','Vencido']]) { const option = document.createElement('option'); option.value = value; option.textContent = label; condition.appendChild(option); }
                cell(row, '').appendChild(condition); $('devolucion-items').appendChild(row);
            }
            key = crypto.randomUUID(); $('registrar-devolucion').hidden = false; status.textContent = '';
        } catch (error) { $('registrar-devolucion').hidden = true; status.textContent = error.message; }
    });
    $('registrar-devolucion')?.addEventListener('submit', async event => {
        event.preventDefault(); const button = event.submitter; button.disabled = true;
        try {
            const items = [...$('devolucion-items').children].map(row => ({detalle_venta_id: Number(row.dataset.detail), cantidad: Number(row.querySelector('input').value), condicion: row.querySelector('select').value})).filter(item => item.cantidad > 0);
            const payload = {request_key: key, motivo: $('devolucion-motivo').value, items};
            const scope = 'return_pending_'+$('registrar-devolucion').dataset.actor+'_'+sale.id;
            const previous = JSON.parse(sessionStorage.getItem(scope) || 'null');
            if (previous && JSON.stringify({...previous, request_key: null}) !== JSON.stringify({...payload, request_key: null}) && !confirm('Hay una recepción pendiente. ¿Recuperar el envío original antes de registrar otra?')) return;
            const pending = previous || payload;
            sessionStorage.setItem(scope, JSON.stringify(pending));
            let body;
            try { body = await api('/api/ventas/'+sale.id+'/devoluciones', 'POST', pending); }
            catch (error) { if (error.status >= 400 && error.status < 500 && error.status !== 409) sessionStorage.removeItem(scope); throw error; }
            sessionStorage.removeItem(scope);
            const {data} = body;
            status.textContent = `Devolución #${data.devolucion_id} registrada. ${data.stock_estado === 'pendiente' ? 'Stock pendiente de sincronizar; no repitas la recepción.' : 'Recepción confirmada.'}`;
            $('registrar-devolucion').hidden = true; await list(); await detail(data.devolucion_id);
        } catch (error) { status.textContent = error.message; } finally { button.disabled = false; }
    });
    $('filtrar-devoluciones').addEventListener('submit', event => {event.preventDefault(); page = 1; list();});
    $('dev-anterior').onclick = () => {page--; list();}; $('dev-siguiente').onclick = () => {page++; list();};
    list();
})();
