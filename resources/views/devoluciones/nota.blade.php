<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#17324d;font-size:11px}h1{color:#075be6}table{width:100%;border-collapse:collapse;margin-top:20px}td,th{padding:10px;border-bottom:1px solid #d8e0e8;text-align:left}small{color:#57677a}
</style></head><body><h1>PyB Pigüé · Nota de crédito</h1><h2>{{ $note->numero }}</h2><p>Comprobante interno, sin validez fiscal.</p>
<p>Fecha: {{ $note->created_at }} · Venta #{{ $note->venta_id }} · Devolución #{{ $return->id }}</p>
<p>Cliente: {{ $client->nombre }} {{ $client->apellido_razon_social }} · {{ $client->dni_cuit }}</p><p>Motivo: {{ $return->motivo }}</p>
<table><thead><tr><th>Producto</th><th>Cantidad recibida</th><th>Condición</th><th>Precio original</th><th>Crédito neto</th></tr></thead><tbody>
@foreach($items as $item)<tr><td>#{{ $item->producto_id }}</td><td>{{ $item->cantidad }}</td><td>{{ $item->condicion }}</td><td>$ {{ number_format($item->precio_venta,2,',','.') }}</td><td>$ {{ number_format($item->importe_neto,2,',','.') }}</td></tr>@endforeach
</tbody></table><h2>Total acreditado: $ {{ number_format($note->total,2,',','.') }}</h2><small>Importe calculado sobre la venta original y sus descuentos. Reduce la deuda o genera saldo a favor según la cuenta del cliente.</small></body></html>
