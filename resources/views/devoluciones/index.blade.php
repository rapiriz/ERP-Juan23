@extends('layouts.app')
@section('title', 'Devoluciones y notas de crédito')
@section('content')
<section class="page-heading"><p class="eyebrow">Gestión comercial</p><h1>Devoluciones y notas de crédito</h1><p>Consultá las recepciones de mercadería y los créditos asociados a cada venta.</p></section>
@if(auth()->user()->rol->value === 'administrativo')
<section class="section-block">
    <h2>Recibir una devolución</h2>
    <form id="buscar-venta-devolucion" class="toolbar-form"><div class="field"><label for="devolucion-venta">Número interno de venta</label><input id="devolucion-venta" type="number" min="1" value="{{ request('venta_id') }}" required></div><button class="button button-secondary">Consultar venta</button></form>
    <form id="registrar-devolucion" data-actor="{{ auth()->id() }}" hidden>
        <p id="devolucion-cliente"></p><p>Indicá únicamente lo recibido físicamente. La mercadería dañada o vencida queda registrada y no se incorpora al stock disponible.</p>
        <div class="table-scroll"><table><thead><tr><th>Producto</th><th>Vendidas</th><th>Ya devueltas</th><th>Recibidas ahora</th><th>Condición</th></tr></thead><tbody id="devolucion-items"></tbody></table></div>
        <div class="field"><label for="devolucion-motivo">Motivo</label><textarea id="devolucion-motivo" minlength="3" maxlength="2000" required></textarea></div>
        <button class="button button-primary" type="submit">Confirmar recepción y crédito</button>
    </form>
</section>
@endif
<p id="devoluciones-status" role="status" aria-live="polite"></p>
<section class="section-block"><h2>Historial</h2><form id="filtrar-devoluciones" class="toolbar-form"><div class="field"><label>Venta<input name="venta_id" type="number" min="1" value="{{ request('venta_id') }}"></label></div><div class="field"><label>Cliente<input name="cliente_id" type="number" min="1"></label></div><button class="button button-secondary">Filtrar</button></form>
<div class="table-scroll"><table><thead><tr><th>Devolución</th><th>Fecha</th><th>Venta</th><th>Cliente</th><th>Crédito</th><th>Detalle</th></tr></thead><tbody id="devoluciones-lista"></tbody></table></div>
<div class="toolbar-actions"><button id="dev-anterior" class="button button-secondary">Anterior</button><span id="dev-pagina"></span><button id="dev-siguiente" class="button button-secondary">Siguiente</button></div></section>
<section id="devolucion-detalle" class="section-block" hidden><h2>Detalle y nota de crédito</h2><p id="dev-datos"></p><div id="dev-renglones"></div><a id="dev-nota" class="button button-primary">Descargar nota de crédito</a></section>
@endsection
@push('scripts')<script src="{{ asset('assets/js/commercial-returns.js') }}" defer></script>@endpush
