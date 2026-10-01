@extends('layouts.app')

@section('title', 'Ventas diarias | Reportes')

@section('content')
    <section class="page-heading"><p class="eyebrow">Reportes</p><h1>Ventas diarias</h1><p>Resumen de operaciones confirmadas, pagadas o facturadas.</p></section>
    <form class="toolbar-form" action="{{ request()->routeIs('ventas.index') ? route('ventas.index') : route('reports.daily-sales') }}" method="get">
        <div class="field search-field"><label for="fecha">Fecha</label><input id="fecha" type="date" name="fecha" value="{{ $fecha }}" required></div>
        <div class="toolbar-actions"><button class="button button-primary" type="submit">Consultar</button>@if (auth()->user()->rol !== App\Enums\UserRole::REPARTIDOR)<a class="button button-secondary" href="{{ route('reports.daily-sales.export', ['format' => 'pdf', 'fecha' => $fecha]) }}">PDF</a><a class="button button-secondary" href="{{ route('reports.daily-sales.export', ['format' => 'xlsx', 'fecha' => $fecha]) }}">Excel</a>@endif</div>
    </form>
    <section class="finance-summary" aria-label="Resumen diario">
        <article><span>Transacciones</span><strong>{{ $ventas->count() }}</strong><small>{{ \Carbon\CarbonImmutable::parse($fecha)->format('d/m/Y') }}</small></article>
        <article><span>Total facturado</span><strong>${{ number_format($totalFacturado, 2, ',', '.') }}</strong><small>Ventas confirmadas, pagadas o facturadas</small></article>
    </section>
    @include('reports.partials.sales-table', ['ventas' => $ventas, 'compact' => true])
@endsection
