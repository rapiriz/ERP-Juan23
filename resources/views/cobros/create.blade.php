@extends('layouts.app')

@section('title', 'Registrar pago | Sistema de gestion')
@section('layout-class', 'narrow')

@section('content')
    <section class="page-heading">
        <p class="eyebrow">Finanzas y tesoreria</p>
        <h1>Registrar pago</h1>
        <p>{{ $cliente->apellido_razon_social }}, {{ $cliente->nombre }} · Saldo pendiente: <strong>${{ number_format((float) $cliente->saldo, 2, ',', '.') }}</strong></p>
    </section>

    @if ((float) $cliente->saldo <= 0)
        <section class="form-panel">
            <p class="empty-state">El cliente no tiene saldo pendiente. No es necesario registrar un pago.</p>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('clientes.show', $cliente) }}">Volver a la cuenta</a></div>
        </section>
    @else
        <section class="form-panel">
            <form class="form" method="post" action="{{ route('cobros.store', $cliente) }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label for="fecha">Fecha y hora <span>*</span></label>
                        <input id="fecha" type="datetime-local" name="fecha" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('fecha', now()->format('Y-m-d\TH:i')) }}" required>
                        @error('fecha')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="monto_total">Monto <span>*</span></label>
                        <input id="monto_total" type="number" name="monto_total" min="0.01" max="{{ $cliente->saldo }}" step="0.01" value="{{ old('monto_total') }}" required>
                        @error('monto_total')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="medio_pago">Medio de pago <span>*</span></label>
                        <select id="medio_pago" name="medio_pago" required>
                            @foreach ($mediosPago as $medio)
                                <option value="{{ $medio->value }}" @selected(old('medio_pago') === $medio->value)>{{ ucfirst($medio->value) }}</option>
                            @endforeach
                        </select>
                        @error('medio_pago')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="comprobante_nro">Numero de comprobante</label>
                        <input id="comprobante_nro" name="comprobante_nro" maxlength="100" value="{{ old('comprobante_nro') }}">
                        @error('comprobante_nro')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field field-full">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="4" maxlength="2000">{{ old('observaciones') }}</textarea>
                        @error('observaciones')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-actions">
                    <a class="button button-secondary" href="{{ route('clientes.show', $cliente) }}">Cancelar</a>
                    <button class="button button-primary" type="submit">Confirmar pago</button>
                </div>
            </form>
        </section>
    @endif
@endsection
