@extends('layouts.app')

@section('title', 'Cuentas Corrientes')

@push('styles')
<style>
    .saldo-page { display: flex; flex-direction: column; min-height: 0; }
    .saldo-heading {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .8rem 1rem;
    }
    .saldo-heading h1 { margin: 0; font-size: 1.05rem; font-weight: 800; }
    .saldo-heading .ico { color: var(--primary); }
    .saldo-layout {
        display: grid;
        grid-template-columns: minmax(230px, 30%) minmax(0, 1fr);
        gap: 1rem;
        flex: 1;
        min-height: 500px;
    }
    .clientes-panel { padding: .65rem 0; overflow: hidden; }
    .clientes-search { padding: 0 .65rem .55rem; }
    .clientes-search input { min-height: 42px; font-size: .88rem; }
    .clientes-list { border-top: 1px solid var(--border); }
    .cliente-item {
        display: block;
        width: 100%;
        padding: .75rem .9rem;
        border: 0;
        border-bottom: 1px solid #f1f5f9;
        border-left: 3px solid transparent;
        background: transparent;
        color: var(--text);
        text-align: left;
        cursor: pointer;
        font: inherit;
    }
    .cliente-item:hover { background: #f8fafc; }
    .cliente-item.active {
        border-left-color: var(--primary);
        background: #fff7ed;
    }
    .cliente-nombre { display: block; font-weight: 600; font-size: .88rem; }
    .cliente-cuit { display: block; margin-top: .15rem; color: #94a3b8; font-size: .75rem; }
    .cliente-saldo { display: block; margin-top: .35rem; font-size: .85rem; font-weight: 700; }
    .saldo-negativo { color: #ff2938; }
    .saldo-positivo { color: #00a63e; }
    .saldo-cero { color: #94a3b8; }
    .cliente-no-encontrado { padding: 1rem; color: var(--muted); font-size: .88rem; }
    .detalle-panel { display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
    .detalle-vacio {
        display: flex;
        flex: 1;
        min-height: 360px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .7rem;
        color: #cbd5e1;
        text-align: center;
    }
    .detalle-vacio .icono { font-size: 2.6rem; }
    .detalle-vacio p { margin: 0; }
    .resumen-cuenta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
    }
    .resumen-identidad { min-width: 0; }
    .resumen-identidad strong { display: block; margin-bottom: .2rem; }
    .resumen-identidad span { display: block; color: var(--muted); font-size: .83rem; }
    .resumen-monto { flex: 0 0 auto; text-align: right; }
    .resumen-monto .label {
        display: block;
        color: #94a3b8;
        font-size: .65rem;
        letter-spacing: .5px;
        text-transform: uppercase;
    }
    .resumen-monto strong {
        display: block;
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-variant-numeric: tabular-nums;
        line-height: 1.3;
    }
    .resumen-monto span:last-child { display: block; font-size: .8rem; }
    .pago-form {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: .75rem;
        align-items: center;
        padding: .75rem;
        border: 1px solid #fecaca;
        border-radius: 14px;
        background: #fff5f5;
    }
    .pago-form.sin-deuda { border-color: var(--border); background: #f8fafc; }
    .pago-controls { display: flex; gap: .65rem; min-width: 0; }
    .pago-controls input { min-width: 0; min-height: 42px; background: #fff; }
    .pago-controls button { min-height: 42px; padding: 0 1rem; white-space: nowrap; }
    .pago-titulo { grid-column: 1 / -1; color: var(--danger); font-size: .85rem; font-weight: 700; }
    .pago-form.sin-deuda .pago-titulo { color: var(--muted); }
    .pago-feedback { grid-column: 1 / -1; margin: 0; font-size: .82rem; }
    .pago-feedback.success { color: #008a35; }
    .pago-feedback.error { color: var(--danger); }
    .movimientos-card { padding: 0; overflow: hidden; }
    .movimientos-title {
        padding: .8rem 1rem;
        border-bottom: 1px solid var(--border);
        color: #94a3b8;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .45px;
        text-transform: uppercase;
    }
    .movimientos-scroll { overflow-x: auto; }
    .movimientos-table { width: 100%; min-width: 560px; border-collapse: collapse; }
    .movimientos-table th {
        padding: .6rem .75rem;
        border-bottom: 1px solid var(--border);
        color: #94a3b8;
        font-size: .65rem;
        letter-spacing: .35px;
        text-align: left;
        text-transform: uppercase;
    }
    .movimientos-table td {
        padding: .65rem .75rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: .82rem;
    }
    .movimientos-table tbody tr:last-child td { border-bottom: 0; }
    .movimientos-table .fecha { color: var(--muted); white-space: nowrap; }
    .movimientos-table .importe,
    .movimientos-table .saldo { text-align: right; white-space: nowrap; font-weight: 700; }
    .movimientos-vacios { padding: 1.25rem; color: var(--muted); text-align: center; font-size: .88rem; }
    @media (max-width: 760px) {
        .saldo-layout { grid-template-columns: 1fr; min-height: 0; }
        .clientes-panel { max-height: 280px; }
        .resumen-cuenta { align-items: flex-start; flex-direction: column; }
        .resumen-monto { text-align: left; }
    }
    @media (max-width: 520px) {
        .pago-form { grid-template-columns: 1fr; }
        .pago-titulo { grid-column: auto; }
        .pago-controls { flex-direction: column; }
        .pago-controls button { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="saldo-page">
    <header class="clay-card saldo-heading">
        <span class="ico" aria-hidden="true">♙</span>
        <h1>CUENTAS CORRIENTES</h1>
    </header>

    <div class="saldo-layout">
        <aside class="clay-card clientes-panel" aria-label="Listado de clientes">
            <div class="clientes-search">
                <input id="buscarCliente" class="clay-input" type="search"
                    placeholder="Buscar cliente..." aria-label="Buscar cliente por nombre o CUIT">
            </div>
            <div class="clientes-list" id="listaClientes"></div>
        </aside>

        <section class="detalle-panel" aria-label="Detalle de cuenta">
            <div class="clay-card detalle-vacio" id="detalleVacio">
                <span class="icono" aria-hidden="true">♧</span>
                <p>Seleccione un cliente de la lista</p>
            </div>

            <div id="detalleCuenta" hidden>
                <header class="clay-card resumen-cuenta">
                    <div class="resumen-identidad">
                        <strong id="nombreCliente"></strong>
                        <span id="datosCliente"></span>
                        <span id="domicilioCliente"></span>
                    </div>
                    <div class="resumen-monto">
                        <span class="label">Saldo actual</span>
                        <strong id="saldoActual"></strong>
                        <span id="estadoSaldo"></span>
                    </div>
                </header>

                <form class="pago-form" id="formPago">
                    <label class="pago-titulo" id="tituloPago" for="montoPago">Registrar pago de deuda</label>
                    <div class="pago-controls">
                        <input id="montoPago" class="clay-input" type="number" min="0.01" step="0.01"
                            inputmode="decimal" placeholder="Monto a pagar..." aria-label="Monto a pagar" required>
                        <button class="clay-btn-primary" id="btnPagar" type="submit">✓ PAGAR</button>
                    </div>
                    <p class="pago-feedback" id="pagoFeedback" role="status" aria-live="polite" hidden></p>
                </form>

                <section class="clay-card movimientos-card" aria-label="Movimientos de cuenta">
                    <header class="movimientos-title">Movimientos de cuenta</header>
                    <div class="movimientos-scroll">
                        <table class="movimientos-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Venta</th>
                                    <th style="text-align:right;">Monto</th>
                                    <th style="text-align:right;">Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="tablaMovimientos"></tbody>
                        </table>
                        <div class="movimientos-vacios" id="movimientosVacios" hidden>
                            Este cliente todavía no tiene movimientos.
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const CLIENTES = @json($clientes);
    const URL_PAGOS = @json(route('saldo.pagos', ['cliente' => '__CLIENTE__']));
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const moneda = new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
    let clienteSeleccionado = null;

    function saldoDe(cliente) {
        return cliente.movimientos.reduce((total, movimiento) => total + Number(movimiento.monto), 0);
    }

    function formatoSaldo(monto) {
        return `$ ${moneda.format(monto)}`;
    }

    function formatoMovimiento(monto) {
        return `${monto > 0 ? '+' : ''}$ ${moneda.format(monto)}`;
    }

    function claseSaldo(monto) {
        if (monto < 0) return 'saldo-negativo';
        if (monto > 0) return 'saldo-positivo';
        return 'saldo-cero';
    }

    function escaparHtml(valor) {
        return String(valor).replace(/[&<>"']/g, caracter => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[caracter]);
    }

    function renderClientes(filtro = '') {
        const consulta = filtro.trim().toLocaleLowerCase('es-AR');
        const clientesVisibles = CLIENTES.filter(cliente =>
            `${cliente.nombre} ${cliente.cuit}`.toLocaleLowerCase('es-AR').includes(consulta)
        );

        $('listaClientes').innerHTML = clientesVisibles.length
            ? clientesVisibles.map(cliente => {
                const saldo = saldoDe(cliente);
                return `
                    <button type="button"
                        class="cliente-item ${clienteSeleccionado?.id === cliente.id ? 'active' : ''}"
                        data-cliente-id="${cliente.id}" aria-pressed="${clienteSeleccionado?.id === cliente.id}">
                        <span class="cliente-nombre">${escaparHtml(cliente.nombre)}</span>
                        <span class="cliente-cuit">${escaparHtml(cliente.cuit)}</span>
                        <span class="cliente-saldo ${claseSaldo(saldo)}">${formatoSaldo(saldo)}</span>
                    </button>
                `;
            }).join('')
            : '<p class="cliente-no-encontrado">No se encontraron clientes.</p>';
    }

    function renderMovimientos() {
        let saldoAcumulado = 0;
        const movimientos = [...clienteSeleccionado.movimientos]
            .sort((a, b) => a.fecha.localeCompare(b.fecha));

        $('tablaMovimientos').innerHTML = movimientos.map(movimiento => {
            saldoAcumulado += Number(movimiento.monto);
            return `
                <tr>
                    <td class="fecha">${escaparHtml(movimiento.fecha)}</td>
                    <td>${escaparHtml(movimiento.descripcion)}</td>
                    <td>${movimiento.venta_id ? `#${escaparHtml(movimiento.venta_id)}` : '—'}</td>
                    <td class="importe ${claseSaldo(movimiento.monto)}">${formatoMovimiento(Number(movimiento.monto))}</td>
                    <td class="saldo ${claseSaldo(saldoAcumulado)}">${formatoSaldo(saldoAcumulado)}</td>
                </tr>
            `;
        }).join('');

        $('movimientosVacios').hidden = movimientos.length > 0;
    }

    function renderDetalle() {
        const saldo = saldoDe(clienteSeleccionado);
        const hayDeuda = saldo < 0;

        $('detalleVacio').hidden = true;
        $('detalleCuenta').hidden = false;
        $('nombreCliente').textContent = clienteSeleccionado.nombre;
        $('datosCliente').textContent = `${clienteSeleccionado.cuit} · ${clienteSeleccionado.condicion}`;
        $('domicilioCliente').textContent = clienteSeleccionado.domicilio;
        $('saldoActual').textContent = formatoSaldo(saldo);
        $('saldoActual').className = claseSaldo(saldo);
        $('estadoSaldo').textContent = hayDeuda ? 'Deuda pendiente' : saldo > 0 ? 'Saldo a favor' : 'Sin deuda pendiente';
        $('estadoSaldo').className = claseSaldo(saldo);
        $('formPago').classList.toggle('sin-deuda', !hayDeuda);
        $('tituloPago').textContent = hayDeuda ? 'Registrar pago de deuda' : 'No hay deuda pendiente';
        $('montoPago').max = hayDeuda ? Math.abs(saldo).toFixed(2) : '';
        $('montoPago').disabled = !hayDeuda;
        $('btnPagar').disabled = !hayDeuda;
        $('pagoFeedback').hidden = true;
        $('pagoFeedback').textContent = '';
        renderMovimientos();
        renderClientes($('buscarCliente').value);
    }

    $('listaClientes').addEventListener('click', evento => {
        const boton = evento.target.closest('[data-cliente-id]');
        if (!boton) return;
        clienteSeleccionado = CLIENTES.find(cliente => cliente.id === Number(boton.dataset.clienteId));
        $('montoPago').value = '';
        renderDetalle();
    });

    $('buscarCliente').addEventListener('input', evento => {
        renderClientes(evento.target.value);
    });

    $('formPago').addEventListener('submit', async evento => {
        evento.preventDefault();
        if (!clienteSeleccionado) return;

        const monto = Number($('montoPago').value);
        const deudaPendiente = Math.abs(Math.min(saldoDe(clienteSeleccionado), 0));
        const feedback = $('pagoFeedback');

        if (!Number.isFinite(monto) || monto <= 0 || monto > deudaPendiente) {
            feedback.textContent = `Ingresá un monto mayor a cero y no superior a ${formatoSaldo(deudaPendiente)}.`;
            feedback.className = 'pago-feedback error';
            feedback.hidden = false;
            return;
        }

        const boton = $('btnPagar');
        boton.disabled = true;
        feedback.textContent = 'Registrando pago...';
        feedback.className = 'pago-feedback';
        feedback.hidden = false;

        try {
            const respuesta = await fetch(URL_PAGOS.replace('__CLIENTE__', clienteSeleccionado.id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ monto })
            });
            const resultado = await respuesta.json();

            if (!respuesta.ok) {
                const mensaje = resultado.errors?.monto?.[0] || resultado.message || 'No se pudo registrar el pago.';
                throw new Error(mensaje);
            }

            clienteSeleccionado.movimientos.push(resultado.movimiento);
            $('montoPago').value = '';
            renderDetalle();
            feedback.textContent = 'Pago registrado en el historial.';
            feedback.className = 'pago-feedback success';
            feedback.hidden = false;
        } catch (error) {
            feedback.textContent = error.message || 'No se pudo registrar el pago.';
            feedback.className = 'pago-feedback error';
            feedback.hidden = false;
        } finally {
            boton.disabled = !clienteSeleccionado || saldoDe(clienteSeleccionado) >= 0;
        }
    });

    renderClientes();
</script>
@endpush
