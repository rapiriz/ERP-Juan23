@extends('layouts.app')

@section('content')
<div class="container-fluid p-4">
    <div class="clay-header mb-4">
        <h1 class="clay-title">➕ Nuevo Lote</h1>
        <p class="clay-subtitle">Registrar un nuevo lote de producto</p>
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

        <form method="POST" action="{{ route('lotes.store') }}" class="row g-3">
            @csrf

            <div class="col-md-6">
                <label class="clay-label">Producto</label>
                <select name="producto_id" class="clay-select" required>
                    <option value="">Seleccioná un producto</option>
                    @foreach ($productos as $producto)
                        <option value="{{ $producto->id }}" @selected(old('producto_id') == $producto->id)>
                            {{ $producto->nombre }} ({{ $producto->codigo }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label class="clay-label">Número de Lote</label>
                <input type="text" name="numero_lote" class="clay-input" value="{{ old('numero_lote') }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Fecha de Vencimiento</label>
                <input type="date" name="fecha_vencimiento" class="clay-input" value="{{ old('fecha_vencimiento') }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Cantidad Inicial</label>
                <input type="number" min="1" name="cantidad_inicial" class="clay-input" value="{{ old('cantidad_inicial') }}" required>
            </div>

            <div class="col-md-4">
                <label class="clay-label">Ubicación en Almacén</label>
                <input type="text" name="ubicacion_almacen" class="clay-input" value="{{ old('ubicacion_almacen') }}" placeholder="Opcional">
            </div>

            <div class="col-12">
                <label class="clay-label">Observaciones</label>
                <textarea name="observaciones" class="clay-input" rows="3">{{ old('observaciones') }}</textarea>
            </div>

            <div class="col-12 d-flex gap-2 mt-3">
                <button type="submit" class="clay-btn clay-btn-primary">💾 Guardar Lote</button>
                <a href="{{ route('lotes.index') }}" class="clay-btn clay-btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
