@extends('layouts.app')
@section('title','Cobros | Finanzas')
@section('content')
@include('partials.form-errors')
<section class="page-heading"><p class="eyebrow">Cobranzas</p><h1>Cobros de rendiciones</h1><p>Aplicación individual y anulación con trazabilidad. Se necesita una caja abierta para aplicar o revertir dinero.</p></section>
<p><a class="button button-secondary" href="{{ route('rendiciones.index') }}">Volver a rendiciones</a><a class="button button-secondary" href="{{ route('caja.dia') }}">Abrir caja</a></p>
<form method="get" class="toolbar-form"><div class="field"><label for="estado">Estado individual</label><select id="estado" name="estado">@foreach(['pendiente','aplicado','rechazado','anulado','todos'] as $state)<option value="{{ $state }}" @selected($estado===$state)>{{ ucfirst($state) }}</option>@endforeach</select></div><button class="button button-primary">Consultar</button></form>
<section class="table-panel"><div class="table-scroll"><table><thead><tr><th>Cobro / rendición</th><th>Cliente / venta</th><th>Importe y medio</th><th>Estado individual</th><th>Operación</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>#{{ $item->id_rendicion_cobro }} / #{{ $item->id_rendicion }}</td><td>#{{ $item->id_cliente }} / #{{ $item->id_factura }}</td><td>$ {{ number_format($item->monto,2,',','.') }} · {{ $item->medio_pago }}</td><td>{{ ucfirst($item->estado_revision) }}<p>{{ $item->nota_revision }}</p></td><td>
@if($item->estado_revision==='pendiente' && $item->rendicion?->estado==='pendiente')
@if($item->medio_pago==='cheque')<form method="post" action="{{ url('/cobros/declarados/'.$item->id_rendicion_cobro.'/cheque') }}">@csrf
<label>Número de cheque<input name="numero_cheque" maxlength="50" value="{{ $item->datos_medio_pago['numero_cheque']??'' }}" required></label><label>Banco emisor<input name="banco_emisor" maxlength="100" value="{{ $item->datos_medio_pago['banco_emisor']??'' }}" required></label><label>Fecha de cobro<input type="date" name="fecha_cobro" value="{{ $item->datos_medio_pago['fecha_cobro']??'' }}" required></label><button class="button button-secondary">Guardar datos del cheque</button></form>@endif
<form method="post" action="{{ url('/cobros/declarados/'.$item->id_rendicion_cobro.'/aplicar') }}">@csrf<button class="button button-primary">Aplicar cobro</button></form>
@elseif($item->estado_revision==='aplicado' && $item->id_cobro_aplicado)<form method="post" action="{{ url('/cobros/'.$item->id_cobro_aplicado.'/anular') }}">@csrf<label for="motivo-{{ $item->id_rendicion_cobro }}">Motivo de anulación</label><input id="motivo-{{ $item->id_rendicion_cobro }}" name="motivo" minlength="3" maxlength="500" required><button class="button button-secondary">Anular y restablecer deuda</button></form>@elseif($item->estado_revision==='pendiente' && $item->rendicion?->estado==='aprobada') Histórico sin certificación individual: revisar antes de volver a liquidar. @else Sin operaciones disponibles @endif
</td></tr>@empty<tr><td colspan="5">No hay cobros para el filtro seleccionado.</td></tr>@endforelse
</tbody></table></div>{{ $items->links() }}</section>
@endsection
