@extends('layouts.app')

@section('title', 'Inicio — ERP Distribuidora')

@section('content')
<div class="page-wrap">

    <div class="actions-grid">

        {{-- Botón: Ver Promociones --}}
        <div class="clay-card clay-card-hover action-card">
            <div class="action-icon">🏷️</div>
            <h2 class="action-title">Ver Promociones</h2>
            <p class="action-desc">
                Consultá las promociones vigentes, descuentos por producto y ofertas por temporada.
            </p>
            <a href="{{ route('promociones') }}" class="clay-btn-secondary">
                Ver promociones
            </a>
        </div>

        {{-- Botón: Realizar Venta --}}
        <div class="clay-card clay-card-hover action-card">
            <div class="action-icon">🛒</div>
            <h2 class="action-title">Realizar Venta</h2>
            <p class="action-desc">
                Accedé al punto de venta para registrar una nueva operación.
            </p>
            <a href="{{ route('ventas') }}" class="clay-btn-primary">
                Iniciar venta
            </a>
        </div>

    </div>
</div>
@endsection
