@extends('layouts.app')
@section('title', 'Pedidos del catálogo')
@section('content')
<section class="page-container"><h1>Pedidos del catálogo</h1><p>Solicitudes pendientes de verificación. No son ventas, no reservan stock y no generan deuda.</p>
<form class="section-block" method="get"><label for="order-state">Estado</label><select id="order-state" name="estado"><option value="">Todos</option>@foreach(['solicitado','en_revision','convertido','rechazado'] as $state)<option value="{{ $state }}" @selected(request('estado') === $state)>{{ str_replace('_',' ',$state) }}</option>@endforeach</select><button class="button button-primary">Filtrar</button></form>
<div class="section-block" style="overflow-x:auto"><table><thead><tr><th>Solicitud</th><th>Contacto</th><th>Fecha</th><th>Referencia</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>#{{ $order->id }}</td><td>{{ $order->nombre }}</td><td>{{ $order->created_at }}</td><td>{{ $order->total_referencia === null ? 'A confirmar' : '$ '.number_format($order->total_referencia,2,',','.') }}</td><td>{{ str_replace('_',' ',$order->estado) }}</td><td><a href="{{ route('catalogo.orders.show',$order->id) }}">Revisar solicitud</a></td></tr>@empty<tr><td colspan="6">No hay solicitudes para este filtro.</td></tr>@endforelse
</tbody></table></div>{{ $orders->withQueryString()->links() }}</section>
@endsection
