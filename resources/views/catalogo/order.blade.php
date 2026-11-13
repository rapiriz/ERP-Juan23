@extends('layouts.app')
@section('title', 'Revisar pedido del catálogo')
@section('content')
<section class="page-container"><a href="{{ route('catalogo.orders.index') }}">Volver a solicitudes</a><h1>Solicitud #{{ $order->id }}</h1>
@include('partials.form-errors')
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
<section class="section-block"><h2>Contacto declarado</h2><p>{{ $order->nombre }} · {{ $order->telefono }} · {{ $order->email }}</p><p>{{ $order->observaciones }}</p><p>Estado: <strong>{{ str_replace('_',' ',$order->estado) }}</strong></p><p>El contacto es declarado por un visitante. Verificá su identidad antes de elegir una ficha de cliente.</p></section>
<div class="section-block" style="overflow-x:auto"><table><thead><tr><th>Código</th><th>Producto</th><th>Unidades individuales</th><th>Precio de referencia al solicitar</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->codigo }}</td><td>{{ $item->nombre }}</td><td>{{ $item->cantidad }}</td><td>{{ $item->precio_referencia === null ? 'A confirmar' : '$ '.number_format($item->precio_referencia,2,',','.') }}</td></tr>@endforeach</tbody></table></div>
@if(!in_array($order->estado,['convertido','rechazado']))
<form class="section-block form-grid" method="post" action="{{ route('catalogo.orders.review',$order->id) }}">@csrf
<div><label for="order-client">Ficha del cliente verificado</label><select id="order-client" name="cliente_id"><option value="">Seleccioná para convertir</option>@foreach($clients as $client)<option value="{{ $client->id }}">{{ $client->nombre }} {{ $client->apellido_razon_social }} · {{ $client->dni_cuit }}</option>@endforeach</select></div>
<div><label for="order-list">Lista de precios vigente</label><select id="order-list" name="lista"><option value="minorista">Minorista</option><option value="mayorista">Mayorista (sólo para clientes mayoristas)</option></select></div>
<div><label for="order-note">Nota de revisión / motivo del rechazo</label><textarea id="order-note" name="nota" maxlength="2000"></textarea></div>
<label><input type="checkbox" name="confirmar_revision" value="1"> Verifiqué la identidad, las cantidades y la lista. Acepto recalcular con precios vigentes al convertir a venta.</label>
<div class="form-actions"><button class="button button-secondary" name="accion" value="revisar">Marcar en revisión</button><button class="button button-secondary" name="accion" value="rechazar">Rechazar</button><button class="button button-primary" name="accion" value="convertir">Convertir a venta</button></div>
</form>
@elseif($order->venta_id)<p>Venta interna creada: #{{ $order->venta_id }}. Consultala en <a href="{{ route('ventas.historial') }}">Historial de ventas</a>.</p>@endif
<section class="section-block"><h2>Historial de revisión</h2><ul>@foreach($events as $event)<li>{{ $event->created_at }} · {{ $event->estado_nuevo }} · {{ $event->usuario_id ? 'Usuario #'.$event->usuario_id : 'Solicitud pública' }} · {{ $event->nota }}</li>@endforeach</ul></section>
</section>
@endsection
