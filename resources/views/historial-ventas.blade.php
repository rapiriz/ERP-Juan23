@extends('layouts.app')

@section('title', 'Historial de Ventas')

@push('styles')
<style>
    /* ---------- Cabecera ---------- */
    .hv-top {
        display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    }
    .hv-titulo {
        display: flex; align-items: center; gap: .75rem;
        font-size: 1.35rem; font-weight: 800; letter-spacing: .3px;
    }
    .hv-contador {
        font-size: .9rem; color: var(--muted); font-weight: 600;
        background: #F1F5F9; padding: .45rem .9rem; border-radius: 999px;
    }

    /* ---------- Filtros ---------- */
    .hv-filtros-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: .85rem;
    }
    .hv-filtros-titulo {
        display: flex; align-items: center; gap: .5rem;
        font-size: .85rem; font-weight: 700; color: var(--muted);
        text-transform: uppercase; letter-spacing: .5px;
    }
    .hv-limpiar {
        background: none; border: none; color: var(--primary);
        font-family: inherit; font-size: .85rem; font-weight: 600;
        cursor: pointer; padding: .25rem .5rem; border-radius: 8px;
        text-decoration: none;
    }
    .hv-limpiar:hover { background: #EFF6FF; }

    .hv-filtros-grid {
        display: grid; grid-template-columns: 1.2fr 1fr 1fr 1fr 1fr; gap: .75rem;
    }
    .hv-campo { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
    .hv-campo label {
        font-size: .72rem; font-weight: 700; color: var(--muted);
        text-transform: uppercase; letter-spacing: .4px;
    }
    .hv-campo input, .hv-campo select {
        width: 100%; min-height: 44px; padding: 0 .75rem;
        border: 1px solid var(--border); border-radius: 12px;
        background: #fff; font-family: inherit; font-size: .9rem; color: var(--text);
    }
    .hv-campo input:focus, .hv-campo select:focus {
        outline: 4px solid var(--primary); outline-offset: 2px; border-color: var(--primary);
    }

    /* ---------- KPIs ---------- */
    .hv-resumen { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; }
    .hv-kpi { display: flex; flex-direction: column; gap: .35rem; padding: 1rem 1.25rem; }
    .hv-kpi .label {
        font-size: .72rem; font-weight: 700; color: var(--muted);
        text-transform: uppercase; letter-spacing: .5px;
    }
    .hv-kpi .valor {
        font-size: 1.55rem; font-weight: 800;
        font-variant-numeric: tabular-nums; line-height: 1.1;
    }
    .hv-kpi .valor.azul    { color: var(--primary); }
    .hv-kpi .valor.naranja { color: #EA580C; }
    .hv-kpi .valor.rojo    { color: var(--danger); }

    /* ---------- Tabla ---------- */
    .hv-tabla { padding: 0; overflow: hidden; display: flex; flex-direction: column; min-height: 320px; }
    .hv-tabla-header {
        display: grid;
        grid-template-columns: 220px 120px 1.3fr 1.1fr 120px 130px 120px 40px;
        gap: .5rem; padding: .9rem 1.25rem;
        font-size: .72rem; font-weight: 700; color: var(--muted);
        text-transform: uppercase; letter-spacing: .5px;
        border-bottom: 1px solid var(--border);
    }
    .hv-tabla-body { flex: 1; overflow-y: auto; }

    .hv-grupo { border-bottom: 1px solid #F1F5F9; }

    .hv-row {
        display: grid;
        grid-template-columns: 220px 120px 1.3fr 1.1fr 120px 130px 120px 40px;
        gap: .5rem; align-items: center;
        padding: .85rem 1.25rem; font-size: .92rem;
        cursor: pointer; transition: background .12s;
    }
    .hv-row:hover { background: #F8FAFC; }
    .hv-row.abierta { background: #EFF6FF; }
    .hv-row .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
    .hv-row .factura { font-weight: 700; color: var(--primary); }
    .hv-row .usuario {
        font-size: .85rem; color: var(--muted);
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .hv-row .fecha { display: flex; flex-direction: column; line-height: 1.25; }
    .hv-row .fecha .hora { font-size: .78rem; color: var(--muted); }

    .hv-toggle {
        justify-self: center; background: none; border: 1px solid var(--border);
        width: 30px; height: 30px; border-radius: 8px; cursor: pointer;
        color: var(--muted); font-size: .8rem; line-height: 1;
        display: grid; place-items: center; transition: all .15s;
    }
    .hv-toggle:hover { background: #EFF6FF; border-color: var(--primary); color: var(--primary); }
    .hv-row.abierta .hv-toggle { transform: rotate(180deg); background: var(--primary); color: #fff; border-color: var(--primary); }

    /* ---------- Badges ---------- */
    .badge {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .65rem; border-radius: 999px;
        font-size: .78rem; font-weight: 700; white-space: nowrap;
    }
    .badge-cc             { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
    .badge-efectivo       { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
    .badge-transferencia  { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
    .badge-tarjeta        { background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF; }
    .badge-vacio          { color: #CBD5E1; font-weight: 600; }

    /* Estados */
    .badge-estado { font-size: .72rem; }
    .estado-pendiente  { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .estado-confirmada { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }
    .estado-pagada     { background: #DCFCE7; color: #166534; border: 1px solid #86EFAC; }
    .estado-facturada  { background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE; }
    .estado-cancelada  { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

    /* ---------- Panel de detalle expandible ---------- */
    .hv-detalle {
        background: #F8FAFC;
        border-top: 1px dashed var(--border);
        padding: 1rem 1.25rem 1.25rem 3rem;
        display: none;
    }
    .hv-grupo.abierto .hv-detalle { display: block; }

    .hv-detalle-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: .75rem;
        font-size: .8rem; color: var(--muted); font-weight: 600;
        text-transform: uppercase; letter-spacing: .5px;
    }
    .hv-detalle-header .obs {
        text-transform: none; letter-spacing: 0;
        font-weight: 500; font-style: italic;
        color: #64748B; max-width: 60%;
        text-align: right;
    }

    .hv-detalle-tabla {
        width: 100%; border-collapse: collapse; background: #fff;
        border-radius: 12px; overflow: hidden;
        box-shadow: 0 2px 6px rgba(30,58,138,.05);
    }
    .hv-detalle-tabla th {
        text-align: left; font-size: .72rem; color: var(--muted);
        text-transform: uppercase; letter-spacing: .5px;
        padding: .55rem .85rem; background: #F1F5F9;
        border-bottom: 1px solid var(--border);
    }
    .hv-detalle-tabla th.num, .hv-detalle-tabla td.num { text-align: right; }
    .hv-detalle-tabla td {
        padding: .55rem .85rem; font-size: .88rem;
        border-bottom: 1px solid #F1F5F9;
    }
    .hv-detalle-tabla tr:last-child td { border-bottom: none; }
    .hv-detalle-tabla td.num { font-variant-numeric: tabular-nums; }

    .hv-detalle-vacio {
        padding: 1rem; text-align: center;
        font-size: .88rem; color: var(--muted);
        background: #fff; border-radius: 12px;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 1100px) {
        .hv-filtros-grid { grid-template-columns: 1fr 1fr; }
        .hv-resumen { grid-template-columns: 1fr 1fr; }
        .hv-tabla { overflow-x: auto; }
        .hv-tabla-header, .hv-row { min-width: 1100px; }
        .hv-detalle { min-width: 1100px; }
    }
    @media (max-width: 720px) {
        .hv-filtros-grid { grid-template-columns: 1fr; }
        .hv-resumen { grid-template-columns: 1fr; }
    }
    .hv-row .factura {
        font-weight: 700;
        color: var(--primary);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: block;
        max-width: 100%;
    }
</style>
@endpush

@section('content')

    {{-- Cabecera --}}
    <div class="clay-card hv-top">
        <div class="hv-titulo">
            <span class="icono">🧾</span>
            HISTORIAL DE VENTAS
        </div>
        <span class="hv-contador">{{ $kpis['cantidad'] }} ventas</span>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('ventas.historial') }}" class="clay-card" id="formFiltros">
        <div class="hv-filtros-header">
            <span class="hv-filtros-titulo">🔽 Filtros</span>
            <a href="{{ route('ventas.historial') }}" class="hv-limpiar">Limpiar filtros</a>
        </div>

        <div class="hv-filtros-grid">
            <div class="hv-campo">
                <label for="fTexto">Buscar (cliente / #)</label>
                <input type="text" id="fTexto" name="texto" value="{{ $filtros['texto'] }}" placeholder="Texto...">
            </div>
            <div class="hv-campo">
                <label for="fCliente">👤 Cliente</label>
                <select id="fCliente" name="cliente">
                    <option value="">Todos</option>
                    @foreach ($clientesFiltro as $c)
                        <option value="{{ $c['id'] }}" @selected($filtros['cliente'] == $c['id'])>
                            {{ $c['nombre'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="hv-campo">
                <label for="fProducto">📦 Producto</label>
                <select id="fProducto" name="producto">
                    <option value="">Todos</option>
                @foreach ($productosFiltro as $p)
                    <option value="{{ $p['id_producto'] }}" @selected($filtros['producto'] == $p['id_producto'])>
                        {{ $p['nombre'] }}
                    </option>
                @endforeach
                </select>
            </div>
            <div class="hv-campo">
                <label for="fDesde">📅 Desde</label>
                <input type="date" id="fDesde" name="desde" value="{{ $filtros['desde'] }}">
            </div>
            <div class="hv-campo">
                <label for="fHasta">📅 Hasta</label>
                <input type="date" id="fHasta" name="hasta" value="{{ $filtros['hasta'] }}">
            </div>
        </div>
    </form>

    {{-- KPIs --}}
    <div class="hv-resumen">
        <div class="clay-card hv-kpi">
            <span class="label">Total vendido</span>
            <span class="valor azul">$ {{ number_format($kpis['total_vendido'], 2, ',', '.') }}</span>
        </div>
        <div class="clay-card hv-kpi">
            <span class="label">Descuentos otorgados</span>
            <span class="valor naranja">$ {{ number_format($kpis['descuentos'], 2, ',', '.') }}</span>
        </div>
        <div class="clay-card hv-kpi">
            <span class="label">En cuenta corriente</span>
            <span class="valor rojo">$ {{ number_format($kpis['en_cc'], 2, ',', '.') }}</span>
        </div>
        <div class="clay-card hv-kpi">
            <span class="label">Ventas realizadas</span>
            <span class="valor azul">{{ $kpis['cantidad'] }}</span>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="clay-card hv-tabla">
        <div class="hv-tabla-header">
            <span>Factura</span>
            <span>Fecha</span>
            <span>Cliente</span>
            <span>Usuario</span>
            <span style="text-align:right;">Total</span>
            <span>Método de pago</span>
            <span>Estado</span>
            <span></span>
        </div>

        <div class="hv-tabla-body">
            @forelse ($ventas as $v)
                <div class="hv-grupo" data-venta-id="{{ $v['id'] }}">
                    <div class="hv-row" data-toggle-detalle>
                        <span class="factura" title="{{ $v['factura'] }}">{{ $v['factura'] }}</span>

                        <span class="fecha">
                            {{ $v['fecha'] }}
                        </span>

                        <span>{{ $v['cliente'] }}</span>

                        <span class="usuario" title="{{ $v['usuario'] }}">{{ $v['usuario'] }}</span>

                        <span class="num">$ {{ number_format($v['total'], 2, ',', '.') }}</span>

                        <span>
                            @php
                                $badgeMetodo = match($v['metodo']) {
                                    'Cuenta Corriente' => 'badge-cc',
                                    'Efectivo'         => 'badge-efectivo',
                                    'Transferencia'    => 'badge-transferencia',
                                    default            => 'badge-tarjeta',
                                };
                            @endphp
                            <span class="badge {{ $badgeMetodo }}">{{ $v['metodo'] }}</span>
                        </span>

                        <span>
                            <span class="badge badge-estado estado-{{ $v['estado'] }}">
                                {{ ucfirst($v['estado']) }}
                            </span>
                        </span>

                        <button type="button" class="hv-toggle" aria-label="Ver detalle">▾</button>
                    </div>

                    <div class="hv-detalle">
                        <div class="hv-detalle-header">
                            <span>Productos vendidos ({{ count($v['detalle']) }})</span>
                            @if (!empty($v['observaciones']))
                                <span class="obs">📝 {{ $v['observaciones'] }}</span>
                            @endif
                        </div>

                        @if (count($v['detalle']))
                            <table class="hv-detalle-tabla">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">Código</th>
                                        <th>Producto</th>
                                        <th class="num" style="width:90px;">Cantidad</th>
                                        <th class="num" style="width:130px;">Precio unit.</th>
                                        <th class="num" style="width:110px;">Descuento</th>
                                        <th class="num" style="width:130px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($v['detalle'] as $d)
                                        <tr>
                                            <td>{{ $d['codigo'] }}</td>
                                            <td>{{ $d['producto'] }}</td>
                                            <td class="num">{{ $d['cantidad'] }}</td>
                                            <td class="num">$ {{ number_format($d['precio_unitario'], 2, ',', '.') }}</td>
                                            <td class="num">
                                                @if ($d['descuento'] > 0)
                                                    - $ {{ number_format($d['descuento'], 2, ',', '.') }}
                                                @else
                                                    <span class="badge-vacio">—</span>
                                                @endif
                                            </td>
                                            <td class="num">$ {{ number_format($d['subtotal'], 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="hv-detalle-vacio">Esta venta no tiene detalle cargado.</div>
                        @endif
                    </div>
                </div>
            @empty
                <div style="padding:2rem; text-align:center; color:var(--muted);">
                    No se encontraron ventas con los filtros aplicados.
                </div>
            @endforelse
        </div>
    </div>

@endsection

@push('scripts')
<script>
    // ---------- Toggle de detalle ----------
    document.querySelectorAll('[data-toggle-detalle]').forEach(row => {
        row.addEventListener('click', () => {
            const grupo = row.closest('.hv-grupo');
            const abierto = grupo.classList.toggle('abierto');
            row.classList.toggle('abierta', abierto);
        });
    });

    // ---------- Auto-submit de filtros con debounce ----------
    const form = document.getElementById('formFiltros');
    const fTexto = document.getElementById('fTexto');
    let timer = null;

    fTexto.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.submit(), 500);
    });

    ['fCliente', 'fProducto', 'fDesde', 'fHasta'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => form.submit());
    });
</script>
@endpush
