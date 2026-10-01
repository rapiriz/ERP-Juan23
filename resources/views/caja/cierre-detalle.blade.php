@extends('layouts.app')

@section('titulo', 'Detalle de cierre')

@section('contenido')
    <a href="{{ route('caja.cierres.index') }}" class="clay-btn-secondary link-volver">← Volver al historial</a>

    <div class="clay-card">
        <h2>Cierre del {{ \Illuminate\Support\Carbon::parse($cierre['fecha'])->format('d/m/Y') }}</h2>
        <p class="subtitulo">
            Abierta: {{ \Illuminate\Support\Carbon::parse($cierre['fecha_hora_apertura'])->format('d/m/Y H:i') }}
            @if ($cierre['fecha_hora_cierre'])
                · Cerrada: {{ \Illuminate\Support\Carbon::parse($cierre['fecha_hora_cierre'])->format('d/m/Y H:i') }}
            @endif
        </p>

        <table>
            <tbody>
                <tr>
                    <th>Monto inicial</th>
                    <td>${{ number_format($cierre['monto_inicial'], 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Monto final</th>
                    <td>${{ number_format($cierre['monto_final'], 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Diferencia</th>
                    <td>${{ number_format($cierre['diferencia'], 2, ',', '.') }}</td>
                </tr>
                @if (!empty($cierre['observaciones']))
                    <tr>
                        <th>Observaciones</th>
                        <td>{{ $cierre['observaciones'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if (!empty($cierre['movimientos']))
        <div class="clay-card">
            <h3>Movimientos del dia</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cierre['movimientos'] as $movimiento)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $movimiento['tipo'])) }}</td>
                            <td>{{ $movimiento['concepto'] }}</td>
                            <td>${{ number_format($movimiento['monto'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection