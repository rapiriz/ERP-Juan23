@extends('layouts.app')
@section('title', 'Transferencias informadas | Reportes')
@section('content')
@include('partials.form-errors')
<section class="page-heading"><p class="eyebrow">Cobranzas</p><h1>Transferencias informadas</h1><p>Registrá el aviso y su comprobante para una revisión manual. Informar o validar el comprobante no acredita automáticamente el pago.</p></section>
@include('reports.partials.navigation')
@if(auth()->user()->rol === \App\Modules\Login\Enums\UserRole::ADMINISTRATIVO)
<section class="section-block"><div class="section-title"><h2>Informar una transferencia</h2></div>
<form action="{{ route('transferencias.store') }}" method="post" enctype="multipart/form-data" class="form-grid">
    @csrf<input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <div class="field"><label for="cliente_id">Cliente</label><select id="cliente_id" name="cliente_id" required><option value="">Seleccioná un cliente</option>@foreach($clientes as $client)<option value="{{ $client->id }}" @selected((string) old('cliente_id', $clienteId) === (string) $client->id)>{{ $client->apellido_razon_social }}, {{ $client->nombre }} · {{ $client->dni_cuit }}</option>@endforeach</select></div>
    <div class="field"><label for="fecha">Fecha de transferencia</label><input id="fecha" name="fecha" type="date" value="{{ old('fecha', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
    <div class="field"><label for="monto">Importe</label><input id="monto" name="monto" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ old('monto') }}" required></div>
    <div class="field"><label for="referencia">Referencia bancaria</label><input id="referencia" name="referencia" maxlength="150" value="{{ old('referencia') }}" required></div>
    <div class="field"><label for="comprobante">Comprobante · PDF, JPG o PNG, hasta 5 MB</label><input id="comprobante" name="comprobante" type="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
    <div class="toolbar-actions" style="align-self:end"><button class="button button-primary" type="submit">Registrar aviso</button></div>
</form></section>
@endif
<section class="section-block"><div class="section-title"><h2>Avisos registrados</h2></div>
<form method="get" action="{{ route('transferencias.index') }}" class="toolbar-form">
    @if($clienteId)<input type="hidden" name="cliente_id" value="{{ $clienteId }}">@endif
    <div class="field"><label for="estado">Estado</label><select id="estado" name="estado">@foreach(['pendiente' => 'Pendientes de validación', 'validada' => 'Validadas', 'rechazada' => 'Rechazadas', 'todas' => 'Todas'] as $value => $label)<option value="{{ $value }}" @selected($estado === $value)>{{ $label }}</option>@endforeach</select></div>
    <div class="toolbar-actions"><button class="button button-primary" type="submit">Consultar</button><a class="button button-secondary" href="{{ route('transferencias.index') }}">Todos los clientes</a></div>
</form>
@if($notifications->isEmpty())<p class="empty-state">No hay transferencias para los filtros seleccionados.</p>@else
<div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Importe</th><th>Referencia</th><th>Estado</th><th>Comprobante y revisión</th></tr></thead><tbody>
@foreach($notifications as $notification)<tr>
    <td>{{ $notification->fecha->format('d/m/Y') }}</td><td>{{ $notification->cliente?->apellido_razon_social }}, {{ $notification->cliente?->nombre }}</td><td>${{ number_format((float) $notification->monto, 2, ',', '.') }}</td><td>{{ $notification->referencia }}</td><td>{{ ucfirst($notification->estado) }}<p>{{ $notification->nota_revision }}</p></td>
    <td><a class="button button-secondary" href="{{ route('transferencias.download', $notification) }}">Descargar comprobante</a>
    @if(auth()->user()->rol === \App\Modules\Login\Enums\UserRole::ADMINISTRATIVO)
        @if($notification->estado === 'pendiente')
        <form method="post" action="{{ route('transferencias.review', $notification) }}">@csrf @method('PATCH')
            <div class="field"><label for="nota-{{ $notification->id }}">Nota de revisión</label><input id="nota-{{ $notification->id }}" name="nota" minlength="3" maxlength="1000" required></div>
            <button class="button button-primary" name="estado" value="validada" type="submit">Validar comprobante</button><button class="button button-secondary" name="estado" value="rechazada" type="submit">Rechazar</button>
        </form>
        @elseif($notification->estado === 'validada')<a class="button button-primary" href="{{ route('clientes.show', ['cliente' => $notification->cliente_id, 'aviso_id' => $notification->id]) }}">Ir a registrar el pago</a>@endif
    @endif
    </td>
</tr>@endforeach
</tbody></table></div>{{ $notifications->links() }}
@endif</section>
@endsection
