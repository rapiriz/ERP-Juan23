(() => {
    'use strict';
    const app = document.querySelector('[data-lots-app]'); if (!app) return;
    const status = app.querySelector('[data-lot-status]'); const filters = app.querySelector('[data-lot-filters]');
    let page = 1, lastPage = 1;
    const text = (parent, tag, value) => { const el = document.createElement(tag); el.textContent = value ?? ''; parent.append(el); return el; };
    const request = async (url, options = {}) => {
        const response = await fetch(url, {headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, ...(options.body instanceof FormData ? {} : {'Content-Type': 'application/json'})}, ...options});
        const data = await response.json(); if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo completar la operación.'); return data;
    };
    async function load() {
        try {
            const query = new URLSearchParams(new FormData(filters)); query.set('page', String(page));
            const result = await request(app.dataset.list + '?' + query); lastPage = result.meta.last_page;
            const body = app.querySelector('[data-lot-rows]'); body.replaceChildren();
            for (const lot of result.data) {
                const row = document.createElement('tr'); body.append(row);
                for (const value of [lot.producto || lot.codigo || `Producto #${lot.id_producto}`, lot.nro_lote, String(lot.fecha_vencimiento).slice(0,10), `${lot.cantidad_actual} / ${lot.cantidad_inicial}`, lot.estado, lot.estado_trazabilidad === 'controlado' ? 'Controlado' : 'Pendiente de revisión']) text(row, 'td', value);
                const actions = text(row, 'td', '');
                const link = text(actions, 'a', 'Movimientos JSON'); link.href = app.dataset.base + '/' + Number(lot.id_lote) + '/movimientos';
                if (lot.estado_trazabilidad !== 'controlado' && app.querySelector('[data-lot-create]')) {
                    const adopt = text(actions, 'button', 'Verifiqué el lote: adoptar'); adopt.type = 'button'; adopt.className = 'button button-secondary';
                    adopt.addEventListener('click', async () => {
                        if (!confirm('¿Verificaste físicamente este lote? Adoptarlo habilita su trazabilidad sin sumar stock.')) return;
                        adopt.disabled = true;
                        try { await request(app.dataset.base + '/' + Number(lot.id_lote) + '/adoptar', {method:'POST', body: JSON.stringify({confirmar:true})}); await load(); } catch(error) { status.textContent = error.message; } finally { adopt.disabled = false; }
                    });
                }
            }
            if (!result.data.length) { const row = text(body,'tr',''); const cell = text(row,'td','No hay lotes para los filtros seleccionados.'); cell.colSpan = 7; }
            app.querySelector('[data-lot-page]').textContent = `Página ${page} de ${lastPage} · ${result.meta.total} lotes`;
            app.querySelector('[data-lot-prev]').disabled = page <= 1; app.querySelector('[data-lot-next]').disabled = page >= lastPage;
            status.textContent = `${result.alertas.vencidos} lotes vencidos · ${result.alertas.proximos} próximos a vencer · ${result.alertas.historicos_pendientes} históricos pendientes de revisión.`;
        } catch(error) { status.textContent = error.message; }
    }
    filters.addEventListener('submit', event => {event.preventDefault(); page = 1; load();});
    app.querySelector('[data-lot-prev]').addEventListener('click', () => { if(page > 1) { page--; load(); }});
    app.querySelector('[data-lot-next]').addEventListener('click', () => { if(page < lastPage) { page++; load(); }});
    app.querySelector('[data-lot-create]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form = event.currentTarget; const button = form.querySelector('button[type=submit]'); button.disabled = true;
        try { await request(app.dataset.store, {method:'POST', body:new FormData(form)}); form.reset(); page = 1; await load(); } catch(error) { status.textContent = error.message; } finally { button.disabled = false; }
    });
    const initial = new URLSearchParams(location.search).get('estado'); if (initial) filters.elements.estado.value = initial;
    load();
})();
