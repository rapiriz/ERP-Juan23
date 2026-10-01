@extends('layouts.app')

@section('title', 'Stock bajo | Reportes')

@section('content')
    <section class="page-heading"><p class="eyebrow">Reportes</p><h1>Productos con stock bajo</h1><p>Existencias actuales inferiores al mínimo configurado.</p></section>
    <form class="toolbar-form" action="{{ request()->routeIs('stock.index') ? route('stock.index') : route('reports.low-stock') }}" method="get">
        <div class="field search-field"><label for="orden">Ordenar por existencia</label><select id="orden" name="orden"><option value="asc" @selected($orden === 'asc')>Menor primero</option><option value="desc" @selected($orden === 'desc')>Mayor primero</option></select></div>
        <div class="toolbar-actions"><button class="button button-primary" type="submit">Ordenar</button>@if (auth()->user()->rol !== App\Enums\UserRole::REPARTIDOR)<a class="button button-secondary" href="{{ route('reports.low-stock.export', ['format' => 'pdf', 'orden' => $orden]) }}">PDF</a><a class="button button-secondary" href="{{ route('reports.low-stock.export', ['format' => 'xlsx', 'orden' => $orden]) }}">Excel</a>@endif</div>
    </form>
    <section class="table-panel">
        @if ($productos->isEmpty())
            <p class="empty-state">No hay productos por debajo del stock mínimo.</p>
        @else
            <div class="table-scroll"><table>
                <thead><tr><th>Producto</th><th>Categoría / Marca</th><th>Disponible</th><th>Mínimo</th><th>Faltante</th></tr></thead>
                <tbody>@foreach ($productos as $producto)<tr><td><strong>{{ $producto->nombre }}</strong><span>{{ $producto->codigo }}</span></td><td>{{ $producto->categoria?->nombre ?? 'Sin categoría' }}<span>{{ $producto->marca?->nombre ?? 'Sin marca' }}</span></td><td><strong class="amount-due">{{ $producto->stock }}</strong></td><td>{{ $producto->stock_minimo }}</td><td>{{ $producto->stock_minimo - $producto->stock }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </section>
@endsection
