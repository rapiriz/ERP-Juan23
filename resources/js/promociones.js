document.addEventListener("DOMContentLoaded", () => {
    // 1. Datos simulados. Ahora incluyen "estado" y los productos tienen "cantidad"
    let promociones = JSON.parse(
        localStorage.getItem("erp_promociones_mock"),
    ) || [
        {
            id: 1,
            codigo: "P001",
            nombre: "Promo Limpieza",
            descripcion: "Descuento especial en artículos de limpieza.",
            fechaInicio: "2026-10-01",
            fechaFin: "2026-10-31",
            productos: [
                { nombre: "Suavizante Vivere 1L", cantidad: 1 },
                { nombre: "Jabón Líquido Skip 1L", cantidad: 1 },
            ],
            tipoValor: "%",
            valor: "15",
            estado: "activa",
        },
        {
            id: 2,
            codigo: "P002",
            nombre: "Pack Almacén x5",
            descripcion: "Llevando 5 productos seleccionados.",
            fechaInicio: "2026-10-10",
            fechaFin: "2026-11-10",
            productos: [
                { nombre: "Fideos", cantidad: 2 },
                { nombre: "Arroz", cantidad: 2 },
                { nombre: "Puré de tomate", cantidad: 1 },
            ],
            tipoValor: "$",
            valor: "4.500,00",
            estado: "activa",
        },
    ];

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
    const inputCantidadProd = document.getElementById(
        "input-cantidad-producto",
    );
    const contenedorTags = document.getElementById("lista-productos-tags");
    const inputProductosOculto = document.getElementById(
        "promo-productos-oculto",
    );

    // Filtros y Vista previa
    const inputBusqueda = document.getElementById("input-busqueda");
    const checkHistorial = document.getElementById("check-historial");
    const tituloProductos = document.getElementById("titulo-productos-promo");
    const badgeDescuento = document.getElementById("badge-detalle-descuento");
    const contenedorTablaDetalle = document.getElementById(
        "contenedor-tabla-detalle",
    );

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

        localStorage.setItem(
            "erp_promociones_mock",
            JSON.stringify(promociones),
        );
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
                    <td>${prod.nombre}</td>
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

    // --- LÓGICA DE LOS PRODUCTOS (Ahora con cantidad) ---
    window.agregarProductoArray = () => {
        const prodNombre = inputNuevoProd.value.trim();
        const prodCant = parseInt(inputCantidadProd.value) || 1;

        if (prodNombre !== "") {
            productosTemporales.push({
                nombre: prodNombre,
                cantidad: prodCant,
            });
            inputNuevoProd.value = "";
            inputCantidadProd.value = 1; // Resetea a 1
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

    // --- GUARDAR O MODIFICAR ---
    if (formPromo) {
        formPromo.addEventListener("submit", (e) => {
            e.preventDefault();

            // Validar que las fechas tengan sentido
            if (inputInicio.value > inputFin.value) {
                alert(
                    "Error: La fecha final no puede ser anterior a la fecha de inicio.",
                );
                return; // Corta la ejecución para que no se guarde
            }

            if (productosTemporales.length === 0) {
                alert("Debes agregar al menos un producto a la promoción.");
                return;
            }

            const idActual = inputId.value;
            const valorIngresado = inputValorNumero.value.trim();

            if (idActual) {
                const index = promociones.findIndex(
                    (p) => p.id === parseInt(idActual),
                );
                if (index !== -1) {
                    promociones[index].nombre = inputNombre.value;
                    promociones[index].descripcion = inputDescripcion.value;
                    promociones[index].fechaInicio = inputInicio.value;
                    promociones[index].fechaFin = inputFin.value;
                    promociones[index].productos = [...productosTemporales]; // Array de objetos
                    promociones[index].tipoValor = inputTipoValor.value;
                    promociones[index].valor = valorIngresado;
                }
            } else {
                promociones.push({
                    id: Date.now(),
                    codigo:
                        "P" + String(promociones.length + 1).padStart(3, "0"),
                    nombre: inputNombre.value,
                    descripcion: inputDescripcion.value,
                    fechaInicio: inputInicio.value,
                    fechaFin: inputFin.value,
                    productos: [...productosTemporales],
                    tipoValor: inputTipoValor.value,
                    valor: valorIngresado,
                    estado: "activa", // Por defecto activa
                });
            }

            cerrarModal();
            renderizarTabla();
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

        productosTemporales = JSON.parse(JSON.stringify(promo.productos)); // Copia profunda
        actualizarTagsProductos();

        modalTitle.innerText = "Modificar Promoción";
        modal.style.display = "flex";
    };

    // --- BAJA LÓGICA (Cambiar estado) ---
    window.cambiarEstado = (id, nuevoEstado) => {
        const mensaje =
            nuevoEstado === "inactiva"
                ? "¿Deseás dar de baja esta promoción? Pasará al historial."
                : "¿Deseás volver a activar esta promoción?";

        if (confirm(mensaje)) {
            const index = promociones.findIndex((p) => p.id === id);
            if (index !== -1) {
                promociones[index].estado = nuevoEstado;
                renderizarTabla();
                limpiarVistaPrevia();
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

    renderizarTabla();
});
