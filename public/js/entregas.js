/**
 * LÓGICA FRONTEND: MÓDULO ENTREGAS (HU #1 y HU #2)
 * ERP DISTRIBUIDORA JUAN XXIII
 */

const API_BASE = '/api/v1';

let pedidosGlobales = [];
let seleccionados = new Set();

document.addEventListener('DOMContentLoaded', () => {
    // Establecer fecha de hoy por defecto
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fechaSalida').value = hoy;

    // Cargar datos iniciales
    cargarZonas();
    cargarRepartidores();
    cargarPedidosPendientes();
    cargarEntregas();
});

/**
 * Cargar combo de Zonas
 */
async function cargarZonas() {
    try {
        const res = await fetch(`${API_BASE}/zonas`);
        const json = await res.json();
        if (!json.error && json.data) {
            const select = document.getElementById('filtroZona');
            json.data.forEach(z => {
                const opt = document.createElement('option');
                opt.value = z.id_zona;
                opt.textContent = `${z.nombre} (${z.descripcion || ''})`;
                select.appendChild(opt);
            });
        }
    } catch (err) {
        console.error('Error al cargar zonas:', err);
    }
}

/**
 * Cargar combo de Repartidores
 */
async function cargarRepartidores() {
    try {
        const res = await fetch(`${API_BASE}/repartidores`);
        const json = await res.json();
        if (!json.error && json.data) {
            const select = document.getElementById('repartidorSelect');
            json.data.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id_usuario;
                opt.textContent = `${u.nombre}`;
                select.appendChild(opt);
            });

            // Si hay uno solo (ej. Matías), seleccionarlo automáticamente
            if (json.data.length === 1) {
                select.value = json.data[0].id_usuario;
            }
        }
    } catch (err) {
        console.error('Error al cargar repartidores:', err);
    }
}

/**
 * HU #1: Cargar pedidos confirmados pendientes de despacho
 */
