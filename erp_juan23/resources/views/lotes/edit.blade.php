@extends('layouts.app')

@section('content')
<div class="container-fluid p-4">
    <div class="clay-header mb-4">
        <h1 class="clay-title">✏️ Editar Lote</h1>
        <p class="clay-subtitle">{{ $lote->producto->nombre }} — Lote {{ $lote->numero_lote }}</p>
    </div>

    <div class="clay-card">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('lotes.update', $lote->id) }}" class="row g-3">
            @csrf
            @method('PUT')

            <div class="col-md-6">
                <label class="clay-label">Producto</label>
                <select name="producto_id" class="clay-select" required>
                    @foreach ($productos as $producto)
                        <option value="{{ $producto->id }}" @selected(old('producto_id', $lote->producto_id) == $producto->id)>
                            {{ $producto->nombre }} ({{ $producto->codigo }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="clay-label">Número de Lote</label>
                <input type="text" name="numero_lote" class="clay-input" value="{{ old('numero_lote', $lote->numero_lote) }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Fecha de Vencimiento</label>
                <input type="date" name="fecha_vencimiento" class="clay-input"
                       value="{{ old('fecha_vencimiento', $lote->fecha_vencimiento->format('Y-m-d')) }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Cantidad Actual</label>
                <input type="number" min="0" name="cantidad_actual" class="clay-input" value="{{ old('cantidad_actual', $lote->cantidad_actual) }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Estado</label>
                <select name="estado" class="clay-select" required>
                    <option value="activo" @selected(old('estado', $lote->estado) === 'activo')>Activo</option>
                    <option value="vencido" @selected(old('estado', $lote->estado) === 'vencido')>Vencido</option>
                    <option value="proximos_a_vencer" @selected(old('estado', $lote->estado) === 'proximos_a_vencer')>Próx. a Vencer</option>
                </select>
            </div>

            <div class="col-md-6">
                <label class="clay-label">Ubicación en Almacén</label>
                <input type="text" name="ubicacion_almacen" class="clay-input" value="{{ old('ubicacion_almacen', $lote->ubicacion_almacen) }}">
            </div>

            <div class="col-12">
                <label class="clay-label">Observaciones</label>
                <textarea name="observaciones" class="clay-input" rows="3">{{ old('observaciones', $lote->observaciones) }}</textarea>
            </div>

            <div class="col-12 d-flex gap-2 mt-3">
                <button type="submit" class="clay-btn clay-btn-primary">💾 Guardar Cambios</button>
                <a href="{{ route('lotes.index') }}" class="clay-btn clay-btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
