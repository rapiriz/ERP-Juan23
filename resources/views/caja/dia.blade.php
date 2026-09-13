@extends('layouts.app')

@section('titulo', 'Caja del dia')

@section('contenido')
    <div class="clay-card">
        <div class="encabezado-flex-inicio">
            <div>
                <h2>Caja del {{ \Illuminate\Support\Carbon::parse($caja['fecha'])->format('d/m/Y') }}</h2>
                <p class="subtitulo">
                    <span class="badge badge-{{ $caja['estado'] === 'abierta' ? 'abierto' : 'cerrado' }}">{{ ucfirst($caja['estado']) }}</span>
                    · Monto inicial: ${{ number_format($caja['monto_inicial'], 2, ',', '.') }}
                </p>
            </div>

            @if ($caja['estado'] === 'abierta')
                <div class="fila-acciones acciones-sin-margen">
                    <button type="button" class="clay-btn-primary" onclick="document.getElementById('modal-movimiento').classList.add('visible')">
                        Registrar movimiento
                    </button>
                    <button type="button" class="clay-btn-secondary" onclick="document.getElementById('modal-cerrar').classList.add('visible')">
                        Cerrar caja
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="clay-card">
        <h3>Movimientos de hoy</h3>

        @if (empty($caja['movimientos']))
            <div class="vacio">
                <p>Todavia no registraste movimientos en esta caja.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($caja['movimientos'] as $movimiento)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $movimiento['tipo'])) }}</td>
                            <td>{{ $movimiento['concepto'] }}</td>
                            <td>${{ number_format($movimiento['monto'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Modal: registrar movimiento --}}
    <div id="modal-movimiento" class="modal-overlay">
        <div class="clay-card modal-card">
            <h3>Registrar movimiento</h3>

            <form method="POST" action="{{ route('caja.movimiento', $caja['id_caja']) }}">
                @csrf

                <label for="tipo">Tipo</label>
                {{-- NOTA: 'cobro' probablemente lo genere el modulo de Cobros
                     automaticamente, no un usuario a mano - a confirmar con el equipo. --}}
                <select name="tipo" id="tipo" class="clay-input" required>
                    <option value="ingreso_manual">Ingreso manual</option>
                    <option value="egreso">Egreso</option>
                    <option value="extraccion">Extraccion</option>
                    <option value="cobro">Cobro</option>
                </select>

                <label for="concepto">Concepto</label>
                <input type="text" name="concepto" id="concepto" class="clay-input" maxlength="200" required>

                <label for="monto">Monto</label>
                <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="clay-input" required>

                <div class="fila-acciones">
                    <button type="submit" class="clay-btn-primary">Guardar movimiento</button>
                    <button type="button" class="clay-btn-secondary" onclick="document.getElementById('modal-movimiento').classList.remove('visible')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: cerrar caja --}}
    <div id="modal-cerrar" class="modal-overlay">
        <div class="clay-card modal-card">
            <h3>Cerrar caja</h3>
            <p class="subtitulo">Indica el monto final contado en efectivo.</p>

            <form method="POST" action="{{ route('caja.cerrar', $caja['id_caja']) }}">
                @csrf
                @method('PATCH')

                <label for="monto_final">Monto final</label>
                <input type="number" step="0.01" min="0" name="monto_final" id="monto_final" class="clay-input" required>

                <label for="observaciones_cierre">Observaciones (opcional)</label>
                <input type="text" name="observaciones" id="observaciones_cierre" class="clay-input">

                <div class="fila-acciones">
                    <button type="submit" class="clay-btn-primary">Confirmar cierre</button>
                    <button type="button" class="clay-btn-secondary" onclick="document.getElementById('modal-cerrar').classList.remove('visible')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection