(function () {
    'use strict';
    let delivery = null;
    const button = document.getElementById('editDeliveryButton'), dialog = document.getElementById('deliveryEditor');
    if (!button || !dialog) return;
    const list = document.getElementById('deliveryEditorOrders'), message = document.getElementById('deliveryEditorStatus');
    window.addEventListener('g2:delivery', event => { delivery = event.detail; button.hidden = delivery.estado !== 'en_preparacion'; });
    button.addEventListener('click', async () => {
        message.textContent = ''; list.replaceChildren(); dialog.hidden = false;
        try {
            const response = await g2Fetch('/g2/api/entregas/pendientes-despacho' + (delivery.id_zona ? `?zona_id=${Number(delivery.id_zona)}` : ''));
            const data = await response.json(); if (!response.ok) throw new Error(data.mensaje);
            const current = delivery.pedidos || [], ids = new Set(current.map(p => Number(p.id_venta)));
            const rows = [...current, ...(data.data || []).filter(p => !ids.has(Number(p.id_venta)))];
            rows.forEach(row => {
                const label = document.createElement('label'), checkbox = document.createElement('input');
                checkbox.type = 'checkbox'; checkbox.value = Number(row.id_venta); checkbox.checked = ids.has(Number(row.id_venta));
                label.append(checkbox, document.createTextNode(` Pedido #${Number(row.id_venta)} · ${row.cliente_nombre || ''}`));
                const paragraph = document.createElement('p'); paragraph.append(label); list.append(paragraph);
            });
        } catch (error) { message.textContent = error.message || 'No se pudieron consultar los pedidos.'; }
    });
    document.getElementById('deliveryEditorCancel').addEventListener('click', () => { dialog.hidden = true; });
    document.getElementById('deliveryEditorSave').addEventListener('click', async event => {
        event.target.disabled = true;
        try {
            const ids = [...list.querySelectorAll('input:checked')].map(input => Number(input.value));
            const response = await g2Fetch(`/g2/api/entregas/${Number(delivery.id_entrega)}/pedidos`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pedidos_ids: ids }) });
            const data = await response.json(); if (!response.ok) throw new Error(data.mensaje || data.message);
            dialog.hidden = true; await abrirModalHojaRuta(delivery.id_entrega); await cargarEntregas();
        } catch (error) { message.textContent = error.message || 'No se pudo actualizar la entrega.'; }
        finally { event.target.disabled = false; }
    });
})();
