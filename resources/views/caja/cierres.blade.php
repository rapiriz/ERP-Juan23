@extends('layouts.app')

@section('titulo', 'Historial de Cierres')

@section('contenido')
    <div class="clay-card">
        <h2>Historial de cierres de caja</h2>
        <p class="subtitulo">Cajas cerradas, de la mas reciente a la mas antigua.</p>

        @if (count($cierres) === 0)
            <div class="vacio">
                <p>Todavia no hay cierres de caja registrados.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Monto inicial</th>
                        <th>Monto final</th>
                        <th>Diferencia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cierres as $cierre)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($cierre['fecha'])->format('d/m/Y') }}</td>
                            <td>${{ number_format($cierre['monto_inicial'], 2, ',', '.') }}</td>
                            <td>${{ number_format($cierre['monto_final'], 2, ',', '.') }}</td>
                            <td>${{ number_format($cierre['diferencia'], 2, ',', '.') }}</td>
                            <td>
                                <a href="{{ route('caja.cierres.show', $cierre['id_caja']) }}" class="clay-btn-secondary">Ver detalle</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection