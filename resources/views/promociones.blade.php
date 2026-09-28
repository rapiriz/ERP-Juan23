@extends('layouts.app')

@section('title', 'Promociones')

@section('content')
    <div class="promociones-container">

        <!-- Header principal -->
        <header class="clay-card" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h1 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;">PROMOS</h1>
                <div style="width: 1px; height: 20px; background-color: #cbd5e1;"></div>
                <form action="{{ route('promociones') }}" method="GET" style="margin: 0;">
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="clay-input"
                        placeholder="Filtrar..." aria-label="Filtrar promociones" style="width: 160px;">
                </form>
            </div>
            <div>
                <a href="#" class="clay-btn-primary">+ NUEVA PROMO</a>
            </div>
        </header>

        <!-- Tabla 1: Listado de Promociones -->
        <section class="clay-card" style="padding: 0; overflow: hidden;">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 90px;">CÓDIGO</th>
                        <th>NOMBRE DEL COMBO</th>
                        <th class="text-center" style="width: 120px;">DESCUENTO</th>
                        <th class="text-right" style="width: 280px;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Promo P001 -->
                    <tr class="promo-row {{ request('promo_id') == '1' ? 'row-selected' : '' }}"
                        onclick="window.location='{{ route('promociones', ['promo_id' => 1]) }}'">
                        <td style="color: #94a3b8; font-family: monospace;">P001</td>
                        <td><strong>Promo Limpieza</strong></td>
                        <td class="text-center"><span class="badge-discount">-15%</span></td>
                        <td class="text-right" onclick="event.stopPropagation();">
                            <div class="actions-group">
                                <button type="button" class="btn-action btn-cart">🛒 Agregar</button>
                                <button type="button" class="btn-action btn-edit">✏️ Editar</button>
                                <button type="button" class="btn-action btn-danger">🗑️ Borrar</button>
                            </div>
                        </td>
                    </tr>

                    <!-- Promo P002 -->
                    <tr class="promo-row {{ request('promo_id') == '2' ? 'row-selected' : '' }}"
                        onclick="window.location='{{ route('promociones', ['promo_id' => 2]) }}'">
                        <td style="color: #94a3b8; font-family: monospace;">P002</td>
                        <td><strong>Pack Almacén x5</strong></td>
                        <td class="text-center"><span class="badge-discount">-20%</span></td>
                        <td class="text-right" onclick="event.stopPropagation();">
                            <div class="actions-group">
                                <button type="button" class="btn-action btn-cart">🛒 Agregar</button>
                                <button type="button" class="btn-action btn-edit">✏️ Editar</button>
                                <button type="button" class="btn-action btn-danger">🗑️ Borrar</button>
                            </div>
                        </td>
                    </tr>

                    <!-- Promo P003 -->
                    <tr class="promo-row {{ request('promo_id') == '3' || !request('promo_id') ? 'row-selected' : '' }}"
                        onclick="window.location='{{ route('promociones', ['promo_id' => 3]) }}'">
                        <td style="color: #94a3b8; font-family: monospace;">P003</td>
                        <td><strong>Promo Fiesta</strong></td>
                        <td class="text-center"><span class="badge-discount">-10%</span></td>
                        <td class="text-right" onclick="event.stopPropagation();">
                            <div class="actions-group">
                                <button type="button" class="btn-action btn-cart">🛒 Agregar</button>
                                <button type="button" class="btn-action btn-edit">✏️ Editar</button>
                                <button type="button" class="btn-action btn-danger">🗑️ Borrar</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Tabla 2: Productos de la promo seleccionada -->
        <section class="clay-card" style="padding: 0; overflow: hidden;">
            <header
                style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1rem; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <span style="font-size: 0.75rem; font-weight: bold; color: #94a3b8; text-transform: uppercase;">
                    PRODUCTOS DE LA PROMO:
                    <span style="color: #1e293b; text-transform: none; font-size: 0.9rem; font-weight: 700;">
                        @if(request('promo_id') == '1') Promo Limpieza
                        @elseif(request('promo_id') == '2') Pack Almacén x5
                        @else Promo Fiesta @endif
                    </span>
                </span>
                <span style="color: var(--brand-blue); font-weight: 700; font-size: 0.85rem;">
                    @if(request('promo_id') == '1') 15% de descuento aplicado
                    @elseif(request('promo_id') == '2') 20% de descuento aplicado
                    @else 10% de descuento aplicado @endif
                </span>
            </header>

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
                    <tr>
                        <td style="color: #94a3b8; font-family: monospace;">011</td>
                        <td>Suavizante para ropa Vivere 1L</td>
                        <td class="text-center">1</td>
                        <td class="text-right price-cell">
                            <del class="old-price">$ 1.200,00</del>
                            <span class="current-price">$ 1.020,00</span>
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #1e293b;">$ 1.020,00</td>
                    </tr>
                    <tr>
                        <td style="color: #94a3b8; font-family: monospace;">008</td>
                        <td>Jabón Líquido Skip 1L</td>
                        <td class="text-center">1</td>
                        <td class="text-right price-cell">
                            <del class="old-price">$ 2.300,00</del>
                            <span class="current-price">$ 1.955,00</span>
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #1e293b;">$ 1.955,00</td>
                    </tr>
                    <tr>
                        <td style="color: #94a3b8; font-family: monospace;">009</td>
                        <td>Jabón en Polvo Skip 1kg</td>
                        <td class="text-center">1</td>
                        <td class="text-right price-cell">
                            <del class="old-price">$ 2.400,00</del>
                            <span class="current-price">$ 2.040,00</span>
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #1e293b;">$ 2.040,00</td>
                    </tr>
                </tbody>
            </table>

            <footer
                style="text-align: right; padding: 0.8rem 1rem; background-color: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 1rem; font-weight: bold;">
                Total promo: <span style="color: var(--brand-orange); font-size: 1.15rem;">$ 5.015,00</span>
            </footer>
        </section>

    </div>
@endsection