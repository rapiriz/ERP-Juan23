@extends('layouts.app')

@section('title', 'Cuenta del cliente | Sistema de gestion')

@section('nav-links')
    <a href="{{ route('clientes.index') }}">Clientes</a>
@endsection

@section('content')
    @include('partials.form-errors')
    <section class="page-heading heading-with-actions">
        <div>
            <p class="eyebrow">Cuenta corriente</p>
            <h1>{{ $cliente->apellido_razon_social }}, {{ $cliente->nombre }}</h1>
            <p>{{ $cliente->dni_cuit }} · {{ $cliente->email }}</p>
        </div>
    </section>

    <section class="finance-summary" aria-label="Resumen de cuenta">
        <article>
            <span>Saldo pendiente</span>
            @if ($saldoDisponible)
                <strong class="{{ (float) $saldo > 0 ? 'amount-due' : 'amount-clear' }}">${{ number_format((float) $saldo, 2, ',', '.') }}</strong>
                <small>{{ (float) $saldo > 0 ? 'Deuda vigente' : 'Cliente sin deuda' }}</small>
            @else
                <strong>--</strong>
                <small>Esperando API de saldos de G4</small>
            @endif
        </article>
        <article>
            <span>Pagos registrados</span>
            @if ($cobrosDisponibles)
                <strong>{{ count($cobros) }}</strong>
                <small>Pagos efectivamente aplicados</small>
            @else
                <strong>--</strong>
                <small>Sin conexión al historial de pagos de G2</small>
            @endif
        </article>
        <article>
            <span>Ventas mostradas</span>
            @if ($salesReady)
                <strong>{{ $ventas->count() }}</strong>
                <small>Segun el periodo seleccionado</small>
            @else
                <strong>--</strong>
                <small>Esperando API de ventas de G4</small>
            @endif
        </article>
    </section>

    @if(auth()->user()->rol === \App\Modules\Login\Enums\UserRole::ADMINISTRATIVO)
    <section class="section-block" aria-label="Registrar pago parcial">
        <div class="section-title"><h2>Registrar pago de cuenta</h2></div>
        <p>El pago se registra en tu caja abierta y reduce la deuda del cliente. Podés abonar una parte o el total pendiente.</p>
        @if($pagosDisponibles && $saldoDisponible && (float) $saldo > 0)
        <form method="post" action="{{ route('clientes.payments.store', $cliente) }}" class="toolbar-form">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            @if($transferencia)<input type="hidden" name="transferencia_id" value="{{ $transferencia->id }}">@endif
            <div class="field"><label for="pago-monto">Importe</label><input id="pago-monto" type="number" name="monto" min="0.01" max="{{ $saldo }}" step="0.01" value="{{ old('monto', $transferencia?->monto) }}" required></div>
            <div class="field"><label for="pago-medio">Medio de pago</label><select data-cheque-method id="pago-medio" name="medio_pago" required>@foreach(['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'cheque' => 'Cheque', 'otro' => 'Otro'] as $value => $label)<option value="{{ $value }}" @selected(old('medio_pago', $transferencia ? 'transferencia' : 'efectivo') === $value)>{{ $label }}</option>@endforeach</select></div>
            @include('partials.cheque-payment-fields')
            <div class="field"><label for="pago-comprobante">Referencia del comprobante</label><input id="pago-comprobante" name="comprobante" maxlength="150" value="{{ old('comprobante', $transferencia?->referencia) }}"></div>
            <div class="toolbar-actions"><button class="button button-primary" type="submit">Registrar pago</button><a class="button button-secondary" href="{{ route('caja.dia') }}">Ver mi caja</a></div>
        </form>
        @else
            <p class="empty-state">{{ !$pagosDisponibles ? 'El registro de pagos no está habilitado para esta conexión.' : ($saldoDisponible ? 'No hay deuda pendiente para registrar un pago.' : 'Sin saldo disponible: no se pueden registrar pagos hasta recuperar la consulta.') }}</p>
        @endif
        @if((float) $saldoAFavor > 0)<p>Saldo a favor: <strong>${{ number_format((float) $saldoAFavor, 2, ',', '.') }}</strong></p>@endif
        <a class="button button-secondary" href="{{ route('transferencias.index', ['cliente_id' => $cliente->id]) }}">Informar o consultar una transferencia</a>
    </section>
    @endif

    <section class="section-block">
        <div class="section-title"><h2>Historial de pagos</h2></div>
        <div class="table-panel">
            @if (! $cobrosDisponibles)
                <p class="empty-state">Sin datos disponibles para el historial de pagos. Los cobros registrados en rendiciones se muestran por separado y no certifican pagos confirmados.</p>
            @elseif (count($cobros) === 0)
                <p class="empty-state">Este cliente no tiene pagos registrados.</p>
            @else
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Fecha</th><th>Monto</th><th>Medio</th><th>Comprobante</th><th>Estado</th><th>Notas</th></tr></thead>
                        <tbody>
                        @foreach ($cobros as $cobro)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($cobro['fecha'])->format('d/m/Y H:i') }}</td>
                                <td><strong>${{ number_format((float) $cobro['monto_total'], 2, ',', '.') }}</strong></td>
                                <td>{{ ucfirst($cobro['medio_pago']) }}</td>
                                <td>{{ $cobro['comprobante_nro'] ?? '-' }}</td>
                                <td>Aplicado
                                    @if(!empty($cobro['cheque']))
                                        <small>Cheque {{ str_replace('_', ' ', $cobro['cheque']['estado']) }}{{ $cobro['cheque']['estado'] !== 'conciliado' ? ' · Pendiente de acreditación bancaria' : ' · Acreditado' }}</small>
                                        @if(auth()->user()->rol === \App\Modules\Login\Enums\UserRole::ADMINISTRATIVO)
                                            <a href="{{ route('conciliacion.index') }}">Gestionar cheque</a>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $cobro['observaciones'] ?? 'Sin observaciones' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <section class="section-block" aria-label="Cobros registrados en rendiciones de G2">
        <div class="section-title"><h2>Cobros registrados en rendiciones</h2></div>
        <p>Un cobro declarado o una rendición aprobada no acreditan el pago por sí solos. Se marca como aplicado cuando el historial financiero informa la confirmación individual y la referencia del cobro correspondiente.</p>
        @if (! $cobrosRendidos['ready'])
            <p class="empty-state" role="status">{{ $cobrosRendidos['message'] }}</p>
        @elseif ($cobrosRendidos['rows'] === [])
            <p class="empty-state">No hay cobros de este cliente en las rendiciones devueltas por G2.</p>
        @else
            <div class="table-scroll"><table>
                <thead><tr><th>Fecha</th><th>Rendición</th><th>Factura (ID)</th><th>Importe registrado</th><th>Medio</th><th>Estado de la rendición</th><th>Aplicación financiera</th></tr></thead>
                <tbody>@foreach ($cobrosRendidos['rows'] as $registro)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($registro['fecha'])->format('d/m/Y H:i') }}</td>
                        <td>#{{ $registro['rendicion_id'] }}</td><td>#{{ $registro['factura_id'] }}</td>
                        <td>${{ number_format((float) $registro['monto'], 2, ',', '.') }}</td>
                        <td>{{ ucfirst($registro['medio_pago']) }}</td><td>{{ ucfirst($registro['estado_rendicion']) }}</td>
                        <td>{{ $registro['aplicacion'] }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>

    <section class="section-block">
        <div class="section-title"><h2>Compras y ventas del cliente</h2></div>
        <form class="toolbar-form" method="get" action="{{ route('clientes.show', $cliente) }}">
            <div class="field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="{{ $desde }}"></div>
            <div class="field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $hasta }}"></div>
            <div class="toolbar-actions"><button class="button button-primary" type="submit">Filtrar</button><a class="button button-secondary" href="{{ route('clientes.show', $cliente) }}">Limpiar</a></div>
        </form>
        @include('reports.partials.sales-table', ['ventas' => $ventas])
    </section>
@endsection
