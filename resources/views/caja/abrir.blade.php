@extends('layouts.app')

@section('titulo', 'Abrir Caja')

@section('contenido')
    <div class="clay-card card-angosta">
        <h2>Abrir caja del dia</h2>
        <p class="subtitulo">Todavia no abriste tu caja hoy. Indica el monto inicial para empezar.</p>

        @if ($errors->any())
            <div class="alerta alerta-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('caja.abrir') }}">
            @csrf

            <label for="monto_inicial">Monto inicial</label>
            <input type="number" step="0.01" min="0" name="monto_inicial" id="monto_inicial" class="clay-input" value="{{ old('monto_inicial') }}" required>

            <label for="observaciones">Observaciones (opcional)</label>
            <input type="text" name="observaciones" id="observaciones" class="clay-input" value="{{ old('observaciones') }}">

            <div class="fila-acciones">
                <button type="submit" class="clay-btn-primary">Abrir caja</button>
            </div>
        </form>
    </div>
@endsection