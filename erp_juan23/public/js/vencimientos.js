const API_URL = '/api/v1/vencimientos';
const TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.addEventListener('DOMContentLoaded', function() {
    cargarProximosAVencer();
    cargarAlertas();
});

function cargarProximosAVencer(pagina = 1, dias = 30, filtros = {}) {
    const params = new URLSearchParams({ dias, pagina, ...filtros });

    fetch(`${API_URL}/proximos?${params.toString()}`, {
        headers: {
            'Authorization': `Bearer ${obtenerToken()}`,
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            renderTablaProximos(data.datos);
            renderPaginacion(data.paginacion, 'paginacionProximos',
                (p) => cargarProximosAVencer(p, dias, filtros));
            actualizarResumen(data.resumen);
        }
    })
    .catch(err => console.error('Error:', err));
}

function cargarPorCriticidad() {
    fetch(`${API_URL}/por-criticidad`, {
        headers: {
            'Authorization': `Bearer ${obtenerToken()}`,
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            renderCriticos(data.critico);
            renderProximos(data.proximo);
            renderSeguros(data.seguro);
        }
    })
    .catch(err => console.error('Error:', err));
}

function cargarAlertas() {
    fetch(`${API_URL}/alertas`, {
        headers: {
            'Authorization': `Bearer ${obtenerToken()}`,
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            actualizarAlertas(data.alertas);
        }
    })
    .catch(err => console.error('Error:', err));
}

function renderTablaProximos(datos) {
    const tbody = document.getElementById('bodyProximos');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No hay productos próximos a vencer</td></tr>';
        return;
    }

    datos.forEach(lote => {
        const badgeClass = obtenerBadgeClass(lote.vencimiento.estado);
        const row = `
            <tr>
                <td><strong>${lote.producto.nombre}</strong><br><small>${lote.producto.codigo}</small></td>
                <td>${lote.lote.numero}</td>
                <td>${lote.lote.cantidad}</td>
                <td>${lote.vencimiento.fecha}</td>
                <td><strong>${lote.vencimiento.dias_restantes}</strong></td>
                <td><span class="${badgeClass}">${lote.vencimiento.urgencia}</span></td>
                <td>${lote.ubicacion || 'N/A'}</td>
                <td>
                    <button class="clay-btn clay-btn-sm clay-btn-secondary">
                        👁️ Ver
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function renderCriticos(data) {
    const container = document.getElementById('criticosContainer');
    container.innerHTML = `
        <div class="text-center">
            <h2 class="text-danger">${data.total}</h2>
            <p>Productos</p>
            <p class="text-muted"><small>${data.cantidad} unidades en riesgo</small></p>
        </div>
    `;
}

function renderProximos(data) {
    const container = document.getElementById('proximosContainer');
    container.innerHTML = `
        <div class="text-center">
            <h2 class="text-warning">${data.total}</h2>
            <p>Productos</p>
            <p class="text-muted"><small>${data.cantidad} unidades</small></p>
        </div>
    `;
}

function renderSeguros(data) {
    const container = document.getElementById('segurosContainer');
    container.innerHTML = `
        <div class="text-center">
            <h2 class="text-success">${data.total}</h2>
            <p>Productos</p>
            <p class="text-muted"><small>${data.cantidad} unidades</small></p>
        </div>
    `;
}

function actualizarResumen(resumen) {
    document.getElementById('total-vencidos').textContent = resumen.total_vencidos;
    document.getElementById('total-criticos').textContent = resumen.criticos_7dias;
    document.getElementById('total-proximos').textContent = resumen.proximos_30dias;
    document.getElementById('total-cantidad').textContent = resumen.total_items_en_riesgo;
}

function actualizarAlertas(alertas) {
    // Actualizar tarjetas de alerta con datos reales
}

function aplicarFiltros() {
    const dias = document.getElementById('filtro-dias').value;
    const producto = document.getElementById('filtro-producto')?.value?.trim();
    const ubicacion = document.getElementById('filtro-ubicacion')?.value?.trim();

    const filtros = {};
    if (producto) filtros.producto = producto;
    if (ubicacion) filtros.ubicacion = ubicacion;

    cargarProximosAVencer(1, parseInt(dias), filtros);
    bootstrap.Modal.getInstance(document.getElementById('filtrosModal')).hide();
}

function exportarReporte() {
    fetch(`${API_URL}/reporte`, {
        headers: {
            'Authorization': `Bearer ${obtenerToken()}`,
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.exito) {
            descargarCSV(data.datos, 'reporte-vencimientos.csv');
        }
    })
    .catch(err => console.error('Error:', err));
}

function renderPaginacion(paginacion, elementId, callback) {
    const container = document.getElementById(elementId);
    container.innerHTML = '';

    if (paginacion.total_paginas <= 1) return;

    const html = `
        <div class="d-flex justify-content-center gap-2">
            ${paginacion.pagina_actual > 1 ? `<button class="clay-btn clay-btn-sm clay-btn-secondary" onclick="callback(1)">« Primera</button>` : ''}
            ${paginacion.pagina_actual > 1 ? `<button class="clay-btn clay-btn-sm clay-btn-secondary" onclick="callback(${paginacion.pagina_actual - 1})">‹ Anterior</button>` : ''}

            <span class="clay-btn clay-btn-sm" style="cursor: default; background: #e8ecf1;">
                Página ${paginacion.pagina_actual} de ${paginacion.total_paginas}
            </span>

            ${paginacion.tiene_siguiente ? `<button class="clay-btn clay-btn-sm clay-btn-secondary" onclick="callback(${paginacion.pagina_actual + 1})">Siguiente ›</button>` : ''}
            ${paginacion.pagina_actual < paginacion.total_paginas ? `<button class="clay-btn clay-btn-sm clay-btn-secondary" onclick="callback(${paginacion.total_paginas})">Última »</button>` : ''}
        </div>
    `;

    container.innerHTML = html;
}

function obtenerBadgeClass(estado) {
    const clases = {
        'critico': 'badge-critico',
        'proximo': 'badge-proximo',
        'seguro': 'badge-seguro',
        'vencido': 'badge-critico'
    };
    return clases[estado] || 'badge-seguro';
}

function obtenerToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
           localStorage.getItem('api_token') || '';
}

function descargarCSV(datos, nombreArchivo) {
    if (datos.length === 0) return;

    const headers = Object.keys(datos[0]);
    const csv = [headers.join(',')];

    datos.forEach(fila => {
        const valores = headers.map(header => {
            let valor = fila[header];
            if (typeof valor === 'string' && valor.includes(',')) {
                valor = `"${valor}"`;
            }
            return valor;
        });
        csv.push(valores.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nombreArchivo;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
}
