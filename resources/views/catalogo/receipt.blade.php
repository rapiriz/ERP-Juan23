@extends('catalogo.layout')
@section('title', 'Solicitud recibida')
@section('content')
<section class="cat-empty"><p class="cat-eyebrow">SOLICITUD #{{ $order->id }}</p><h1>Recibimos tu selección</h1>
<p>Estado: <strong>{{ str_replace('_', ' ', $order->estado) }}</strong>. Esta solicitud no es una factura ni reserva mercadería.</p>
<p>Conservá este enlace privado para consultar su estado. No lo compartas: permite ver tu selección.</p>
<ul>@foreach($items as $item)<li>{{ $item->cantidad }} × {{ $item->nombre }} · {{ $item->codigo }}</li>@endforeach</ul>
<p>Importe de referencia: {{ $order->total_referencia === null ? 'A confirmar' : '$ '.number_format($order->total_referencia, 2, ',', '.') }}.</p>
<p>La distribuidora verificará la solicitud y te contactará por los datos indicados.</p><a class="cat-button" href="{{ route('catalogo.index') }}">Volver al catálogo</a></section>
@endsection
