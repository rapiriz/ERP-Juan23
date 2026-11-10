@extends('layouts.app')
@section('title', 'Lotes y vencimientos')
@section('content')
<section class="page-container" data-lots-app data-list="{{ route('lotes.data') }}" data-store="{{ route('lotes.store') }}" data-base="{{ url('/inventario/lotes') }}">
    <header class="page-header"><div><p class="eyebrow">INVENTARIO</p><h1>Lotes y vencimientos</h1><p>Controlá la mercadería por lote. Las unidades vencidas de lotes controlados no están disponibles para venta.</p></div><a class="button button-secondary" href="{{ route('inventario.index') }}">Volver al inventario</a></header>
    <p class="section-block">Los lotes históricos pendientes de revisión se conservan sin descontar ni sumar stock. Adoptalos sólo después de verificar sus cantidades físicas. Las alertas se calculan con la fecha actual al consultar.</p>
    <form data-lot-filters class="section-block form-grid">
        <div><label for="lot-q">Producto, código o lote</label><input id="lot-q" name="q" maxlength="100"></div>
        <div><label for="lot-state">Estado</label><select id="lot-state" name="estado"><option value="todos">Todos</option><option value="proximo">Próximos a vencer</option><option value="vencido">Vencidos</option><option value="vigente">Vigentes</option><option value="consumido">Consumidos</option><option value="pendiente_revision">Históricos pendientes de revisión</option></select></div>
        <div><label for="lot-days">Ventana de alerta</label><select id="lot-days" name="dias"><option value="7">7 días</option><option value="15">15 días</option><option value="30" selected>30 días</option><option value="90">90 días</option></select></div>
        <button class="button button-primary" type="submit">Consultar</button>
    </form>
    <p data-lot-status role="status" aria-live="polite"></p>
    <div class="section-block" style="overflow-x:auto"><table><thead><tr><th>Producto</th><th>Lote</th><th>Vencimiento</th><th>Actual / inicial</th><th>Estado</th><th>Trazabilidad</th><th>Acciones</th></tr></thead><tbody data-lot-rows></tbody></table></div>
    <div class="form-actions"><button class="button button-secondary" type="button" data-lot-prev>Anterior</button><span data-lot-page></span><button class="button button-secondary" type="button" data-lot-next>Siguiente</button></div>
    @if(auth()->user()->rol->value === 'administrativo')
    <section class="section-block"><h2>Registrar lote</h2><form data-lot-create class="form-grid">
        @csrf
        <div><label for="lot-product">Producto</label><select id="lot-product" name="id_producto" required><option value="">Seleccioná</option>@foreach($products as $product)<option value="{{ $product->id_producto }}">{{ $product->codigo }} · {{ $product->nombre }}</option>@endforeach</select></div>
        <div><label for="lot-number">Número de lote</label><input id="lot-number" name="nro_lote" required maxlength="100"></div>
        <div><label for="lot-expiry">Fecha de vencimiento</label><input id="lot-expiry" name="fecha_vencimiento" type="date" required></div>
        <div><label for="lot-quantity">Cantidad en unidades individuales</label><input id="lot-quantity" name="cantidad" type="number" min="1" max="2147483647" step="1" required></div>
        <div><label for="lot-ingress">Tratamiento del stock</label><select id="lot-ingress" name="asociar_ingreso" required><option value="1">Ingreso nuevo: sumar estas unidades al inventario</option><option value="0">Estas unidades ya están en inventario: sólo asignar lote</option></select></div>
        <p>No registres el mismo ingreso dos veces. El segundo modo exige que las unidades existentes todavía no estén asignadas a otro lote controlado.</p>
        <button class="button button-primary" type="submit">Guardar lote</button>
    </form></section>
    @endif
</section>
<script src="{{ asset('assets/js/lotes.js') }}" defer></script>
@endsection