async function cargarPedidosPendientes() {
    const idZona = document.getElementById('filtroZona').value;
    const url = idZona ? `${API_BASE}/entregas/pendientes-despacho?zona_id=${idZona}` : `${API_BASE}/entregas/pendientes-despacho`;

    const tbody = document.getElementById('tablaPedidosBody');
    tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">Cargando pedidos...</td></tr>`;

    try {
        const res = await fetch(url);
        const json = await res.json();

        if (json.error) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--danger); padding: 2rem;">${json.mensaje}</td></tr>`;
            return;
        }

        pedidosGlobales = json.data || [];
        seleccionados.clear();
        document.getElementById('selectAll').checked = false;
        actualizarResumenSeleccion();

        if (pedidosGlobales.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                No hay pedidos pendientes de despacho para la zona seleccionada.
            </td></tr>`;
            return;
        }

        tbody.innerHTML = '';
        pedidosGlobales.forEach(p => {
            const tr = document.createElement('tr');
            
            const bultos = parseInt(p.total_bultos || 0);
            const sueltos = parseInt(p.total_sueltos || 0);
            const itemsCount = (p.items || []).length;

            // Badges de carga física
            let cargaHtml = `<span class="badge-bultos">${bultos} bultos</span>`;
            if (sueltos > 0) {
                cargaHtml += ` <span class="badge-sueltos">+${sueltos} sueltas</span>`;
            }

            tr.innerHTML = `
                <td style="text-align: center;">
                    <input type="checkbox" class="custom-checkbox pedido-checkbox" value="${p.id_venta}" data-bultos="${bultos}" data-sueltos="${sueltos}" onchange="togglePedido(this)">
                </td>
                <td><strong>#PED-${p.id_venta.toString().padStart(4, '0')}</strong></td>
                <td>
                    <strong>${p.cliente_nombre}</strong><br>
                    <small style="color: var(--text-muted);">${p.razon_social || ''}</small>
                </td>
                <td><span class="badge-zona">${p.zona_nombre}</span></td>
                <td>${p.direccion}, ${p.localidad}</td>
                <td style="text-align: center;">${cargaHtml}</td>
                <td style="text-align: center;">
                    <button class="clay-btn clay-btn-secondary" style="min-height: 36px; padding: 0.35rem 0.85rem; font-size: 0.92rem;" onclick="abrirModalArticulos(${p.id_venta})">
                        Ver Items (${itemsCount})
                    </button>
                </td>
                <td style="text-align: right; font-weight: 800; color: #1E3A8A;">
                    $${Number(p.total).toLocaleString('es-AR', {minimumFractionDigits: 2})}
                </td>
                <td>
                    <small style="color: var(--text-muted);">${p.observaciones || 'Sin observaciones'}</small>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="9" style="text-align: center; color: var(--danger); padding: 2rem;">Error de conexión con el servidor.</td></tr>`;
    }
}

/**
 * Abrir modal para ver el listado exacto de cosas de un pedido (sin ser remito)
 */
function abrirModalArticulos(idVenta) {
    const pedido = pedidosGlobales.find(p => parseInt(p.id_venta) === parseInt(idVenta));
    if (!pedido) return;

    document.getElementById('articulosPedidoSubtitulo').textContent = `Pedido #PED-${pedido.id_venta.toString().padStart(4, '0')} • Fecha: ${pedido.fecha}`;
    document.getElementById('articulosClienteNombre').textContent = pedido.cliente_nombre;
    document.getElementById('articulosDireccion').textContent = `${pedido.direccion}, ${pedido.localidad}`;
    document.getElementById('articulosZona').textContent = pedido.zona_nombre;
    document.getElementById('articulosObservaciones').textContent = pedido.observaciones || 'Sin observaciones de entrega';

    const tbody = document.getElementById('articulosDetalleBody');
    tbody.innerHTML = '';

    (pedido.items || []).forEach(item => {
        const tr = document.createElement('tr');
        const bultos = parseInt(item.bultos || 0);
        const sueltos = parseInt(item.unidades_sueltas || 0);

        tr.innerHTML = `
            <td style="font-weight: 700;">${item.codigo || '-'}</td>
            <td>
                <strong>${item.producto_nombre}</strong><br>
                <small style="color: var(--text-muted);">${item.producto_descripcion || ''}</small>
            </td>
            <td style="text-align: center;">
                ${bultos > 0 ? `<span class="badge-bultos">${bultos} bulto(s)</span>` : '<span style="color: var(--text-muted);">-</span>'}
            </td>
            <td style="text-align: center;">
                ${sueltos > 0 ? `<span class="badge-sueltos">+${sueltos} sueltas</span>` : '<span style="color: var(--text-muted);">-</span>'}
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('modalArticulos').style.display = 'flex';
}

function cerrarModalArticulos() {
    document.getElementById('modalArticulos').style.display = 'none';
}

/**
 * Tildar o destildar un pedido individual
 */
function togglePedido(checkbox) {
    const id = parseInt(checkbox.value);
    if (checkbox.checked) {
        seleccionados.add(id);
    } else {
        seleccionados.delete(id);
    }
    actualizarResumenSeleccion();
}

/**
 * Tildar todos los pedidos de la lista
 */
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.pedido-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        const id = parseInt(cb.value);
        if (master.checked) {
            seleccionados.add(id);
        } else {
            seleccionados.delete(id);
        }
    });
    actualizarResumenSeleccion();
}

/**
 * Limpiar selección
 */
function limpiarSeleccion() {
    seleccionados.clear();
    document.querySelectorAll('.pedido-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    actualizarResumenSeleccion();
}

/**
 * Actualizar contadores de pedidos y bultos seleccionados
 */
function actualizarResumenSeleccion() {
    let totalBultos = 0;
    let totalSueltos = 0;
    pedidosGlobales.forEach(p => {
        if (seleccionados.has(parseInt(p.id_venta))) {
            totalBultos += parseInt(p.total_bultos || 0);
            totalSueltos += parseInt(p.total_sueltos || 0);
        }
    });

    document.getElementById('contadorSeleccionados').textContent = seleccionados.size;
    document.getElementById('contadorBultos').textContent = totalBultos;
    document.getElementById('contadorSueltos').textContent = totalSueltos;
}

/**
 * HU #2: Armar Entrega y Emitir Remitos
 */
async function armarEntrega(event) {
    event.preventDefault();

    if (seleccionados.size === 0) {
        alert('Por favor seleccioná al menos un pedido de la lista de pendientes.');
        return;
    }

    const repartidorId = document.getElementById('repartidorSelect').value;
    const fechaSalida = document.getElementById('fechaSalida').value;
    const observaciones = document.getElementById('observacionesEntrega').value;

    if (!repartidorId) {
        alert('Por favor asigná un repartidor/chofer para el viaje.');
        return;
    }

    const payload = {
        repartidor_id: parseInt(repartidorId),
        pedidos_ids: Array.from(seleccionados),
        fecha_salida: fechaSalida,
        observaciones: observaciones,
        usuario_id: 1 // Diego
    };

    try {
        const res = await fetch(`${API_BASE}/entregas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (json.error) {
            alert('Error al armar la entrega: ' + json.mensaje);
            return;
        }

        alert(json.mensaje);

        // Recargar pedidos pendientes y lista de entregas
        cargarPedidosPendientes();
        cargarEntregas();
        document.getElementById('formArmarEntrega').reset();
        document.getElementById('fechaSalida').value = new Date().toISOString().split('T')[0];

        // Abrir automáticamente el primer remito generado para ver/imprimir
        if (json.data && json.data.remitos && json.data.remitos.length > 0) {
            verRemito(json.data.remitos[0].id_remito);
        }

    } catch (err) {
        console.error(err);
        alert('Error de comunicación con el servidor.');
    }
}

/**
 * Cargar historial de viajes/entregas
 */
async function cargarEntregas() {
    const tbody = document.getElementById('tablaEntregasBody');

    try {
        const res = await fetch(`${API_BASE}/entregas`);
        const json = await res.json();

        if (!json.error && json.data) {
            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Aún no hay viajes de entrega creados.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            json.data.forEach(e => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>#ENT-${e.id_entrega.toString().padStart(4, '0')}</strong></td>
                    <td>${e.fecha_salida}</td>
                    <td><strong>${e.repartidor_nombre}</strong></td>
                    <td>${e.cantidad_pedidos} pedidos</td>
                    <td><span class="badge-bultos">${e.total_bultos} bultos</span></td>
                    <td><span class="badge-estado badge-${e.estado}">${e.estado.replace('_', ' ').toUpperCase()}</span></td>
                    <td>
                        <button class="clay-btn clay-btn-secondary" style="min-height: 38px; padding: 0.4rem 0.9rem; font-size: 0.95rem;" onclick="verDetalleEntrega(${e.id_entrega})">
                            Ver Remitos
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Ver detalle de una entrega y sus remitos
 */
async function verDetalleEntrega(idEntrega) {
    try {
        const res = await fetch(`${API_BASE}/entregas/${idEntrega}`);
        const json = await res.json();

        if (!json.error && json.data) {
            const e = json.data;
            if (e.pedidos && e.pedidos.length > 0) {
                // Abrir el remito del primer pedido de la entrega
                const primerRemito = e.pedidos.find(p => p.id_remito);
                if (primerRemito) {
                    verRemito(primerRemito.id_remito);
                } else {
                    alert(`Entrega #${idEntrega} no tiene remitos asociados.`);
                }
            }
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Mostrar Comprobante de Remito Oficial en Modal
 */
async function verRemito(idRemito) {
    try {
        const res = await fetch(`${API_BASE}/remitos/${idRemito}`);
        const json = await res.json();

        if (json.error || !json.data) {
            alert('No se pudo cargar el remito solicitado.');
            return;
        }

        const r = json.data;
        document.getElementById('remitoNumero').textContent = r.numero_remito;
        document.getElementById('remitoFecha').textContent = r.fecha_emision;
        document.getElementById('remitoChofer').textContent = r.repartidor_nombre;
        document.getElementById('remitoClienteNombre').textContent = r.cliente_nombre;
        document.getElementById('remitoClienteRazon').textContent = r.razon_social || '-';
        document.getElementById('remitoClienteCuit').textContent = r.cuit || 'Consumidor Final';
        document.getElementById('remitoClienteDireccion').textContent = r.direccion;
        document.getElementById('remitoClienteLocalidad').textContent = r.localidad;
        document.getElementById('remitoClienteZona').textContent = r.zona_nombre;

        const tbody = document.getElementById('remitoDetalleBody');
        tbody.innerHTML = '';
        (r.items || []).forEach(item => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #E2E8F0';
            tr.innerHTML = `
                <td style="padding: 0.6rem; font-weight: 700;">${item.codigo || '-'}</td>
                <td style="padding: 0.6rem;">${item.descripcion}</td>
                <td style="padding: 0.6rem; text-align: center; font-weight: 700;">${item.cantidad}</td>
                <td style="padding: 0.6rem; text-align: center;">${item.bultos} bulto(s)</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('modalRemito').style.display = 'flex';

    } catch (err) {
        console.error(err);
        alert('Error al abrir el remito.');
    }
}

/**
 * Cerrar modal
 */
function cerrarModalRemito() {
    document.getElementById('modalRemito').style.display = 'none';
}
