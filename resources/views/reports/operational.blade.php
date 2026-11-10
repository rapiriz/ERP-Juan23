@extends('layouts.app')
@section('title', $data['title'].' | Reportes')
@section('content')
<section class="page-heading"><p class="eyebrow">Análisis comercial</p><h1>{{ $data['title'] }}</h1><p>{{ $data['description'] ?? 'Consultá información actualizada para tu negocio.' }}</p></section>
@include('reports.partials.navigation')
<form class="toolbar-form" method="get" action="{{ url()->current() }}">
    @if($report !== 'low-stock')
        <div class="field"><label for="fecha">{{ $report === 'due-invoices' ? 'Inicio de la ventana' : 'Fecha' }}</label><input id="fecha" name="fecha" type="date" value="{{ $filters['fecha'] }}" required></div>
    @else
        <div class="field"><label for="orden">Ordenar disponibilidad</label><select id="orden" name="orden"><option value="asc" @selected(($filters['orden'] ?? 'asc') === 'asc')>Menor a mayor</option><option value="desc" @selected(($filters['orden'] ?? '') === 'desc')>Mayor a menor</option></select></div>
    @endif
    @if($report === 'due-invoices')
        <div class="field"><label for="dias">Ventana</label><select id="dias" name="dias">@foreach([7,15,30] as $days)<option value="{{ $days }}" @selected($filters['dias'] === $days)>Próximos {{ $days }} días</option>@endforeach</select></div>
    @endif
    <div class="toolbar-actions"><button class="button button-primary" type="submit">Consultar</button></div>
</form>
@if(!$ready)
    <section class="empty-state" role="status"><h2>Sin datos disponibles</h2><p>No pudimos consultar la información. Volvé a intentar; una falla de conexión no significa que no haya registros.</p><a class="button button-secondary" href="{{ request()->fullUrl() }}">Reintentar consulta</a></section>
@else
    <section class="finance-summary" aria-label="Resumen del reporte">@foreach($data['summary'] as $label => $value)<article><span>{{ $label }}</span><strong>{{ $value }}</strong></article>@endforeach</section>
    @include('reports.partials.exports')
    <section class="section-block"><div class="table-panel">
    @if($data['rows'] === [])
        <p class="empty-state">{{ $report === 'low-stock' ? 'No hay productos activos con stock bajo o agotado.' : 'No hay registros para la consulta seleccionada.' }}</p>
    @else
        <div class="table-scroll"><table><thead><tr>@foreach($data['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
        @foreach($data['rows'] as $row)<tr>@foreach($row as $value)<td>{{ is_float($value) ? '$ '.number_format($value, 2, ',', '.') : $value }}</td>@endforeach</tr>@endforeach
        </tbody></table></div>
    @endif
    </div></section>
@endif
@endsection
