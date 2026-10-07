document.addEventListener("DOMContentLoaded", () => {
    // 1. Variable global vacía (ya no usamos localStorage)
    let promociones = [];
    let productosTemporales = [];

    const tablaBody = document.getElementById("tabla-promociones-body");
    const formPromo = document.getElementById("form-promocion");
    const modal = document.getElementById("modal-promo");
    const modalTitle = document.getElementById("modal-title");

    // Inputs
    const inputId = document.getElementById("promo-id");
    const inputNombre = document.getElementById("promo-nombre");
    const inputDescripcion = document.getElementById("promo-descripcion");
    const inputInicio = document.getElementById("promo-inicio");
    const inputFin = document.getElementById("promo-fin");
    const inputTipoValor = document.getElementById("promo-tipo-valor");
    const inputValorNumero = document.getElementById("promo-valor-numero");

    // Inputs Productos
    const inputNuevoProd = document.getElementById("input-nuevo-producto");
    const inputCantidadProd = document.getElementById("input-cantidad-producto");
    const contenedorTags = document.getElementById("lista-productos-tags");
    const inputProductosOculto = document.getElementById("promo-productos-oculto");

    // Filtros y Vista previa
    const inputBusqueda = document.getElementById("input-busqueda");
    const checkHistorial = document.getElementById("check-historial");
    const tituloProductos = document.getElementById("titulo-productos-promo");
    const badgeDescuento = document.getElementById("badge-detalle-descuento");
    const contenedorTablaDetalle = document.getElementById("contenedor-tabla-detalle");

    // ==========================================
    // CONEXIÓN CON LA API: OBTENER DATOS (GET)
    // ==========================================
    async function cargarPromocionesDesdeAPI() {
        try {
            const response = await fetch('/api/promociones');
            const data = await response.json();

            // "Traducimos" los nombres del backend a los nombres que usa tu frontend
            promociones = data.map(p => ({
                id: p.id_promocion,
                codigo: "P" + String(p.id_promocion).padStart(3, "0"),
                nombre: p.nombre,
                descripcion: p.condiciones || p.descripcion || "",
                fechaInicio: p.vigencia_desde,
                fechaFin: p.vigencia_hasta,
                productos: p.productos || [],
                tipoValor: p.tipo_descuento === 'porcentaje' ? '%' : '$',
                valor: p.valor,
                estado: p.estado
            }));

            // Una vez descargados y adaptados, dibujamos tu tabla
            renderizarTabla();
        } catch (error) {
            console.error("Error al cargar promociones desde la API:", error);
        }
    }

    // --- RENDERIZAR TABLA PRINCIPAL ---
    function renderizarTabla() {
        if (!tablaBody) return;
        tablaBody.innerHTML = "";

        const filtroTexto = inputBusqueda.value.toLowerCase();
        const mostrarInactivas = checkHistorial.checked;

        const promosFiltradas = promociones.filter((p) => {
            const coincideTexto =
                p.nombre.toLowerCase().includes(filtroTexto) ||
                p.codigo.toLowerCase().includes(filtroTexto);
            const coincideEstado = mostrarInactivas
                ? p.estado === "inactiva"
                : p.estado === "activa";
            return coincideTexto && coincideEstado;
        });

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (promosFiltradas.length === 0) {
            tablaBody.innerHTML = `<tr><td colspan="5" class="text-center" style="color: #94a3b8; padding: 2rem;">No se encontraron promociones.</td></tr>`;
        }

        promosFiltradas.forEach((promo) => {
            const tr = document.createElement("tr");
            tr.classList.add("promo-row");

            const fechaFin = new Date(promo.fechaFin + "T00:00:00");
            const estaVencida = fechaFin < hoy;

            // Si está inactiva o vencida, se ve más clarita
            if (estaVencida || promo.estado === "inactiva")
                tr.style.opacity = "0.6";

            let badges = "";
            if (promo.estado === "inactiva") {
                badges += `<span style="background: #e2e8f0; color: #475569; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 0.5rem;">DADA DE BAJA</span>`;
            } else if (estaVencida) {
                badges += `<span style="background: #fee2e2; color: #ef4444; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.7rem; font-weight: bold; margin-left: 0.5rem;">VENCIDA</span>`;
            }

            let textoPrecio =
                promo.tipoValor === "$"
                    ? `$ ${promo.valor}`
                    : `-${promo.valor}%`;

            tr.addEventListener("click", () =>
                mostrarDetalles(promo, estaVencida),
            );

            // Lógica de botones según estado
            let botonesAccion = "";
            if (promo.estado === "activa") {
                botonesAccion = `
                    <button type="button" class="btn-action btn-cart" title="Agregar a Venta">🛒 Agregar</button>
                    <button type="button" class="btn-action btn-edit" onclick="editarPromocion(${promo.id})" title="Modificar">✏️ Editar</button>
                    <button type="button" class="btn-action btn-danger" onclick="cambiarEstado(${promo.id}, 'inactiva')" title="Dar de baja">🚫 Baja</button>
                `;
            } else {
                botonesAccion = `
                    <button type="button" class="btn-action" style="background-color: #10b981;" onclick="cambiarEstado(${promo.id}, 'activa')" title="Reactivar Promoción">✅ Reactivar</button>
                `;
            }

            tr.innerHTML = `
                <td style="color: #94a3b8; font-family: monospace;">${promo.codigo}</td>
                <td>
                    <strong>${promo.nombre}</strong><br>
                    <span style="font-size: 0.8rem; color: #64748b;">${promo.descripcion}</span>
                </td>
                <td style="font-size: 0.85rem; color: #475569;">
                    ${formatearFecha(promo.fechaInicio)} al ${formatearFecha(promo.fechaFin)}
                    ${badges}
                </td>
                <td class="text-center" style="white-space: nowrap;">
                    <span class="badge-discount">${textoPrecio}</span>
                </td>
                <td class="text-right" onclick="event.stopPropagation();">
                    <div class="actions-group">
                        ${botonesAccion}
                    </div>
                </td>
            `;
            tablaBody.appendChild(tr);
        });

        // ¡ELIMINAMOS EL LOCALSTORAGE AQUÍ!
    }

    // --- MOSTRAR DETALLES ---
    function mostrarDetalles(promo, estaVencida) {
        tituloProductos.innerText = promo.nombre;
        badgeDescuento.innerText =
            promo.tipoValor === "%"
                ? `${promo.valor}% de descuento aplicado`
                : `Precio promocional aplicado`;

        let filasTabla = "";
        promo.productos.forEach((prod, index) => {
            filasTabla += `
                <tr>
                    <td style="color: #94a3b8; font-family: monospace;">00${index + 1}</td>
                    <td>${prod.nombre || ('Producto ID ' + prod.id_producto)}</td>
                    <td class="text-center"><strong>${prod.cantidad}</strong></td>
                    <td class="text-right" style="color: var(--brand-blue); font-weight: bold;">--</td>
                    <td class="text-right" style="font-weight: bold; color: #1e293b;">--</td>
                </tr>
            `;
        });

        let alertaArriba = "";
        if (promo.estado === "inactiva") {
            alertaArriba = `<div style="padding: 1rem; background: #e2e8f0; color: #475569; font-weight: bold; text-align: center; border-bottom: 1px solid #cbd5e1;">ℹ️ Esta promoción se encuentra en el historial (dada de baja).</div>`;
        } else if (estaVencida) {
            alertaArriba = `<div style="padding: 1rem; background: #fee2e2; color: #ef4444; font-weight: bold; text-align: center; border-bottom: 1px solid #fca5a5;">⚠️ Esta promoción ya no está vigente.</div>`;
        }

        contenedorTablaDetalle.innerHTML = `
            ${alertaArriba}
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 90px;">CÓDIGO</th>
                        <th>NOMBRE</th>
                        <th class="text-center" style="width: 100px;">CANTIDAD</th>
                        <th class="text-right" style="width: 140px;">PRECIO C/DESC.</th>
                        <th class="text-right" style="width: 140px;">TOTAL LÍNEA</th>
                    </tr>
                </thead>
                <tbody>
                    ${filasTabla}
                </tbody>
            </table>
            <footer style="text-align: right; padding: 0.8rem 1rem; background-color: #f8fafc; font-size: 1rem; font-weight: bold;">
                Total promo: <span style="color: var(--brand-blue); font-size: 1.15rem;">${promo.tipoValor === "$" ? "$ " + promo.valor : "---"}</span>
            </footer>
        `;

        document
            .querySelectorAll(".promo-row")
            .forEach((row) => row.classList.remove("row-selected"));
        event.currentTarget.classList.add("row-selected");
    }

    // --- LÓGICA DE LOS PRODUCTOS ---
    window.agregarProductoArray = () => {
        const prodNombre = inputNuevoProd.value.trim();
        const prodCant = parseInt(inputCantidadProd.value) || 1;

        if (prodNombre !== "") {
            productosTemporales.push({
                nombre: prodNombre,
                cantidad: prodCant,
            });
            inputNuevoProd.value = "";
            inputCantidadProd.value = 1;
            actualizarTagsProductos();
        }
    };

    window.quitarProductoArray = (index) => {
        productosTemporales.splice(index, 1);
        actualizarTagsProductos();
    };

    function actualizarTagsProductos() {
        contenedorTags.innerHTML = "";
        if (productosTemporales.length === 0) {
            contenedorTags.innerHTML =
                '<span style="color: #94a3b8; font-size: 0.8rem; margin: auto;">No hay productos agregados</span>';
        }

        productosTemporales.forEach((prod, index) => {
            const tag = document.createElement("div");
            tag.style.cssText =
                "background: white; color: #1e293b; font-size: 0.85rem; padding: 0.4rem 0.8rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #cbd5e1;";
            tag.innerHTML = `
                <span><strong>${prod.cantidad}x</strong> ${prod.nombre}</span>
                <span style="cursor: pointer; color: #ef4444; font-weight: bold; font-size: 1.2rem; line-height: 1;" onclick="quitarProductoArray(${index})">×</span>
            `;
            contenedorTags.appendChild(tag);
        });

        inputProductosOculto.value = productosTemporales.length > 0 ? "ok" : "";
    }

    // --- FUNCIONES DEL MODAL ---
    window.abrirModal = () => {
        formPromo.reset();
        inputId.value = "";
        productosTemporales = [];
        actualizarTagsProductos();
        modalTitle.innerText = "Nueva Promoción";
        modal.style.display = "flex";
    };

    window.cerrarModal = () => {
        modal.style.display = "none";
    };

    // ==========================================
    // CONEXIÓN CON LA API: GUARDAR (POST/PUT)
    // ==========================================
    if (formPromo) {
        formPromo.addEventListener("submit", async (e) => {
            e.preventDefault();

            if (inputInicio.value > inputFin.value) {
                alert("Error: La fecha final no puede ser anterior a la fecha de inicio.");
                return;
            }

            if (productosTemporales.length === 0) {
                alert("Debes agregar al menos un producto a la promoción.");
                return;
            }

            const idActual = inputId.value;
            const metodoHTTP = idActual ? 'PUT' : 'POST';
            const urlAPI = idActual ? `/api/promociones/${idActual}` : '/api/promociones';

            // Armamos el JSON con los nombres exactos que espera nuestro backend
            const payload = {
                nombre: inputNombre.value,
                descripcion: inputDescripcion.value, // (Si se la agregas al backend luego)
                vigencia_desde: inputInicio.value,
                vigencia_hasta: inputFin.value,
                tipo_descuento: inputTipoValor.value === '%' ? 'porcentaje' : 'monto_fijo',
                valor: inputValorNumero.value,
                productos: productosTemporales
            };

            try {
                const response = await fetch(urlAPI, {
                    method: metodoHTTP,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (response.ok) {
                    const respuestaBackend = await response.json(); // Obtenemos el ID falso que generó el backend
                    cerrarModal();

                    // TRUCO MOCKING: Modificamos el arreglo local en lugar de consultar al backend estático
                    if (!idActual) {
                        // Es un POST (Creación) - Agregamos al final del arreglo
                        const nuevaPromo = respuestaBackend.promocion;
                        promociones.push({
                            id: nuevaPromo.id_promocion,
                            codigo: "P" + String(nuevaPromo.id_promocion).padStart(3, "0"),
                            nombre: nuevaPromo.nombre,
                            descripcion: inputDescripcion.value,
                            fechaInicio: nuevaPromo.vigencia_desde,
                            fechaFin: nuevaPromo.vigencia_hasta,
                            productos: nuevaPromo.productos || [],
                            tipoValor: nuevaPromo.tipo_descuento === 'porcentaje' ? '%' : '$',
                            valor: nuevaPromo.valor,
                            estado: "activa"
                        });
                    } else {
                        // Es un PUT (Edición) - Buscamos y actualizamos
                        const index = promociones.findIndex((p) => p.id === parseInt(idActual));
                        if (index !== -1) {
                            promociones[index].nombre = inputNombre.value;
                            promociones[index].descripcion = inputDescripcion.value;
                            promociones[index].fechaInicio = inputInicio.value;
                            promociones[index].fechaFin = inputFin.value;
                            promociones[index].tipoValor = inputTipoValor.value;
                            promociones[index].valor = inputValorNumero.value;
                            promociones[index].productos = [...productosTemporales];
                        }
                    }

                    // En lugar de cargarPromocionesDesdeAPI(), solo redibujamos la tabla
                    renderizarTabla();
                    limpiarVistaPrevia();
                }
            } catch (error) {
                console.error("Error al guardar:", error);
            }
        });
    }

    // --- EDITAR ---
    window.editarPromocion = (id) => {
        const promo = promociones.find((p) => p.id === id);
        if (!promo) return;

        inputId.value = promo.id;
        inputNombre.value = promo.nombre;
        inputDescripcion.value = promo.descripcion;
        inputInicio.value = promo.fechaInicio;
        inputFin.value = promo.fechaFin;
        inputTipoValor.value = promo.tipoValor;
        inputValorNumero.value = promo.valor;

        productosTemporales = JSON.parse(JSON.stringify(promo.productos));
        actualizarTagsProductos();

        modalTitle.innerText = "Modificar Promoción";
        modal.style.display = "flex";
    };

    // ==========================================
    // CONEXIÓN CON LA API: BAJA Y REACTIVAR (DELETE/PATCH)
    // ==========================================
    window.cambiarEstado = async (id, nuevoEstado) => {
        // Buscamos el índice en nuestro array local
        const index = promociones.findIndex((p) => p.id === id);
        if (index === -1) return;

        const promo = promociones[index];

        if (nuevoEstado === "activa") {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const fechaFin = new Date(promo.fechaFin + "T00:00:00");

            if (fechaFin < hoy) {
                alert("Atención: Esta promoción está vencida. Modificá las fechas de vigencia para poder reactivarla.");
                editarPromocion(id);
                return;
            }
        }

        const mensaje = nuevoEstado === "inactiva"
            ? "¿Deseás dar de baja esta promoción? Pasará al historial."
            : "¿Deseás volver a activar esta promoción?";

        if (confirm(mensaje)) {
            const urlAPI = nuevoEstado === "inactiva" ? `/api/promociones/${id}` : `/api/promociones/${id}/estado`;
            const metodoHTTP = nuevoEstado === "inactiva" ? 'DELETE' : 'PATCH';
            const cuerpo = nuevoEstado === "inactiva" ? null : JSON.stringify({ estado: 'activa' });

            try {
                const response = await fetch(urlAPI, {
                    method: metodoHTTP,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: cuerpo
                });

                if (response.ok) {
                    // TRUCO MOCKING: Actualizamos el estado en nuestra memoria local
                    promociones[index].estado = nuevoEstado;

                    // Volvemos a dibujar la tabla
                    renderizarTabla();
                    limpiarVistaPrevia();
                }
            } catch (error) {
                console.error("Error al cambiar estado:", error);
            }
        }
    };

    function limpiarVistaPrevia() {
        tituloProductos.innerText = "Seleccioná una promo";
        badgeDescuento.innerText = "";
        contenedorTablaDetalle.innerHTML =
            '<p style="padding: 1rem; color: #94a3b8; font-size: 0.9rem; margin: 0;">Hacé clic en una promoción para ver sus detalles y productos asociados.</p>';
    }

    // Filtros
    inputBusqueda.addEventListener("input", renderizarTabla);
    checkHistorial.addEventListener("change", () => {
        renderizarTabla();
        limpiarVistaPrevia();
    });

    function formatearFecha(fechaString) {
        if (!fechaString) return "";
        const partes = fechaString.split("-");
        if (partes.length !== 3) return fechaString;
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    // 2. INICIO DE LA APLICACIÓN: Llamamos a la API en lugar de localStorage
    cargarPromocionesDesdeAPI();
});