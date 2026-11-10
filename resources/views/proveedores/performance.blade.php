@extends('layouts.app')
@section('title', 'Cumplimiento de proveedores')
@section('content')
<section class="page-container"><p class="eyebrow">COMPRAS Y PROVEEDORES</p><h1>Cumplimiento de proveedores</h1><p>{{ $criterio }}</p>
<form class="section-block form-grid" method="get"><div><label for="supplier-from">Compras desde</label><input id="supplier-from" name="desde" type="date" value="{{ request('desde') }}"></div><div><label for="supplier-to">Compras hasta</label><input id="supplier-to" name="hasta" type="date" value="{{ request('hasta') }}"></div>
<div><label for="supplier-id">Proveedor</label><select id="supplier-id" name="id_proveedor"><option value="">Comparar todos</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id_proveedor }}" @selected((string)request('id_proveedor') === (string)$supplier->id_proveedor)>{{ $supplier->razon_social }}</option>@endforeach</select></div><button class="button button-primary">Consultar</button></form>
<p class="section-block">Las compras históricas sin fecha comprometida no se completan con fechas inventadas. Para las nuevas compras se conserva el compromiso informado o, si no se indica, el plazo del proveedor vigente al registrarlas.</p>
<div class="section-block" style="overflow-x:auto"><table><thead><tr><th>Proveedor</th><th>Compras</th><th>Entregas evaluables</th><th>En término</th><th>Demora media</th><th>Pendientes vencidas</th><th>Sin compromiso</th></tr></thead><tbody>
@forelse($data as $row)<tr><td>{{ $row['proveedor'] }}</td><td>{{ $row['compras'] }}</td><td>{{ $row['entregas_evaluables'] }}</td><td>{{ $row['porcentaje_en_termino'] === null ? 'Sin datos comparables' : $row['porcentaje_en_termino'].' %' }}</td><td>{{ $row['demora_promedio_dias'] === null ? 'Sin datos comparables' : $row['demora_promedio_dias'].' días' }}</td><td>{{ $row['pendientes_vencidas'] }}</td><td>{{ $row['sin_fecha_comprometida'] }}</td></tr>@empty<tr><td colspan="7">No hay proveedores para comparar.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
