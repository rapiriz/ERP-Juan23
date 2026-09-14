<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Rendiciones - ERP Distribuidora</title>
    <style>
        :root {
            --bg-color: #EBF4FC;
            --primary-blue: #0D6EFD;
            --primary-hover: #0b5ed7;
            --text-main: #1E293B;
            --card-bg: rgba(255, 255, 255, 0.9);
            --box-bg: #0f172a;
            --box-text: #38bdf8;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 30px;
            font-size: 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            margin-bottom: 30px;
        }
        h1 {
            color: #1E3A8A;
            margin: 0 0 5px 0;
            font-size: 2rem;
        }
        p.subtitle {
            color: #64748B;
            margin: 0;
            font-size: 1.1rem;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
        }
        .clay-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .clay-card h3 {
            margin-top: 0;
            color: #1E40AF;
            font-size: 1.25rem;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 12px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 0.9rem;
            color: #334155;
        }
        input, select, textarea {
            width: 100%;
            height: 44px;
            padding: 8px 12px;
            border: 2px solid #CBD5E1;
            border-radius: 10px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background-color: #FFFFFF;
        }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid var(--primary-blue);
            outline-offset: 1px;
            border-color: var(--primary-blue);
        }
        button.clay-btn {
            background-color: var(--primary-blue);
            color: white;
            font-weight: 600;
            height: 46px;
            width: 100%;
            padding: 0 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
            transition: background-color 0.2s, transform 0.1s;
            margin-top: 10px;
        }
        button.clay-btn:hover {
            background-color: var(--primary-hover);
        }
        button.clay-btn:active {
            transform: scale(0.98);
        }
        /* Contenedor de respuesta fijo para que NUNCA rompa ni expanda la tarjeta */
        .response-container {
            margin-top: 15px;
            background: var(--box-bg);
            color: var(--box-text);
            padding: 12px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 0.85rem;
            height: 120px;
            max-height: 120px;
            overflow-y: auto;
            box-sizing: border-box;
            border: 1px solid #334155;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Panel de Control - Módulo de Rendiciones</h1>
        <p class="subtitle"> Demostración interactiva para clase</p>
    </header>

    <div class="grid-container">

        <!-- 1. Registrar Cobro Individual -->
        <div class="clay-card">
            <div>
                <h3>1. Registrar Cobro Individual</h3>
                <div class="form-group">
                    <label>ID Factura</label>
                    <input type="number" id="c_factura" value="201">
                </div>
                <div class="form-group">
                    <label>Monto ($)</label>
                    <input type="number" id="c_monto" value="15000">
                </div>
                <div class="form-group">
                    <label>Medio de Pago</label>
                    <select id="c_medio">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarCobro()">Registrar Cobro</button>
                <div id="res-cobro" class="response-container">Esperando acción...</div>
            </div>
        </div>

        <!-- 2. Registrar Rendición de Reparto (#14) -->
        <div class="clay-card">
            <div>
                <h3>2. Rendición de Reparto (#14)</h3>
                <div class="form-group">
                    <label>ID Reparto</label>
                    <input type="number" id="r_reparto" value="45">
                </div>
                <div class="form-group">
                    <label>ID Repartidor</label>
                    <input type="number" id="r_repartidor" value="12">
                </div>
                <div class="form-group">
                    <label>Observaciones</label>
                    <input type="text" id="r_obs" value="Entrega completada con éxito">
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarRendicionReparto()">Enviar Rendición</button>
                <div id="res-reparto" class="response-container">Esperando acción...</div>
            </div>
        </div>

        <!-- 3. Registrar Diferencias (#12) -->
        <div class="clay-card">
            <div>
                <h3>3. Registrar Diferencias (#12)</h3>
                <div class="form-group">
                    <label>ID Rendición</label>
                    <input type="number" id="d_rendicion" value="15">
                </div>
                <div class="form-group">
                    <label>Total Esperado ($)</label>
                    <input type="number" id="d_esperado" value="50000">
                </div>
                <div class="form-group">
                    <label>Total Rendido ($)</label>
                    <input type="number" id="d_rendido" value="48500">
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarDiferencia()">Calcular Diferencia</button>
                <div id="res-diferencia" class="response-container">Esperando acción...</div>
            </div>
        </div>

        <!-- 4. Aprobar o Rechazar (#11) -->
        <div class="clay-card">
            <div>
                <h3>4. Validar Rendición (#11)</h3>
                <div class="form-group">
                    <label>ID Rendición</label>
                    <input type="number" id="v_rendicion" value="15">
                </div>
                <div class="form-group">
                    <label>Acción</label>
                    <select id="v_accion">
                        <option value="aprobada">Aprobar</option>
                        <option value="rechazada">Rechazar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Motivo (si rechaza)</label>
                    <input type="text" id="v_motivo" value="Faltan comprobantes">
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarValidacion()">Procesar Validación</button>
                <div id="res-validacion" class="response-container">Esperando acción...</div>
            </div>
        </div>

        <!-- 5. Registrar Devoluciones (#8) -->
        <div class="clay-card">
            <div>
                <h3>5. Registrar Devolución (#8)</h3>
                <div class="form-group">
                    <label>ID Rendición / Pedido</label>
                    <input type="number" id="dev_pedido" value="305">
                </div>
                <div class="form-group">
                    <label>Cliente</label>
                    <input type="text" id="dev_cliente" value="Comercio El Amigo">
                </div>
                <div class="form-group">
                    <label>Motivo</label>
                    <input type="text" id="dev_motivo" value="Negocio cerrado">
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarDevolucion()">Registrar Devolución</button>
                <div id="res-devolucion" class="response-container">Esperando acción...</div>
            </div>
        </div>

        <!-- 6. Consultar Historial (#10) -->
        <div class="clay-card">
            <div>
                <h3>6. Historial y Filtros (#10)</h3>
                <div class="form-group">
                    <label>Filtrar por Estado</label>
                    <select id="h_estado">
                        <option value="">(Todos los estados)</option>
                        <option value="Aprobada">Aprobada</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Rechazada">Rechazada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Filtrar por Repartidor ID</label>
                    <input type="number" id="h_repartidor" placeholder="Ej: 12">
                </div>
            </div>
            <div>
                <button class="clay-btn" onclick="probarHistorial()">Consultar Historial</button>
                <div id="res-historial" class="response-container">Esperando acción...</div>
            </div>
        </div>

    </div>
</div>

<script>
    async function probarCobro() {
        const data = {
            id_factura: parseInt(document.getElementById('c_factura').value),
            monto: parseFloat(document.getElementById('c_monto').value),
            medio_pago: document.getElementById('c_medio').value
        };
        const res = await fetch('/api/v1/cobros', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        document.getElementById('res-cobro').innerText = JSON.stringify(await res.json(), null, 2);
    }

    async function probarRendicionReparto() {
        const data = {
            id_reparto: parseInt(document.getElementById('r_reparto').value),
            id_repartidor: parseInt(document.getElementById('r_repartidor').value),
            observaciones: document.getElementById('r_obs').value,
            remitos: [101, 102],
            cobros: [{ id_factura: 201, monto: 15000.00, medio_pago: "efectivo" }]
        };
        const res = await fetch('/api/v1/rendiciones', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        document.getElementById('res-reparto').innerText = JSON.stringify(await res.json(), null, 2);
    }

    async function probarDiferencia() {
        const data = {
            id_rendicion: parseInt(document.getElementById('d_rendicion').value),
            total_esperado: parseFloat(document.getElementById('d_esperado').value),
            total_rendido: parseFloat(document.getElementById('d_rendido').value),
            motivo: "Diferencia detectada en arqueo"
        };
        const res = await fetch('/api/v1/rendiciones/diferencias', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        document.getElementById('res-diferencia').innerText = JSON.stringify(await res.json(), null, 2);
    }

    async function probarValidacion() {
        const data = {
            id_rendicion: parseInt(document.getElementById('v_rendicion').value),
            accion: document.getElementById('v_accion').value,
            id_usuario_validador: 3,
            motivo_rechazo: document.getElementById('v_motivo').value
        };
        const res = await fetch('/api/v1/rendiciones/validar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        document.getElementById('res-validacion').innerText = JSON.stringify(await res.json(), null, 2);
    }

    async function probarDevolucion() {
        const data = {
            id_rendicion: 15,
            id_pedido: parseInt(document.getElementById('dev_pedido').value),
            cliente: document.getElementById('dev_cliente').value,
            motivo: document.getElementById('dev_motivo').value
        };
        const res = await fetch('/api/v1/rendiciones/devoluciones', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        document.getElementById('res-devolucion').innerText = JSON.stringify(await res.json(), null, 2);
    }

    async function probarHistorial() {
        let url = '/api/v1/rendiciones/historial';
        const estado = document.getElementById('h_estado').value;
        const rep = document.getElementById('h_repartidor').value;
        
        let params = [];
        if (estado) params.push(`estado=${estado}`);
        if (rep) params.push(`id_repartidor=${rep}`);
        if (params.length > 0) url += '?' + params.join('&');

        const res = await fetch(url);
        document.getElementById('res-historial').innerText = JSON.stringify(await res.json(), null, 2);
    }
</script>

</body>
</html>