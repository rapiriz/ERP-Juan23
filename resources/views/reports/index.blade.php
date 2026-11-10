@extends('layouts.app')

@section('title', 'Reportes | Sistema de gestion')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/reports.css') }}">
@endpush

@section('content')
    <div class="report-hub">
        <section class="page-heading">
            <p class="eyebrow">Análisis comercial</p>
            <h1>Reportes y Finanzas</h1>
            <p>Consultá el historial de tus clientes, sus compras y los resultados comerciales.</p>
        </section>

        @php
            $reportCards = [
                ['title' => 'Historial de clientes', 'description' => 'Ventas, productos e importes de cada cliente. Filtrá por período y descargá el historial en PDF o Excel.', 'route' => 'reports.client-history', 'action' => 'Ver historial', 'icon' => 'M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-3M8 2h8v4H8zM7 11h10M7 16h7'],
                ['title' => 'Clientes por producto', 'description' => 'Conocé qué clientes compraron un producto y qué porcentaje representan sobre tu base de clientes.', 'route' => 'reports.client-product-reach', 'action' => 'Consultar compradores', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
                ['title' => 'Clientes deudores', 'description' => 'Revisá los saldos informados y las facturas pendientes. Ordená los resultados por importe adeudado.', 'route' => 'reports.debtors', 'action' => 'Consultar deudores', 'icon' => 'M3 5h18v14H3zM3 9h18M7 14h3M14 14h3'],
                ['title' => 'Ranking comercial', 'description' => 'Compará las unidades vendidas y el importe neto por producto en ventas confirmadas o posteriores.', 'route' => 'reports.product-ranking', 'action' => 'Ver ranking', 'icon' => 'M4 20V10h4v10M10 20V4h4v16M16 20v-7h4v7M2 20h20'],
                ['title' => 'Reportes de ventas', 'description' => 'Consultá las operaciones y el importe vendido de una jornada, incluyendo fechas anteriores.', 'route' => 'reports.daily-sales', 'action' => 'Consultar ventas diarias', 'icon' => 'M3 3v18h18M7 14l4-4 4 3 6-7M16 6h5v5'],
                ['title' => 'Reportes de stock', 'description' => 'Consultá los productos agotados o por debajo del mínimo, con disponibilidad y ordenamiento.', 'route' => 'reports.low-stock', 'action' => 'Consultar stock bajo', 'icon' => 'M21 8l-9 5-9-5M12 13v9M3 7l9-5 9 5v10l-9 5-9-5zM7 5l10 5'],
                ['title' => 'Caja diaria', 'description' => 'Revisá ingresos, egresos, movimientos y el neto de la fecha seleccionada.', 'route' => 'reports.daily-cash', 'action' => 'Consultar caja', 'icon' => 'M3 5h18v14H3zM3 10h18M7 15h3'],
                ['title' => 'Vencimientos', 'description' => 'Consultá comprobantes pendientes en ventanas de 7, 15 o 30 días, vencidos y sin fecha informada.', 'route' => 'reports.due-invoices', 'action' => 'Consultar vencimientos', 'icon' => 'M4 5h16v16H4zM4 10h16M8 3v4M16 3v4'],
                ['title' => 'Transferencias informadas', 'description' => 'Consultá avisos y comprobantes, revisá pendientes y continuá con el registro del pago.', 'route' => 'transferencias.index', 'action' => 'Ver transferencias', 'icon' => 'M4 8h16M16 4l4 4-4 4M20 16H4M8 12l-4 4 4 4'],
            ];
            $canOpenOperationalModules = auth()->user()?->rol === \App\Modules\Login\Enums\UserRole::ADMINISTRATIVO;
        @endphp

        <section class="report-card-grid" aria-label="Reportes disponibles">
            @foreach ($reportCards as $card)
                <article class="report-card">
                    <div class="report-card-heading">
                        <span class="report-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="{{ $card['icon'] }}"/></svg></span>
                        <h2>{{ $card['title'] }}</h2>
                    </div>
                    <p>{{ $card['description'] }}</p>
                    @if (empty($card['restricted']) || ($canOpenOperationalModules && \Illuminate\Support\Facades\Route::has($card['route'])))
                        <a class="button button-primary" href="{{ route($card['route']) }}" aria-label="{{ $card['action'] }}: {{ $card['title'] }}">{{ $card['action'] }} <span aria-hidden="true">→</span></a>
                    @else
                        <button class="button button-primary" type="button" disabled>{{ $card['action'] }}</button>
                        <small>Acceso disponible para administración.</small>
                    @endif
                </article>
            @endforeach
        </section>

    </div>
@endsection
