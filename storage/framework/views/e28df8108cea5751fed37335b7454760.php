<?php $__env->startSection('title', 'Punto de Venta'); ?>

<?php $__env->startPush('styles'); ?>
<style>
    /* ---------- Cabecera de venta ---------- */
    .venta-top {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 1rem;
        align-items: stretch;
    }

    .factura-box { display: flex; flex-direction: column; gap: .25rem; }
    .factura-box .label {
        font-size: .72rem;
        font-weight: 700;
        color: var(--muted);
        letter-spacing: .5px;
        text-transform: uppercase;
    }
    .factura-box .cliente {
        font-size: 1.15rem;
        font-weight: 700;
    }
    .factura-box .cuit { font-size: .9rem; color: var(--muted); }
    .factura-box .cuit a { color: var(--primary); font-weight: 600; text-decoration: none; }

    .lista-toggle {
        display: flex;
        gap: .5rem;
        align-items: center;
    }
    .lista-btn {
        min-height: 48px;
        padding: 0 1.1rem;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: #fff;
        font-family: inherit;
        font-size: .9rem;
        font-weight: 600;
        cursor: pointer;
        color: var(--text);
    }
    .lista-btn.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .acciones-cliente {
        display: flex;
        flex-direction: column;
        gap: .5rem;
        justify-content: center;
    }
    .acciones-cliente .clay-btn-secondary,
    .acciones-cliente .clay-btn-primary {
        width: 100%;
        justify-content: flex-start;
        font-size: .85rem;
        padding: 0 1rem;
    }

    /* ---------- Buscador ---------- */
    .buscador {
        position: relative;
        margin-top: 1rem;
    }
    .buscador input {
        padding-left: 2.75rem;
        font-size: 1rem;
    }
    .buscador .ico {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
        pointer-events: none;
    }

    /* ---------- Tabla del carrito ---------- */
    .carrito {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 380px;
        padding: 0;
        overflow: hidden;
    }
    .carrito-header {
        display: grid;
        grid-template-columns: 90px 1fr 100px 120px 90px 130px;
        gap: .5rem;
        padding: .9rem 1.25rem;
        font-size: .72rem;
        font-weight: 700;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .5px;
        border-bottom: 1px solid var(--border);
    }
    .carrito-body {
        flex: 1;
        overflow-y: auto;
        padding: .5rem 0;
    }
    .carrito-row {
        display: grid;
        grid-template-columns: 90px 1fr 100px 120px 90px 130px;
        gap: .5rem;
        align-items: center;
        padding: .6rem 1.25rem;
        font-size: .95rem;
        border-bottom: 1px solid #F1F5F9;
    }
    .carrito-row input {
        width: 100%;
        min-height: 38px;
        padding: 0 .5rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-family: inherit;
        font-size: .9rem;
        text-align: right;
    }
    .carrito-row .num { text-align: right; font-variant-numeric: tabular-nums; }
    .carrito-row .quitar {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 1.1rem;
        padding: .25rem;
    }

    .carrito-vacio {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94A3B8;
        gap: .75rem;
        padding: 3rem 0;
    }
    .carrito-vacio .icono {
        font-size: 3rem;
        opacity: .5;
    }

    /* ---------- Footer ---------- */
    .carrito-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border);
        background: #F8FAFC;
        font-size: .9rem;
        color: var(--muted);
    }
    .total-box {
        display: flex;
        align-items: baseline;
        gap: .75rem;
    }
    .total-box .label { font-size: .95rem; font-weight: 600; color: var(--text); }
    .total-box .monto {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text);
        font-variant-numeric: tabular-nums;
    }

    /* ---------- Barra de acciones inferior ---------- */
    .acciones-finales {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: .75rem;
        margin-top: 1rem;
    }
    .acciones-finales button {
        min-height: 56px;
        font-size: 1rem;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>

    
    <div class="clay-card venta-top">
        <div class="factura-box">
            <span class="label">Emitir factura a:</span>
            <span class="cliente">Consumidor Final</span>
            <span class="cuit">
                CUIT: 11.111.111-1 &nbsp;·&nbsp;
                <a href="#">Consumidor Final</a>
            </span>
            <span class="cuit">Domicilio/Localidad, Localidad</span>
        </div>

        <div class="lista-toggle">
            <button type="button" class="lista-btn active" data-lista="minorista">
                Lista 1 — Minorista
            </button>
            <button type="button" class="lista-btn" data-lista="mayorista">
                Lista 2 — Mayorista
            </button>
        </div>

        <div class="acciones-cliente">
            <button type="button" class="clay-btn-secondary">
                🔍 BUSCAR CLIENTE
            </button>
            <button type="button" class="clay-btn-primary">
                👤 NUEVO CLIENTE (F1)
            </button>
        </div>
    </div>

    
    <div class="clay-card buscador" style="padding: .5rem 1rem;">
        <span class="ico">🔍</span>
        <input
            type="text"
            id="buscadorProductos"
            class="clay-input"
            placeholder="Buscar producto por nombre o código..."
            autocomplete="off"
        >
        <div id="sugerencias" style="
            position:absolute; left:1rem; right:1rem; top:100%;
            background:#fff; border:1px solid var(--border); border-radius:14px;
            box-shadow: 0 8px 24px rgba(30,58,138,.12);
            max-height:280px; overflow-y:auto; z-index:10; display:none;">
        </div>
    </div>
    <br>
    <br>
    <br>
    
    <div class="clay-card carrito">
        <div class="carrito-header">
            <span>Cód.</span>
            <span>Descripción</span>
            <span style="text-align:right;">Cantidad</span>
            <span style="text-align:right;">Precio U.</span>
            <span style="text-align:right;">Desc.</span>
            <span style="text-align:right;">Subtotal</span>
        </div>

        <div class="carrito-body" id="carritoBody">
            
        </div>

        <div class="carrito-vacio" id="carritoVacio">
            <div class="icono">🛒</div>
            <p style="margin:0;">Carrito vacío — busque un producto</p>
        </div>

        <div class="carrito-footer">
            <span id="resumenItems">0 art. · 0 unid.</span>
            <div class="total-box">
                <span class="label">Total</span>
                <span class="monto" id="totalMonto">$ 0,00</span>
            </div>
        </div>
    </div>

    
    <div class="acciones-finales">
        <button type="button" class="clay-btn-primary" id="btnCobrar" disabled>
            🧾 COBRAR
        </button>
        <button type="button" class="clay-btn-secondary" id="btnLimpiar">
            🗑 LIMPIAR
        </button>
        <button type="button" class="clay-btn-primary" id="btnDescuentos">
            % DESCUENTOS
        </button>
        <button type="button" class="clay-btn-danger" id="btnCerrarCaja">
            🔒 CERRAR CAJA
        </button>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Datos que vienen del controlador (por ahora hardcodeados en VentaController)
    const PRODUCTOS = <?php echo json_encode($productos, 15, 512) ?>;

    // ---------- Estado ----------
    let listaActual = 'minorista'; // o 'mayorista'
    const carrito = []; // { id, codigo, nombre, cantidad, precioUnit, descuento }

    const $ = (id) => document.getElementById(id);

    // ---------- Buscador ----------
    const input = $('buscadorProductos');
    const panelSug = $('sugerencias');

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        if (q.length < 1) { panelSug.style.display = 'none'; return; }

        const coincidencias = PRODUCTOS.filter(p =>
            p.nombre.toLowerCase().includes(q) ||
            p.codigo.toLowerCase().includes(q)
        ).slice(0, 8);

        if (!coincidencias.length) {
            panelSug.innerHTML = '<div style="padding:.75rem 1rem;color:#94A3B8;">Sin resultados</div>';
        } else {
            panelSug.innerHTML = coincidencias.map(p => `
                <div class="sugerencia-item" data-id="${p.id}"
                     style="padding:.7rem 1rem; cursor:pointer; border-bottom:1px solid #F1F5F9;">
                    <strong>${p.codigo}</strong> · ${p.nombre}
                    <span style="float:right;color:#64748B;">
                        $${Number(p.precioMin).toLocaleString('es-AR')}
                    </span>
                </div>
            `).join('');
        }
        panelSug.style.display = 'block';

        panelSug.querySelectorAll('.sugerencia-item').forEach(el => {
            el.addEventListener('click', () => {
                agregarProducto(Number(el.dataset.id));
                input.value = '';
                panelSug.style.display = 'none';
                input.focus();
            });
        });
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const q = input.value.trim().toLowerCase();
            const p = PRODUCTOS.find(p =>
                p.codigo.toLowerCase() === q || p.nombre.toLowerCase() === q
            );
            if (p) {
                agregarProducto(p.id);
                input.value = '';
                panelSug.style.display = 'none';
            }
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.buscador')) panelSug.style.display = 'none';
    });

    // ---------- Carrito ----------
    function precioDe(p) {
        return listaActual === 'mayorista' ? p.precioMay : p.precioMin;
    }

    function agregarProducto(id) {
        const p = PRODUCTOS.find(x => x.id === id);
        if (!p) return;

        const existente = carrito.find(x => x.id === id);
        if (existente) {
            existente.cantidad += 1;
        } else {
            carrito.push({
                id: p.id,
                codigo: p.codigo,
                nombre: p.nombre,
                cantidad: 1,
                precioUnit: precioDe(p),
                descuento: 0
            });
        }
        render();
    }

    function quitarProducto(id) {
        const i = carrito.findIndex(x => x.id === id);
        if (i >= 0) carrito.splice(i, 1);
        render();
    }

    function render() {
        const body = $('carritoBody');
        const vacio = $('carritoVacio');
        const btnCobrar = $('btnCobrar');

        if (!carrito.length) {
            body.innerHTML = '';
            vacio.style.display = 'flex';
            $('resumenItems').textContent = '0 art. · 0 unid.';
            $('totalMonto').textContent = '$ 0,00';
            btnCobrar.disabled = true;
            return;
        }

        vacio.style.display = 'none';

        body.innerHTML = carrito.map(item => {
            const subtotal = item.cantidad * item.precioUnit * (1 - item.descuento / 100);
            return `
                <div class="carrito-row" data-id="${item.id}">
                    <span>${item.codigo}</span>
                    <span>${item.nombre}</span>
                    <input type="number" min="1" value="${item.cantidad}"
                           data-campo="cantidad" data-id="${item.id}">
                    <span class="num">$${Number(item.precioUnit).toLocaleString('es-AR')}</span>
                    <input type="number" min="0" max="100" value="${item.descuento}"
                           data-campo="descuento" data-id="${item.id}">
                    <span class="num">$${subtotal.toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                    <button class="quitar" data-quitar="${item.id}" title="Quitar">✕</button>
                </div>
            `;
        }).join('');

        // Listeners para cantidad y descuento
        body.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('change', (e) => {
                const id = Number(e.target.dataset.id);
                const campo = e.target.dataset.campo;
                const item = carrito.find(x => x.id === id);
                if (!item) return;

                let val = Number(e.target.value) || 0;
                if (campo === 'cantidad' && val < 1) val = 1;
                if (campo === 'descuento') {
                    if (val < 0) val = 0;
                    if (val > 100) val = 100;
                }
                item[campo] = val;
                render();
            });
        });

        body.querySelectorAll('[data-quitar]').forEach(btn => {
            btn.addEventListener('click', () => quitarProducto(Number(btn.dataset.quitar)));
        });

        // Resumen y total
        const totalUnid = carrito.reduce((s, x) => s + x.cantidad, 0);
        $('resumenItems').textContent = `${carrito.length} art. · ${totalUnid} unid.`;

        const total = carrito.reduce((s, x) =>
            s + x.cantidad * x.precioUnit * (1 - x.descuento / 100), 0);

        $('totalMonto').textContent = '$ ' + total.toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        btnCobrar.disabled = false;

        // Guardar en localStorage (mismo criterio que el prototipo)
        localStorage.setItem('pos_carrito', JSON.stringify(carrito));
    }

    // ---------- Lista minorista / mayorista ----------
    document.querySelectorAll('.lista-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.lista-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            listaActual = btn.dataset.lista;

            // Recalcular precios de todo el carrito según la lista elegida
            carrito.forEach(item => {
                const p = PRODUCTOS.find(x => x.id === item.id);
                if (p) item.precioUnit = precioDe(p);
            });
            render();
        });
    });

    // ---------- Botones finales ----------
    $('btnLimpiar').addEventListener('click', () => {
        if (!carrito.length) return;
        if (confirm('¿Vaciar el carrito?')) {
            carrito.length = 0;
            localStorage.removeItem('pos_carrito');
            render();
        }
    });

    $('btnCobrar').addEventListener('click', () => {
        // TODO: POST a /ventas para guardar VENTA + DETALLE_VENTA
        alert('Cobro registrado (placeholder). Total: ' + $('totalMonto').textContent);
    });

    $('btnDescuentos').addEventListener('click', () => {
        alert('Panel de descuentos — a implementar');
    });

    $('btnCerrarCaja').addEventListener('click', () => {
        alert('Cierre de caja — a implementar');
    });

    // ---------- Recuperar carrito al cargar ----------
    (function restaurar() {
        const guardado = localStorage.getItem('pos_carrito');
        if (guardado) {
            try {
                const items = JSON.parse(guardado);
                items.forEach(x => carrito.push(x));
            } catch (e) {}
        }
        render();
    })();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\nicol\Desktop\PPS3Proyecto\resources\views/ventas.blade.php ENDPATH**/ ?>