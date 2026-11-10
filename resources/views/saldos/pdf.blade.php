<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #133354; }
    h1 { color: #075bd8; font-size: 20px; } h2 { font-size: 12px; }
    table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #d9dfe5; padding: 6px; }
    th { background: #075bd8; color: white; text-align: left; } tr:nth-child(even) td { background: #f5f8fc; }
    .summary { padding: 8px; border: 1px solid #d9dfe5; } .money { text-align: right; white-space: nowrap; }
</style></head>
<body>
<h1>PyB Pigüé ERP — Saldos y pagos</h1>
<p>Generado: {{ now()->format('d/m/Y H:i') }}. Moneda: ARS. Los saldos filtrados suman los movimientos del período; no son saldos iniciales ni certifican deudas completas.</p>
<p>Búsqueda: {{ $filters['search'] ?? 'Todos' }} · Fechas: {{ $filters['modoFecha'] ?? 'todas' }}
    {{ $filters['fecha'] ?? '' }} {{ $filters['fechaDesde'] ?? '' }} {{ $filters['fechaHasta'] ?? '' }}</p>
<h2>Resumen del listado filtrado</h2>
<div class="summary">@forelse($summary as $line)<div>{{ $line }}</div>@empty Sin clientes para los filtros elegidos. @endforelse</div>
<h2>Movimientos</h2>
@if($rows === []) <p>No hay movimientos para los filtros elegidos.</p> @else
<table><thead><tr><th>Cliente</th><th>DNI / CUIT</th><th>Fecha</th><th>Descripción</th><th>Medio / referencia</th><th>Monto</th><th>Saldo filtrado</th></tr></thead><tbody>
@foreach($rows as $row)<tr>@foreach($row as $i => $value)<td class="{{ $i >= 5 ? 'money' : '' }}">{{ $value }}</td>@endforeach</tr>@endforeach
</tbody></table>
@endif
</body></html>
