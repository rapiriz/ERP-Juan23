const normalizeText = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase();

export function filterSales(sales, filters) {
    const search = normalizeText(filters.search).trim();

    return sales.filter((sale) => {
        const matchesSearch = !search
            || normalizeText(`${sale.cliente} ${sale.factura}`).includes(search);
        const matchesClient = !filters.clienteId
            || String(sale.cliente_id) === String(filters.clienteId);
        const matchesProduct = !filters.productoId
            || sale.detalle.some((item) => String(item.id_producto) === String(filters.productoId));
        const matchesLocality = !filters.localidad
            || sale.localidad === filters.localidad;
        const saleDate = String(sale.fecha_iso ?? sale.fecha ?? '').slice(0, 10);
        const matchesStartDate = !filters.fechaDesde || saleDate >= filters.fechaDesde;
        const matchesEndDate = !filters.fechaHasta || saleDate <= filters.fechaHasta;

        return matchesSearch
            && matchesClient
            && matchesProduct
            && matchesLocality
            && matchesStartDate
            && matchesEndDate;
    });
}

function formatCurrency(value) {
    return `$ ${Number(value).toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export function initializeSalesHistory() {
    const dataElement = document.getElementById('ventasHistorialData');
    if (!dataElement) return;

    const sales = JSON.parse(dataElement.textContent);
    const form = document.getElementById('formFiltros');
    const searchInput = document.getElementById('fTexto');
    const clientSelect = document.getElementById('fCliente');
    const productSelect = document.getElementById('fProducto');
    const localitySelect = document.getElementById('fLocalidad');
    const startDateInput = document.getElementById('fDesde');
    const endDateInput = document.getElementById('fHasta');
    const clearButton = document.getElementById('limpiarFiltros');
    const noResults = document.getElementById('sinResultados');
    const counter = document.getElementById('contadorVentas');
    const saleRows = new Map(
        Array.from(document.querySelectorAll('.hv-grupo'), (row) => [row.dataset.ventaId, row])
    );
    const filters = {
        search: '',
        clienteId: '',
        productoId: '',
        localidad: '',
        fechaDesde: '',
        fechaHasta: '',
    };

    const localities = [...new Set(sales.map((sale) => sale.localidad).filter(Boolean))]
        .sort((first, second) => first.localeCompare(second, 'es'));

    for (const locality of localities) {
        const option = document.createElement('option');
        option.value = locality;
        option.textContent = locality;
        localitySelect.append(option);
    }

    const updateFilters = () => {
        filters.search = searchInput.value;
        filters.clienteId = clientSelect.value;
        filters.productoId = productSelect.value;
        filters.localidad = localitySelect.value;
        filters.fechaDesde = startDateInput.value;
        filters.fechaHasta = endDateInput.value;

        const filteredSales = filterSales(sales, filters);
        const visibleIds = new Set(filteredSales.map((sale) => String(sale.id)));

        for (const [saleId, row] of saleRows) {
            row.hidden = !visibleIds.has(saleId);
        }

        noResults.hidden = filteredSales.length > 0;
        counter.textContent = `${filteredSales.length} ventas`;
        document.querySelector('[data-kpi="total-vendido"]').textContent = formatCurrency(
            filteredSales.reduce((sum, sale) => sum + Number(sale.total), 0)
        );
        document.querySelector('[data-kpi="descuentos"]').textContent = formatCurrency(
            filteredSales.reduce((sum, sale) => sum + Number(sale.descuento), 0)
        );
        document.querySelector('[data-kpi="en-cc"]').textContent = formatCurrency(
            filteredSales.reduce((sum, sale) => sum + (sale.cc ? Number(sale.total) : 0), 0)
        );
        document.querySelector('[data-kpi="cantidad"]').textContent = String(filteredSales.length);
    };

    searchInput.addEventListener('input', updateFilters);
    form.addEventListener('submit', (event) => event.preventDefault());
    form.querySelectorAll('select, input[type="date"]').forEach((control) => {
        control.addEventListener('change', updateFilters);
    });
    clearButton.addEventListener('click', () => {
        form.reset();
        updateFilters();
    });

    document.querySelectorAll('[data-toggle-detalle]').forEach((row) => {
        row.addEventListener('click', () => {
            const group = row.closest('.hv-grupo');
            const opened = group.classList.toggle('abierto');
            row.classList.toggle('abierta', opened);
        });
    });
}

if (typeof document !== 'undefined') {
    initializeSalesHistory();
}
