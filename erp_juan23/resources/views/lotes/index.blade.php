@extends('layouts.app')

@section('content')
<div class="container-fluid p-4">
    <div class="clay-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="clay-title">🗃️ Lotes de Producto</h1>
                <p class="clay-subtitle">Alta, edición y control de vencimientos por lote</p>
            </div>
            <a href="{{ route('lotes.create') }}" class="clay-btn clay-btn-primary">
                <i class="fas fa-plus"></i> Nuevo Lote
            </a>
        </div>
    </div>

    @if (session('exito'))
        <div class="alert alert-success">{{ session('exito') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="clay-card mb-4">
        <div class="clay-card-header">
            <h5>Filtros Avanzados</h5>
        </div>
        <form method="GET" action="{{ route('lotes.index') }}" class="row g-3 p-3">
            <div class="col-md-4">
                <label class="clay-label">Producto</label>
                <input type="text" name="producto" class="clay-input" value="{{ $filtros['producto'] ?? '' }}" placeholder="Nombre o código">
            </div>
            <div class="col-md-3">
                <label class="clay-label">Estado</label>
                <select name="estado" class="clay-select">
                    <option value="">Todos</option>
                    <option value="activo" @selected(($filtros['estado'] ?? '') === 'activo')>Activo</option>
                    <option value="vencido" @selected(($filtros['estado'] ?? '') === 'vencido')>Vencido</option>
                    <option value="proximos_a_vencer" @selected(($filtros['estado'] ?? '') === 'proximos_a_vencer')>Próx. a Vencer</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="clay-label">Ubicación</label>
                <input type="text" name="ubicacion" class="clay-input" value="{{ $filtros['ubicacion'] ?? '' }}" placeholder="Depósito, estante...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="clay-btn clay-btn-secondary w-100">🔍 Filtrar</button>
            </div>
        </form>
    </div>

    <div class="clay-card">
        <div class="clay-card-header">
            <h5>Listado de Lotes</h5>
        </div>

        <div class="table-responsive">
            <table class="clay-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Lote</th>
                        <th>Vencimiento</th>
                        <th>Cantidad</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lotes as $lote)
                        <tr>
                            <td><strong>{{ $lote->producto->nombre }}</strong><br><small>{{ $lote->producto->codigo }}</small></td>
                            <td>{{ $lote->numero_lote }}</td>
                            <td>{{ $lote->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td>{{ $lote->cantidad_actual }} / {{ $lote->cantidad_inicial }}</td>
                            <td>{{ $lote->ubicacion_almacen ?? 'N/A' }}</td>
                            <td><span class="badge bg-secondary">{{ $lote->estado }}</span></td>
                            <td class="d-flex gap-2">
                                <a href="{{ route('lotes.edit', $lote->id) }}" class="clay-btn clay-btn-sm clay-btn-secondary">✏️ Editar</a>
                                <form method="POST" action="{{ route('lotes.destroy', $lote->id) }}" onsubmit="return confirm('¿Eliminar este lote?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="clay-btn clay-btn-sm clay-btn-danger">🗑️ Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No hay lotes registrados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($paginacion['total_paginas'] > 1)
            <div class="clay-pagination mt-3">
                @for ($p = 1; $p <= $paginacion['total_paginas']; $p++)
                    <a href="{{ route('lotes.index', array_merge($filtros, ['pagina' => $p])) }}"
                       class="clay-btn clay-btn-sm {{ $p === $paginacion['pagina_actual'] ? 'clay-btn-primary' : 'clay-btn-secondary' }}">
                        {{ $p }}
                    </a>
                @endfor
            </div>
        @endif
    </div>
</div>
@endsection
