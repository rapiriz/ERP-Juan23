document.addEventListener("DOMContentLoaded", () => {
    // 1. Variable global vacía (ya no usamos localStorage)
    let promociones = [];
    let productosTemporales = [];
    let productosDisponibles = [];

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
    const sugerenciasProductos = document.getElementById("sugerencias-productos-promo");
    const contenedorTags = document.getElementById("lista-productos-tags");
    const inputProductosOculto = document.getElementById("promo-productos-oculto");
    const contadorProductos = document.getElementById("contador-productos-promo");
    const errorProductos = document.getElementById("error-productos-promo");
    const botonAgregarProducto = document.getElementById("btn-agregar-producto");
    const resumenPrecioNormal = document.getElementById("resumen-precio-normal");
    const resumenDescuento = document.getElementById("resumen-descuento");
    const resumenPrecioPromo = document.getElementById("resumen-precio-promo");
    const errorDescuento = document.getElementById("error-descuento-promo");
    const formatoMoneda = new Intl.NumberFormat("es-AR", {
        style: "currency",
        currency: "ARS",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    let coincidenciasActuales = [];
    let indiceSugerenciaActiva = 0;

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
            if (!response.ok) throw new Error('No se pudieron cargar las promociones.');
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

    async function cargarProductosDisponibles() {
        const response = await fetch('/api/ventas/productos-simulados');
        if (!response.ok) throw new Error('No se pudieron cargar los productos disponibles.');
        productosDisponibles = await response.json();
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

            tr.addEventListener("click", (event) => {
                if (event.target.closest("button")) return;
                mostrarDetalles(promo, estaVencida, tr);
            });

            // Lógica de botones según estado
            let botonesAccion = "";
            if (promo.estado === "activa") {
                botonesAccion = `
                    <button type="button" class="btn-action btn-cart" data-promo-id="${promo.id}" title="Agregar a Venta">🛒 Agregar</button>
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

        tablaBody.querySelectorAll(".btn-cart").forEach((button) => {
            button.addEventListener("click", () => {
                const promo = promociones.find((item) => item.id === Number(button.dataset.promoId));
                if (promo) agregarPromocionAlCarrito(promo);
            });
        });
    }

    // --- MOSTRAR DETALLES ---
    function mostrarDetalles(promo, estaVencida, filaSeleccionada) {
        tituloProductos.innerText = promo.nombre;
        const detallePromo = calcularDetallePromo(promo);
        badgeDescuento.innerText = promo.tipoValor === "%"
            ? `${promo.valor}% de descuento aplicado`
            : `${formatearMoneda(detallePromo.descuentoTotal)} de descuento aplicado`;

        const filasTabla = detallePromo.lineas.map(({ producto, cantidad, precioUnitario, precioConDescuento, totalLinea }) => `
                <tr>
                    <td style="color: #94a3b8; font-family: monospace;">${producto?.codigo ?? "--"}</td>
                    <td>${producto?.nombre ?? "Producto no disponible"}</td>
                    <td class="text-center"><strong>${cantidad}</strong></td>
                    <td class="text-right">
                        <span style="display: block; color: #94a3b8; font-size: .75rem; text-decoration: line-through;">${formatearMoneda(precioUnitario)}</span>
                        <strong style="color: var(--brand-blue);">${formatearMoneda(precioConDescuento)}</strong>
                    </td>
                    <td class="text-right" style="font-weight: bold; color: #1e293b;">${formatearMoneda(totalLinea)}</td>
                </tr>
            `).join("");

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
                Total promo: <span style="color: var(--brand-blue); font-size: 1.15rem;">${formatearMoneda(detallePromo.total)}</span>
            </footer>
        `;

        tablaBody.querySelectorAll(".promo-row").forEach((row) => row.classList.remove("row-selected"));
        filaSeleccionada.classList.add("row-selected");
    }

    // --- LÓGICA DE LOS PRODUCTOS ---
    function renderizarSugerenciasProductos() {
        const busqueda = inputNuevoProd.value.trim().toLocaleLowerCase();
        sugerenciasProductos.innerHTML = "";
        coincidenciasActuales = [];
        inputNuevoProd.removeAttribute("aria-activedescendant");

        if (!busqueda) {
            sugerenciasProductos.style.display = "none";
            return;
        }

        coincidenciasActuales = productosDisponibles.filter((producto) =>
            producto.nombre.toLocaleLowerCase().includes(busqueda)
            || producto.codigo.toLocaleLowerCase().includes(busqueda)
        ).slice(0, 8);

        if (coincidenciasActuales.length === 0) {
            const sinResultados = document.createElement("span");
            sinResultados.textContent = "No se encontraron productos.";
            sinResultados.style.cssText = "padding: .65rem .8rem; color: #64748b; font-size: .85rem;";
            sugerenciasProductos.appendChild(sinResultados);
        } else {
            coincidenciasActuales.forEach((producto, index) => {
                const opcion = document.createElement("button");
                opcion.type = "button";
                opcion.id = `sugerencia-producto-${producto.id}`;
                opcion.dataset.productoId = producto.id;
                opcion.setAttribute("role", "option");
                opcion.tabIndex = -1;
                opcion.setAttribute("aria-label", `${producto.codigo}, ${producto.nombre}, ${formatearMoneda(producto.precioMin)}`);
                opcion.setAttribute("aria-selected", index === indiceSugerenciaActiva ? "true" : "false");
                opcion.style.cssText = "padding: .65rem .8rem; border: 0; border-bottom: 1px solid #e2e8f0; background: white; color: #1e293b; text-align: left; cursor: pointer; display: flex; justify-content: space-between; gap: .75rem;";
                if (index === indiceSugerenciaActiva) {
                    opcion.style.background = "#eff6ff";
                }

                const nombre = document.createElement("span");
                nombre.textContent = `${producto.codigo} · ${producto.nombre}`;
                const precio = document.createElement("strong");
                precio.textContent = formatearMoneda(producto.precioMin);
                opcion.append(nombre, precio);
                sugerenciasProductos.appendChild(opcion);
            });
            inputNuevoProd.setAttribute("aria-activedescendant", `sugerencia-producto-${coincidenciasActuales[0].id}`);
        }

        sugerenciasProductos.style.display = "flex";
    }

    inputNuevoProd.addEventListener("input", () => {
        limpiarErrorProducto();
        indiceSugerenciaActiva = 0;
        renderizarSugerenciasProductos();
    });
    sugerenciasProductos.addEventListener("click", (event) => {
        const opcion = event.target.closest("[data-producto-id]");
        if (!opcion) return;

        const producto = productosDisponibles.find((item) => item.id === Number(opcion.dataset.productoId));
        if (producto) agregarProducto(producto);
    });
    inputNuevoProd.addEventListener("keydown", (event) => {
        if (event.key === "ArrowDown" && coincidenciasActuales.length > 0) {
            event.preventDefault();
            indiceSugerenciaActiva = (indiceSugerenciaActiva + 1) % coincidenciasActuales.length;
            renderizarSugerenciasProductos();
        } else if (event.key === "ArrowUp" && coincidenciasActuales.length > 0) {
            event.preventDefault();
            indiceSugerenciaActiva = (indiceSugerenciaActiva - 1 + coincidenciasActuales.length) % coincidenciasActuales.length;
            renderizarSugerenciasProductos();
        } else if (event.key === "Enter") {
            event.preventDefault();
            if (coincidenciasActuales[indiceSugerenciaActiva]) {
                agregarProducto(coincidenciasActuales[indiceSugerenciaActiva]);
            } else if (productosTemporales.length === 0) {
                mostrarErrorProducto("Elegí un producto de la lista.");
            }
        }
    });

    window.agregarProductoArray = () => {
        const producto = coincidenciasActuales[indiceSugerenciaActiva];
        const prodCant = Number(inputCantidadProd.value);

        if (inputCantidadProd.value.trim() === "" || !Number.isInteger(prodCant) || prodCant < 1) {
            mostrarErrorProducto("La cantidad tiene que ser 1 o más.");
            inputCantidadProd.focus();
            return;
        }
        if (prodCant > 9999) {
            mostrarErrorProducto("La cantidad no puede superar 9999.");
            inputCantidadProd.focus();
            return;
        }
        if (!producto) {
            mostrarErrorProducto("Elegí un producto de la lista.");
            inputNuevoProd.focus();
            return;
        }

        agregarProducto(producto);
    };
    botonAgregarProducto.addEventListener("click", window.agregarProductoArray);

    inputCantidadProd.addEventListener("input", limpiarErrorProducto);

    function agregarProducto(producto) {
        const prodCant = Number(inputCantidadProd.value);
        if (inputCantidadProd.value.trim() === "" || !Number.isInteger(prodCant) || prodCant < 1) {
            mostrarErrorProducto("La cantidad tiene que ser 1 o más.");
            inputCantidadProd.focus();
            return;
        }
        if (prodCant > 9999) {
            mostrarErrorProducto("La cantidad no puede superar 9999.");
            inputCantidadProd.focus();
            return;
        }
        const existente = productosTemporales.find((item) => item.id_producto === producto.id);
        if (existente) {
            if (existente.cantidad + prodCant > 9999) {
                mostrarErrorProducto("La cantidad total de un producto no puede superar 9999.");
                inputCantidadProd.focus();
                return;
            }
            existente.cantidad += prodCant;
        } else {
            productosTemporales.push({
                id_producto: producto.id,
                codigo: producto.codigo,
                nombre: producto.nombre,
                cantidad: prodCant,
            });
        }
        inputNuevoProd.value = "";
        inputCantidadProd.value = 1;
        sugerenciasProductos.style.display = "none";
        coincidenciasActuales = [];
        limpiarErrorProducto();
        actualizarTagsProductos();
        inputNuevoProd.focus();
    }

    function mostrarErrorProducto(mensaje) {
        errorProductos.textContent = mensaje;
        errorProductos.style.display = "block";
    }

    function limpiarErrorProducto() {
        errorProductos.textContent = "";
        errorProductos.style.display = "none";
    }

    window.quitarProductoArray = (index) => {
        productosTemporales.splice(index, 1);
        actualizarTagsProductos();
    };

    window.cambiarCantidadProducto = (index, cambio) => {
        const producto = productosTemporales[index];
        if (!producto) return;

        producto.cantidad = Math.min(9999, Math.max(1, producto.cantidad + cambio));
        actualizarTagsProductos();
    };

    function actualizarTagsProductos() {
        contenedorTags.innerHTML = "";
        contadorProductos.textContent = productosTemporales.length;
        if (productosTemporales.length === 0) {
            contenedorTags.innerHTML =
                '<span style="color: #94a3b8; font-size: 0.85rem; margin: auto;">Todavía no agregaste productos. Buscá uno arriba para empezar.</span>';
        }

        productosTemporales.forEach((prod, index) => {
            const productoDisponible = productosDisponibles.find((item) => item.id === prod.id_producto);
            const precioUnitario = Number(productoDisponible?.precioMin) || 0;
            const subtotal = precioUnitario * prod.cantidad;
            const tag = document.createElement("div");
            tag.className = "promo-product-item";
            tag.style.cssText =
                "background: white; color: #1e293b; font-size: 0.85rem; padding: 0.45rem 0.65rem; border-radius: 8px; align-items: center; gap: 0.75rem; border: 1px solid #cbd5e1;";

            const nombre = document.createElement("span");
            nombre.style.cssText = "min-width: 0;";
            const codigo = document.createElement("strong");
            codigo.textContent = prod.codigo;
            nombre.append(codigo, document.createTextNode(` · ${prod.nombre}`));

            const precio = document.createElement("span");
            precio.style.cssText = "color: #64748b; white-space: nowrap;";
            precio.textContent = `${formatearMoneda(precioUnitario)} c/u`;

            const controles = document.createElement("div");
            controles.style.cssText = "display: inline-flex; align-items: center; gap: 0.35rem;";
            const botonMenos = crearBotonCantidad("−", `Reducir cantidad de ${prod.nombre}`, () => cambiarCantidadProducto(index, -1));
            const cantidad = document.createElement("output");
            cantidad.setAttribute("aria-label", `Cantidad de ${prod.nombre}`);
            cantidad.textContent = prod.cantidad;
            cantidad.style.cssText = "min-width: 1.5rem; text-align: center; font-weight: 700;";
            const botonMas = crearBotonCantidad("+", `Aumentar cantidad de ${prod.nombre}`, () => cambiarCantidadProducto(index, 1));
            controles.append(botonMenos, cantidad, botonMas);

            const subtotalFila = document.createElement("strong");
            subtotalFila.textContent = formatearMoneda(subtotal);
            subtotalFila.style.whiteSpace = "nowrap";

            const botonQuitar = document.createElement("button");
            botonQuitar.type = "button";
            botonQuitar.className = "btn-action btn-danger";
            botonQuitar.textContent = "×";
            botonQuitar.setAttribute("aria-label", `Quitar ${prod.nombre} de la promoción`);
            botonQuitar.addEventListener("click", () => quitarProductoArray(index));
            botonQuitar.style.cssText = "min-width: 32px; justify-content: center; padding: 0.25rem;";
            tag.append(nombre, precio, controles, subtotalFila, botonQuitar);
            contenedorTags.appendChild(tag);
        });

        inputProductosOculto.value = productosTemporales.length > 0 ? "ok" : "";
        actualizarResumenPromo();
    }

    function crearBotonCantidad(texto, ariaLabel, accion) {
        const boton = document.createElement("button");
        boton.type = "button";
        boton.className = "btn-action";
        boton.textContent = texto;
        boton.setAttribute("aria-label", ariaLabel);
        boton.style.cssText = "min-height: 32px; min-width: 32px; justify-content: center; padding: 0; background: #e2e8f0; color: #1e293b;";
        boton.addEventListener("click", accion);
        return boton;
    }

    // --- FUNCIONES DEL MODAL ---
    window.abrirModal = () => {
        formPromo.reset();
        inputId.value = "";
        productosTemporales = [];
        inputNuevoProd.value = "";
        inputCantidadProd.value = 1;
        limpiarErrorProducto();
        sugerenciasProductos.style.display = "none";
        coincidenciasActuales = [];
        actualizarTagsProductos();
        modalTitle.innerText = "Nueva Promoción";
        modal.style.display = "flex";
        inputNuevoProd.focus();
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
                mostrarErrorProducto("Elegí un producto de la lista.");
                inputNuevoProd.focus();
                return;
            }

            if (!validarLimiteDescuento()) {
                inputValorNumero.focus();
                return;
            }

            const valorIngresado = inputValorNumero.value.trim().replace(',', '.');
            const valorNumerico = Number(valorIngresado);
            if (!Number.isFinite(valorNumerico) || valorNumerico < 0) {
                alert("Ingresá un valor promocional válido.");
                inputValorNumero.focus();
                return;
            }

            if (inputTipoValor.value === '%') {
                if (valorNumerico > 100) {
                    alert('Error: El porcentaje de descuento no puede ser mayor a 100%.');
                    return;
                }
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
                valor: valorNumerico,
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
                    await response.json();
                    cerrarModal();
                    await cargarPromocionesDesdeAPI();
                    limpiarVistaPrevia();
                } else {
                    const resultado = await response.json();
                    const mensaje = Object.values(resultado.errors || {})[0]?.[0] || resultado.message || resultado.error;
                    alert(mensaje || "No se pudo guardar la promoción.");
                }
            } catch (error) {
                console.error("Error al guardar:", error);
                alert("No se pudo guardar la promoción. Revisá la conexión e intentá nuevamente.");
            }
        });
    }

    // --- EDITAR ---
    window.editarPromocion = (id) => {
        const promo = promociones.find((p) => p.id === id);
        if (!promo) return;

        formPromo.reset();
        inputId.value = promo.id;
        inputNombre.value = promo.nombre;
        inputDescripcion.value = promo.descripcion;
        inputInicio.value = promo.fechaInicio;
        inputFin.value = promo.fechaFin;
        inputTipoValor.value = promo.tipoValor;
        inputValorNumero.value = promo.valor;

        productosTemporales = promo.productos.map((producto) => ({
            id_producto: producto.id_producto,
            codigo: productosDisponibles.find((item) => item.id === producto.id_producto)?.codigo ?? "",
            nombre: producto.nombre,
            cantidad: Number(producto.cantidad),
        }));
        limpiarErrorProducto();
        sugerenciasProductos.style.display = "none";
        coincidenciasActuales = [];
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

    function formatearMoneda(valor) {
        return formatoMoneda.format(Number(valor) || 0);
    }

    function actualizarResumenPromo() {
        const promoPrevia = {
            productos: productosTemporales.map((producto) => ({
                id_producto: producto.id_producto,
                cantidad: producto.cantidad,
            })),
            tipoValor: inputTipoValor.value,
            valor: Number(inputValorNumero.value.trim().replace(",", ".")) || 0,
        };
        const detalle = calcularDetallePromo(promoPrevia);
        const precioNormal = detalle.lineas.reduce((total, linea) => total + linea.bruto, 0);

        resumenPrecioNormal.textContent = formatearMoneda(precioNormal);
        resumenDescuento.textContent = `−${formatearMoneda(detalle.descuentoTotal)}`;
        resumenPrecioPromo.textContent = formatearMoneda(detalle.total);
        validarLimiteDescuento();
    }

    inputTipoValor.addEventListener("change", actualizarResumenPromo);
    inputValorNumero.addEventListener("input", actualizarResumenPromo);
    inputValorNumero.addEventListener("change", actualizarResumenPromo);

    function validarLimiteDescuento() {
        const valor = Number(inputValorNumero.value.trim().replace(",", "."));
        const subtotal = productosTemporales.reduce((total, productoPromo) => {
            const producto = productosDisponibles.find((item) => item.id === productoPromo.id_producto);
            return total + (Number(producto?.precioMin) || 0) * productoPromo.cantidad;
        }, 0);
        const excedeLimite = Number.isFinite(valor) && (
            (inputTipoValor.value === "$" && valor > subtotal)
            || (inputTipoValor.value === "%" && valor > 100)
        );

        if (excedeLimite) {
            errorDescuento.textContent = inputTipoValor.value === "%"
                ? "El porcentaje de descuento no puede ser mayor a 100%."
                : "El descuento no puede ser mayor que el total de los productos.";
            errorDescuento.style.display = "block";
            inputValorNumero.setAttribute("aria-invalid", "true");
            inputValorNumero.setAttribute("aria-describedby", "error-descuento-promo");
            return false;
        }

        errorDescuento.textContent = "";
        errorDescuento.style.display = "none";
        inputValorNumero.removeAttribute("aria-invalid");
        inputValorNumero.removeAttribute("aria-describedby");
        return true;
    }

    function calcularDetallePromo(promo, carrito = []) {
        const lineas = promo.productos.map((item) => {
            const producto = productosDisponibles.find((disponible) => disponible.id === Number(item.id_producto));
            const itemEnCarrito = carrito.find((carritoItem) =>
                Number(carritoItem.id) === Number(item.id_producto) && !carritoItem.esPromocion
            ) ?? carrito.find((carritoItem) => Number(carritoItem.id) === Number(item.id_producto));
            const precioUnitario = Number(itemEnCarrito?.precioUnit ?? producto?.precioMin ?? 0);
            const cantidad = Number(item.cantidad) || 0;

            return {
                producto,
                cantidad,
                precioUnitario,
                bruto: precioUnitario * cantidad,
            };
        });
        const subtotalBruto = lineas.reduce((total, linea) => total + linea.bruto, 0);
        const valor = Number(promo.valor) || 0;
        const descuentoSolicitado = promo.tipoValor === "%"
            ? subtotalBruto * Math.min(Math.max(valor, 0), 100) / 100
            : Math.min(Math.max(valor, 0), subtotalBruto);

        let descuentoRestante = Math.round(descuentoSolicitado * 100);
        const lineasCalculadas = lineas.map((linea, index) => {
            const descuentoAsignado = index === lineas.length - 1
                ? descuentoRestante
                : Math.min(
                    descuentoRestante,
                    Math.round(descuentoSolicitado * linea.bruto / (subtotalBruto || 1) * 100)
                );
            descuentoRestante -= descuentoAsignado;

            const descuentoUnitario = linea.cantidad > 0
                ? Math.min(linea.precioUnitario, Math.round(descuentoAsignado / 100 / linea.cantidad * 100) / 100)
                : 0;
            const precioConDescuento = Math.max(0, linea.precioUnitario - descuentoUnitario);
            const totalLinea = Math.round(precioConDescuento * linea.cantidad * 100) / 100;

            return {
                ...linea,
                precioConDescuento,
                totalLinea,
                descuentoLinea: Math.round((linea.bruto - totalLinea) * 100) / 100,
            };
        });

        return {
            lineas: lineasCalculadas,
            total: lineasCalculadas.reduce((total, linea) => total + linea.totalLinea, 0),
            descuentoTotal: lineasCalculadas.reduce((total, linea) => total + linea.descuentoLinea, 0),
        };
    }

    function agregarPromocionAlCarrito(promo) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const fechaInicio = new Date(`${promo.fechaInicio}T00:00:00`);
        const fechaFin = new Date(`${promo.fechaFin}T00:00:00`);
        if (promo.estado !== "activa" || fechaInicio > hoy || fechaFin < hoy) {
            alert("La promoción no está vigente y no se puede agregar a una venta.");
            return;
        }

        const articulosPromo = promo.productos.map((producto) => {
            const articulo = productosDisponibles.find((item) => item.id === Number(producto.id_producto));
            return articulo ? { ...articulo, cantidad: Number(producto.cantidad) } : null;
        });
        if (articulosPromo.some((item) => !item || !Number.isInteger(item.cantidad) || item.cantidad < 1)) {
            alert("La promoción contiene productos inválidos. Editala antes de agregarla a una venta.");
            return;
        }

        let carrito;
        try {
            const guardado = JSON.parse(localStorage.getItem("pos_carrito") || "[]");
            if (!Array.isArray(guardado)) throw new Error("El carrito guardado no tiene un formato válido.");
            carrito = guardado;
            carrito.forEach((item) => {
                if (typeof item.descuentoMonto === "undefined") {
                    const porcentajeAnterior = Number(item.descuento) || 0;
                    item.descuentoMonto = (Number(item.precioUnit) || 0) * porcentajeAnterior / 100;
                    delete item.descuento;
                }
            });
        } catch (error) {
            console.error("No se pudo recuperar el carrito de ventas:", error);
            alert("No se pudo leer el carrito guardado. Abrí Ventas y revisá el carrito antes de continuar.");
            return;
        }

        const detallePromo = calcularDetallePromo(promo, carrito);
        const promocionInstanciaId = `promo-${promo.id}`;

        detallePromo.lineas.forEach((linea) => {
            const productoId = Number(linea.producto.id);
            carrito.push({
                id: productoId,
                codigo: linea.producto.codigo,
                nombre: linea.producto.nombre,
                cantidad: linea.cantidad,
                precioUnit: linea.precioUnitario,
                descuentoMonto: linea.descuentoLinea,
                descuentoInputModo: promo.tipoValor === "%" ? "porcentaje" : "monto",
                descuentoInputValor: promo.tipoValor === "%" ? Number(promo.valor) : linea.descuentoLinea,
                esPromocion: true,
                promocionId: promo.id,
                promocionNombre: promo.nombre,
                promocionInstanciaId,
            });
        });

        try {
            localStorage.setItem("pos_carrito", JSON.stringify(carrito));
        } catch (error) {
            console.error("No se pudo guardar el carrito de ventas:", error);
            alert("No se pudo guardar la promoción en el carrito. Revisá el espacio disponible del navegador.");
            return;
        }

        window.location.href = "/ventas";
    }

    // Cargamos el catálogo mock y las promociones desde sus endpoints.
    Promise.all([cargarProductosDisponibles(), cargarPromocionesDesdeAPI()])
        .catch((error) => {
            console.error("No se pudieron inicializar las promociones:", error);
            alert("No se pudieron cargar los productos de venta. Recargá la pantalla e intentá nuevamente.");
        });
});