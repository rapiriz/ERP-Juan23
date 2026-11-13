const g2Admin = document.querySelector('[data-g2-role]')?.dataset.g2Role === 'admin';
/**
 * LÓGICA FRONTEND: MÓDULO ENTREGAS (HU #1 y HU #2)
 * ERP DISTRIBUIDORA JUAN XXIII
 */

const API_BASE = '/g2/api';

let pedidosGlobales = [];
let seleccionados = new Set();

document.addEventListener('DOMContentLoaded', () => {
    // Establecer fecha de hoy por defecto
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fechaSalida').value = hoy;

    // Cargar datos iniciales
    if (!g2Admin) document.querySelector('section.clay-card')?.setAttribute('hidden', '');
    if (!g2Admin) document.getElementById('btnVentasG4')?.setAttribute('hidden', '');
    cargarZonas();
    cargarRepartidores();
    if (g2Admin) cargarPedidosPendientes();
    cargarEntregas();
});

/**
 * Cargar combo de Zonas
 */
async function cargarZonas() {
    try {
        const res = await g2Fetch(`${API_BASE}/zonas`);
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
        const res = await g2Fetch(`${API_BASE}/repartidores`);
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
        const res = await g2Fetch(url);
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

            const obs = p.observaciones
                ? `<span class="col-obs-entrega" title="${p.observaciones}">${p.observaciones}</span>`
                : `<span style="color: var(--text-light); font-size: 0.8rem;">—</span>`;

            tr.innerHTML = `
                <td style="text-align: center;">
                    <input type="checkbox" class="custom-checkbox pedido-checkbox" value="${p.id_venta}" data-bultos="${bultos}" data-sueltos="${sueltos}" onchange="togglePedido(this)">
                </td>
                <td><strong style="color: var(--primary);">#PED-${p.id_venta.toString().padStart(4, '0')}</strong></td>
                <td>
                    <strong>${p.cliente_nombre}</strong>${p.razon_social ? `<br><small style="color: var(--text-muted);">${p.razon_social}</small>` : ''}
                </td>
                <td><span class="badge-zona">${p.zona_nombre}</span></td>
                <td>${p.direccion}, ${p.localidad}</td>
                <td style="text-align: center;">${cargaHtml}</td>
                <td style="text-align: center;">
                    <button class="clay-btn clay-btn-secondary clay-btn-xs" style="padding: 0.25rem 0.6rem; font-size: 0.82rem;" onclick="abrirModalArticulos(${p.id_venta})" title="Ver artículos de este pedido">
                        Ver (${itemsCount})
                    </button>
                </td>
                <td style="text-align: right; font-weight: 800; color: #1E3A8A; white-space: nowrap;">
                    $${Number(p.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}
                </td>
                <td>${obs}</td>
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
        const cantidad = parseInt(item.cantidad || 0);
        const bultos = item.bultos !== undefined ? parseInt(item.bultos) : Math.floor(cantidad / 6);

        tr.innerHTML = `
            <td style="font-weight: 700;">${item.codigo || '-'}</td>
            <td>
                <strong>${item.producto_nombre || item.producto || 'Artículo'}</strong>
                ${item.producto_descripcion ? `<br><small style="color: var(--text-muted);">${item.producto_descripcion}</small>` : ''}
            </td>
            <td style="text-align: center; font-weight: 700; color: #1E3A8A;">
                ${cantidad} un.
            </td>
            <td style="text-align: center;">
                ${bultos > 0 ? `<span class="badge-bultos">${bultos} bulto(s)</span>` : '<span style="color: var(--text-muted);">Suelto</span>'}
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
        showToast('Por favor seleccioná al menos un pedido de la lista de pendientes.', 'warning');
        return;
    }

    const repartidorId = document.getElementById('repartidorSelect').value;
    const fechaSalida = document.getElementById('fechaSalida').value;
    const observaciones = document.getElementById('observacionesEntrega').value;

    if (!repartidorId) {
        showToast('Por favor asigná un repartidor/chofer para el viaje.', 'warning');
        return;
    }

    const payload = {
        repartidor_id: parseInt(repartidorId),
        pedidos_ids: Array.from(seleccionados),
        fecha_salida: fechaSalida,
        observaciones: observaciones,
    };

    try {
        const res = await g2Fetch(`${API_BASE}/entregas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (json.error) {
            showToast('Error al armar la entrega: ' + json.mensaje, 'error');
            return;
        }

        showToast(json.mensaje, 'success');

        // Recargar pedidos pendientes y lista de entregas
        if (g2Admin) cargarPedidosPendientes();
        cargarEntregas();
        document.getElementById('formArmarEntrega').reset();
        document.getElementById('fechaSalida').value = new Date().toISOString().split('T')[0];

        // Abrir automáticamente el primer remito generado para ver/imprimir (con selector si son varios)
        if (json.data && json.data.remitos && json.data.remitos.length > 0) {
            verRemito(json.data.remitos[0].id_remito, json.data.remitos);
        }

    } catch (err) {
        console.error(err);
        showToast('Error de comunicación con el servidor.', 'error');
    }
}

/**
 * Cargar historial de viajes/entregas
 */
async function cargarEntregas() {
    const tbody = document.getElementById('tablaEntregasBody');

    try {
        const res = await g2Fetch(`${API_BASE}/entregas`);
        const json = await res.json();

        if (!json.error && json.data) {
            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Aún no hay viajes de entrega creados.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            json.data.forEach(e => {
                const tr = document.createElement('tr');
                const sueltosHtml = parseInt(e.total_sueltos || 0) > 0
                    ? ` <span class="badge-sueltos">+${e.total_sueltos} sueltas</span>`
                    : '';
                const cantPedidos = parseInt(e.cantidad_pedidos || 0);
                const textoBtnRemito = cantPedidos > 1 ? `Remitos (${cantPedidos})` : 'Remito';
                tr.innerHTML = `
                    <td><strong style="color: var(--primary);">#ENT-${e.id_entrega.toString().padStart(4, '0')}</strong></td>
                    <td style="color: var(--text-muted); font-size: 0.82rem; white-space: nowrap;">${e.fecha_salida || e.fecha_creacion}</td>
                    <td><strong>${e.repartidor_nombre}</strong></td>
                    <td style="text-align: center;">${e.cantidad_pedidos} pedidos</td>
                    <td style="text-align: center;"><span class="badge-bultos">${e.total_bultos} bultos</span>${sueltosHtml}</td>
                    <td style="text-align: center;"><span class="badge badge-${e.estado}">${e.estado.replace('_', ' ').toUpperCase()}</span></td>
                    <td style="text-align: center;">
                        <div class="acciones-btn-group">
                            <button class="clay-btn clay-btn-primary clay-btn-xs" style="padding: 0.25rem 0.65rem; font-size: 0.82rem;" onclick="abrirModalHojaRuta(${e.id_entrega})" title="Ver hoja de ruta y confirmar entregas">
                                Hoja de Ruta
                            </button>
                            <button class="clay-btn clay-btn-secondary clay-btn-xs" style="padding: 0.25rem 0.65rem; font-size: 0.82rem;" onclick="verDetalleEntrega(${e.id_entrega})" title="Ver comprobantes de remito">
                                ${textoBtnRemito}
                            </button>
                        </div>
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
 * HU #3 (Issue #124): Ver Hoja de Ruta de Reparto y gestión de pedidos
 */
async function abrirModalHojaRuta(idEntrega) {
    entregaActualId = idEntrega;
    try {
        const res = await g2Fetch(`${API_BASE}/entregas/${idEntrega}`);
        const json = await res.json();
        if (json.error || !json.data) {
            showToast('No se pudo cargar la hoja de ruta de la entrega.', 'error');
            return;
        }

        const e = json.data;
        document.getElementById('hrEntregaNumero').textContent = `#ENT-${e.id_entrega.toString().padStart(4, '0')}`;
        document.getElementById('hrChofer').textContent = e.repartidor_nombre;
        document.getElementById('hrFechaSalida').textContent = e.fecha_salida || e.fecha_creacion;

        const hrEstado = document.getElementById('hrEstadoBadge');
        hrEstado.className = `badge badge-${e.estado}`;
        hrEstado.textContent = e.estado.replace('_', ' ').toUpperCase();

        const pedidos = e.pedidos || [];
        document.getElementById('hrTotalPedidos').textContent = `${pedidos.length} pedido(s)`;

        let totalBultos = 0;
        let totalSueltos = 0;
        pedidos.forEach(p => {
            totalBultos += parseInt(p.total_bultos || 0);
            totalSueltos += parseInt(p.total_sueltos || 0);
        });
        document.getElementById('hrTotalBultos').textContent = totalSueltos > 0
            ? `${totalBultos} bultos (+${totalSueltos} sueltas)`
            : `${totalBultos} bultos`;

        const tbody = document.getElementById('hrPedidosTableBody');
        tbody.innerHTML = '';

        if (pedidos.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">No hay pedidos asignados a este viaje.</td></tr>`;
        } else {
            window._pedidosHojaRuta = {};
            pedidos.forEach(p => {
                window._pedidosHojaRuta[`${e.id_entrega}_${p.id_venta}`] = p;
                const tr = document.createElement('tr');
                const esEntregado = (p.estado_pedido_entrega === 'entregado');
                const badgeEstado = esEntregado
                    ? `<span class="badge badge-entregada">Entregado</span>`
                    : `<span class="badge badge-en_camino">En Reparto</span>`;

                const cobroInfo = esEntregado && p.monto_cobrado !== null && p.monto_cobrado !== undefined
                    ? `<br><small style="color: var(--text-muted); font-weight: 500;">$${Number(p.monto_cobrado).toLocaleString('es-AR', { minimumFractionDigits: 2 })} (${p.medio_pago || 'efectivo'})</small>`
                    : '';

                const btnConfirmar = esEntregado
                    ? `<span style="color: var(--success); font-weight: 700; font-size: 0.88rem;">Completado${cobroInfo}</span>`
                    : `<button class="clay-btn clay-btn-success" style="min-height: 32px; padding: 0.3rem 0.75rem; font-size: 0.88rem;" onclick="confirmarEntregaPedido(${e.id_entrega}, ${p.id_venta})" title="Confirmar entrega física realizada">
                        Confirmar Entrega
                       </button>`;

                const btnRemito = p.id_remito
                    ? `<button class="clay-btn clay-btn-secondary" style="min-height: 32px; padding: 0.3rem 0.75rem; font-size: 0.88rem;" onclick="verRemito(${p.id_remito})" title="Ver remito oficial">
                        Remito
                       </button>`
                    : '';

                const cargaPedido = parseInt(p.total_sueltos || 0) > 0
                    ? `<span class="badge-bultos">${p.total_bultos} b</span> <span class="badge-sueltos">+${p.total_sueltos} s</span>`
                    : `<span class="badge-bultos">${p.total_bultos} b</span>`;

                tr.innerHTML = `
                    <td><strong style="color: var(--primary);">#PED-${p.id_venta.toString().padStart(4, '0')}</strong></td>
                    <td><strong>${p.cliente_nombre}</strong></td>
                    <td>${p.direccion}, ${p.localidad} <span class="badge-zona">${p.zona_nombre}</span></td>
                    <td style="text-align: center;">${cargaPedido}</td>
                    <td style="text-align: right; font-weight: 700; color: #1E3A8A;">$${Number(p.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                    <td style="text-align: center;">${badgeEstado}</td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 0.35rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                            ${btnConfirmar}
                            ${btnRemito}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Mostrar botón para iniciar rendición si todos los pedidos ya fueron entregados
        const todosEntregados = pedidos.length > 0 && pedidos.every(p => p.estado_pedido_entrega === 'entregado');
        const btnRendicion = document.getElementById('btnIrARendicion');
        if (btnRendicion) {
            btnRendicion.style.display = todosEntregados ? 'inline-flex' : 'none';
        }

        document.getElementById('modalHojaRuta').style.display = 'flex';

    } catch (err) {
        console.error(err);
        showToast('Error de comunicación con el servidor.', 'error');
    }
}

let entregaActualId = null;

function irARendirEntrega() {
    if (!entregaActualId) return;
    window.location.href = `/rendiciones?id_entrega=${entregaActualId}`;
}

function cerrarModalHojaRuta() {
    document.getElementById('modalHojaRuta').style.display = 'none';
}

/**
 * Consulta de pedidos confirmados a la API de Ventas del Grupo 4
 */
async function consultarVentasGrupo4() {
    const btn = document.getElementById('btnVentasG4');
    if (btn) btn.disabled = true;
    showToast('Consultando API de Ventas (Grupo 4)...', 'info');

    try {
        const idZona = document.getElementById('filtroZona').value;
        const url = idZona ? `${API_BASE}/ventas/remotas?id_zona=${idZona}` : `${API_BASE}/ventas/remotas`;
        const res = await g2Fetch(url);
        const json = await res.json();

        if (json.error) {
            showToast('Error al consultar ventas: ' + json.mensaje, 'error');
        } else {
            const cant = json.data?.cantidad || 0;
            const modo = 'API de Ventas';
            showToast(`¡${cant} pedidos confirmados sincronizados (${modo})!`, 'success');
            await cargarPedidosPendientes();
        }
    } catch (err) {
        console.error(err);
        showToast('Error de conexión al consultar API de Ventas.', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

/**
 * HU #3 (Issue #124): Confirmar pedido entregado por el chofer (con medio de pago y monto cobrado/parcial)
 */
async function confirmarEntregaPedido(idEntrega, idVenta) {
    if (!window._pedidosHojaRuta) {
        window._pedidosHojaRuta = {};
    }

    let p = window._pedidosHojaRuta[`${idEntrega}_${idVenta}`] || null;

    // Si no está cargado en memoria (ej: se abrió desde Ver Remito directamente), lo buscamos
    if (!p) {
        // Intentar obtenerlo de los pedidos pendientes en memoria
        if (Array.isArray(pedidosGlobales)) {
            const pedPend = pedidosGlobales.find(item => parseInt(item.id_venta) === parseInt(idVenta));
            if (pedPend) {
                p = {
                    id_venta: pedPend.id_venta,
                    total: pedPend.total,
                    cliente_nombre: pedPend.cliente_nombre
                };
            }
        }

        // Si aún no se tiene, consultar la API de entregas para obtener el viaje y sus pedidos
        if (!p && idEntrega) {
            try {
                const res = await g2Fetch(`${API_BASE}/entregas/${idEntrega}`);
                const json = await res.json();
                if (!json.error && json.data && Array.isArray(json.data.pedidos)) {
                    json.data.pedidos.forEach(item => {
                        window._pedidosHojaRuta[`${idEntrega}_${item.id_venta}`] = item;
                    });
                    p = window._pedidosHojaRuta[`${idEntrega}_${idVenta}`] || null;
                }
            } catch (e) {
                console.warn('No se pudo precargar el pedido para la entrega:', e);
            }
        }
    }

    const total = p ? Number(p.total || 0) : 0;
    const cliente = p ? (p.cliente_nombre || `Pedido #${idVenta}`) : `Pedido #${idVenta}`;

    document.getElementById('confIdEntrega').value = idEntrega;
    document.getElementById('confIdVenta').value = idVenta;
    document.getElementById('confPedidoLabel').textContent = `#PED-${idVenta.toString().padStart(4, '0')}`;
    document.getElementById('confClienteLabel').textContent = cliente;

    const saldoEl = document.getElementById('confSaldoClienteLabel');
    if (saldoEl) {
        if (p && p.cliente_saldo !== null && p.cliente_saldo !== undefined) {
            const saldo = Number(p.cliente_saldo);
            const absFormatted = Math.abs(saldo).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (saldo < 0) {
                saldoEl.innerHTML = `<span class="badge" style="background:#FEE2E2; color:#991B1B;">Debe $${absFormatted}</span>`;
            } else if (saldo > 0) {
                saldoEl.innerHTML = `<span class="badge" style="background:#DBEAFE; color:#1E40AF;">Saldo a favor: $${absFormatted}</span>`;
            } else {
                saldoEl.innerHTML = `<span class="badge" style="background:#DCFCE7; color:#166534;">Al día ($0,00)</span>`;
            }
        } else {
            saldoEl.textContent = 'No disponible';
        }
    }

    document.getElementById('confTotalPedidoLabel').textContent = `$${total.toLocaleString('es-AR', { minimumFractionDigits: 2 })}`;
    document.getElementById('confMedioPago').value = 'efectivo';
    const inputMonto = document.getElementById('confMontoCobrado');
    inputMonto.value = total > 0 ? total.toFixed(2) : '0.00';
    if (total > 0) {
        inputMonto.max = total.toFixed(2);
    } else {
        inputMonto.removeAttribute('max');
    }
    document.getElementById('confObservaciones').value = '';

    document.getElementById('modalConfirmarEntregaCobro').style.display = 'flex';
}

function cerrarModalConfirmarEntrega() {
    document.getElementById('modalConfirmarEntregaCobro').style.display = 'none';
}

async function enviarConfirmacionEntrega(e) {
    e.preventDefault();

    const idEntrega = parseInt(document.getElementById('confIdEntrega').value, 10);
    const idVenta = parseInt(document.getElementById('confIdVenta').value, 10);
    const medioPago = document.getElementById('confMedioPago').value;
    const montoCobrado = parseFloat(document.getElementById('confMontoCobrado').value);
    const observaciones = document.getElementById('confObservaciones').value.trim();

    if (isNaN(montoCobrado) || montoCobrado < 0) {
        showToast('Ingresá un monto cobrado válido (mayor o igual a 0).', 'warning');
        return;
    }

    const btn = document.getElementById('btnSubmitConfEntrega');
    btn.disabled = true;
    btn.textContent = 'Confirmando…';

    try {
        const res = await g2Fetch(`${API_BASE}/entregas/${idEntrega}/pedidos/${idVenta}/entregado`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                medio_pago: medioPago,
                monto_cobrado: montoCobrado,
                observaciones: observaciones || null
            })
        });

        const json = await res.json();

        if (json.error) {
            showToast('Error: ' + json.mensaje, 'error');
            return;
        }

        cerrarModalConfirmarEntrega();
        showToast(json.mensaje, 'success');

        // Refrescar modal de hoja de ruta si está abierto
        abrirModalHojaRuta(idEntrega);
        // Refrescar listado de viajes y pedidos pendientes
        cargarEntregas();
        if (g2Admin) cargarPedidosPendientes();

    } catch (err) {
        console.error(err);
        showToast('Error al conectar con el servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirmar Entrega';
    }
}

/**
 * Ver detalle de una entrega y abrir sus remitos (con selector si tiene varios pedidos)
 */
async function verDetalleEntrega(idEntrega) {
    try {
        const res = await g2Fetch(`${API_BASE}/entregas/${idEntrega}`);
        const json = await res.json();

        if (!json.error && json.data) {
            const e = json.data;
            const remitos = (e.pedidos || [])
                .filter(p => p.id_remito)
                .map(p => ({
                    id_remito: p.id_remito,
                    numero_remito: p.numero_remito,
                    id_venta: p.id_venta
                }));

            if (remitos.length > 0) {
                verRemito(remitos[0].id_remito, remitos);
            } else {
                showToast(`Entrega #${idEntrega} no tiene remitos asociados.`, 'warning');
            }
        }
    } catch (err) {
        console.error(err);
    }
}

/**
 * Mostrar Comprobante de Remito Oficial en Modal con selector entre remitos del mismo viaje
 */
async function verRemito(idRemito, listaRemitos = null) {
    try {
        const res = await g2Fetch(`${API_BASE}/remitos/${idRemito}`);
        const json = await res.json();

        if (json.error || !json.data) {
            showToast('No se pudo cargar el remito solicitado.', 'error');
            return;
        }

        const r = json.data;

        // Si no se recibió la lista de remitos del viaje, consultarla para habilitar el selector
        let remitosViaje = Array.isArray(listaRemitos) ? listaRemitos : null;
        if (!remitosViaje && r.id_entrega) {
            try {
                const resEnt = await g2Fetch(`${API_BASE}/entregas/${r.id_entrega}`);
                const jsonEnt = await resEnt.json();
                if (!jsonEnt.error && jsonEnt.data && Array.isArray(jsonEnt.data.pedidos)) {
                    remitosViaje = jsonEnt.data.pedidos
                        .filter(p => p.id_remito)
                        .map(p => ({
                            id_remito: p.id_remito,
                            numero_remito: p.numero_remito,
                            id_venta: p.id_venta
                        }));
                }
            } catch (_) {
                remitosViaje = null;
            }
        }

        // Renderizar barra selectora si el viaje tiene más de un remito
        const selectorCont = document.getElementById('remitoSelectorContainer');
        const selectorBtns = document.getElementById('remitoSelectorButtons');
        if (selectorCont && selectorBtns) {
            if (remitosViaje && remitosViaje.length > 1) {
                selectorBtns.innerHTML = '';
                remitosViaje.forEach((rem, idx) => {
                    const esActual = parseInt(rem.id_remito) === parseInt(r.id_remito);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = esActual ? 'clay-btn clay-btn-primary' : 'clay-btn clay-btn-secondary';
                    btn.style.cssText = 'min-height: 34px; padding: 0.3rem 0.75rem; font-size: 0.85rem;';
                    const pedLabel = rem.id_venta ? `#PED-${rem.id_venta.toString().padStart(4, '0')}` : `Remito ${idx + 1}`;
                    btn.textContent = `${pedLabel} (${rem.numero_remito || 'R-' + rem.id_remito})`;
                    btn.onclick = () => verRemito(rem.id_remito, remitosViaje);
                    selectorBtns.appendChild(btn);
                });
                selectorCont.style.display = 'block';
            } else {
                selectorCont.style.display = 'none';
                selectorBtns.innerHTML = '';
            }
        }

        document.getElementById('remitoNumero').textContent = r.numero_remito;
        const elPedidoNum = document.getElementById('remitoPedidoNumero');
        if (elPedidoNum) {
            elPedidoNum.textContent = `#PED-${(r.id_venta || 0).toString().padStart(4, '0')}`;
        }
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
            const cant = parseInt(item.cantidad || 0);
            const bultos = parseInt(item.bultos || 0);
            const sueltas = cant % 6;
            const bultosTexto = bultos > 0
                ? `${bultos} bulto(s)${sueltas > 0 ? ` (+${sueltas} sueltas)` : ''}`
                : `Suelto (${cant} un.)`;
            tr.innerHTML = `
                <td style="padding: 0.6rem; font-weight: 700;">${item.codigo || '-'}</td>
                <td style="padding: 0.6rem;">${item.descripcion}</td>
                <td style="padding: 0.6rem; text-align: center; font-weight: 700;">${cant}</td>
                <td style="padding: 0.6rem; text-align: center;">${bultosTexto}</td>
            `;
            tbody.appendChild(tr);
        });

        // Configurar acción de entrega en el pie del remito
        const contAccion = document.getElementById('remitoAccionEntrega');
        if (contAccion) {
            if (r.estado === 'entregado' || r.estado === 'entregado_conforme') {
                contAccion.innerHTML = `<span class="badge badge-entregada" style="padding: 0.5rem 1rem; font-size: 0.95rem;">Mercadería Entregada Conforme</span>`;
            } else {
                contAccion.innerHTML = `
                    <button class="clay-btn clay-btn-success" onclick="confirmarEntregaPedido(${r.id_entrega}, ${r.id_venta}); cerrarModalRemito();">
                        Confirmar Pedido como Entregado
                    </button>
                `;
            }
        }

        document.getElementById('modalRemito').style.display = 'flex';

    } catch (err) {
        console.error(err);
        showToast('Error al abrir el remito.', 'error');
    }
}

/**
 * Cerrar modal de remito
 */
function cerrarModalRemito() {
    document.getElementById('modalRemito').style.display = 'none';
}

/**
 * Toast notifications
 */
function showToast(mensaje, tipo = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const icons = {
        success: '<svg class="icon-svg" viewBox="0 0 24 24" style="stroke:#059669"><path d="M5 12l5 5L20 7"/></svg>',
        error: '<svg class="icon-svg" viewBox="0 0 24 24" style="stroke:#DC2626"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        info: '<svg class="icon-svg" viewBox="0 0 24 24" style="stroke:#0D6EFD"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        warning: '<svg class="icon-svg" viewBox="0 0 24 24" style="stroke:#D97706"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
    };
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = `<span style="display:inline-flex;align-items:center;">${icons[tipo] || ''}</span><span>${mensaje}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 320);
    }, 3500);
}

// Cerrar modales con Escape y click fuera (respetando el modal superior activo)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modalRemito = document.getElementById('modalRemito');
        if (modalRemito && modalRemito.style.display === 'flex') {
            cerrarModalRemito();
            return;
        }
        cerrarModalArticulos();
        cerrarModalHojaRuta();
    }
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.style.display = 'none';
        }
    });
});
