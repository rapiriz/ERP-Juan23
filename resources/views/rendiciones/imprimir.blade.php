<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de Rendición #{{ $rendicion['id_rendicion'] }}</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; margin: 0; }
        .header { border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        .empresa { font-size: 16px; font-weight: bold; color: #1e3a8a; }
        .subtitulo { font-size: 10px; color: #64748b; margin-top: 2px; }
        .comprobante-info { text-align: right; }
        .comprobante-nro { font-size: 14px; font-weight: bold; color: #1e3a8a; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        .badge-aprobada { background: #dcfce7; color: #166534; }
        .badge-rechazada { background: #fee2e2; color: #991b1b; }
        .datos-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .datos-grid td { padding: 4px 6px; font-size: 11px; }
        .label { font-weight: bold; color: #475569; width: 18%; }
        table.tabla-cobros { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 16px; }
        table.tabla-cobros th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-size: 10px; font-weight: bold; color: #334155; }
        table.tabla-cobros td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totales-box { width: 45%; margin-left: auto; border-collapse: collapse; margin-bottom: 24px; }
        .totales-box td { padding: 5px 8px; font-size: 11px; border: 1px solid #cbd5e1; }
        .totales-box tr.total-final { font-size: 13px; font-weight: bold; background: #f8fafc; color: #1e3a8a; }
        .firmas { margin-top: 40px; width: 100%; border-collapse: collapse; }
        .firmas td { width: 50%; padding: 0 20px; text-align: center; }
        .linea-firma { border-top: 1px dashed #64748b; padding-top: 6px; font-size: 10px; color: #475569; font-weight: bold; }
        .no-print { margin-bottom: 16px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right;">
        <button onclick="window.print()" style="background: #1e3a8a; color: white; border: none; padding: 8px 16px; font-size: 12px; font-weight: bold; border-radius: 6px; cursor: pointer;">
            Imprimir Comprobante
        </button>
        <button onclick="window.close()" style="background: #e2e8f0; color: #334155; border: none; padding: 8px 16px; font-size: 12px; font-weight: bold; border-radius: 6px; cursor: pointer; margin-left: 6px;">
            Cerrar
        </button>
    </div>

    @php
        $dinero = fn ($v) => '$' . number_format((float) ($v ?? 0), 2, ',', '.');
        $fecha = \Illuminate\Support\Carbon::parse($rendicion['fecha'] ?? now())->format('d/m/Y H:i');
        $estado = strtolower($rendicion['estado'] ?? 'pendiente');
        $cobros = $rendicion['cobros'] ?? [];
        $totalEfectivo = 0;
        $totalValores = 0;
        foreach ($cobros as $c) {
            $m = (float) ($c['monto'] ?? 0);
            if (($c['medio_pago'] ?? '') === 'efectivo') {
                $totalEfectivo += $m;
            } else {
                $totalValores += $m;
            }
        }
    @endphp

    <table style="width: 100%; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 14px;">
        <tr>
            <td style="vertical-align: top;">
                <div class="empresa">DISTRIBUIDORA JUAN XXIII</div>
                <div class="subtitulo">Pigüé, Buenos Aires — Distribución Mayorista y Logística</div>
                <div class="subtitulo">Comprobante Interno de Rendición de Cobranzas</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div class="comprobante-nro">RENDICIÓN #{{ str_pad($rendicion['id_rendicion'], 6, '0', STR_PAD_LEFT) }}</div>
                <div class="subtitulo">Fecha: {{ $fecha }}</div>
                <div style="margin-top: 4px;">
                    <span class="badge badge-{{ $estado }}">{{ ucfirst($estado) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="datos-grid">
        <tr>
            <td class="label">Repartidor / Chofer:</td>
            <td><strong>{{ $rendicion['repartidor_nombre'] ?? ("Usuario #" . ($rendicion['id_repartidor'] ?? '—')) }}</strong></td>
            <td class="label">Entrega / Hoja de Ruta:</td>
            <td>{{ !empty($rendicion['id_entrega']) ? "#ENT-" . str_pad($rendicion['id_entrega'], 4, '0', STR_PAD_LEFT) : 'Carga directa' }}</td>
        </tr>
        <tr>
            <td class="label">Observaciones:</td>
            <td colspan="3"><em>{{ $rendicion['observaciones'] ?? 'Sin observaciones de rendición' }}</em></td>
        </tr>
        @if(!empty($rendicion['motivo_rechazo']))
        <tr>
            <td class="label" style="color: #991b1b;">Motivo de Rechazo:</td>
            <td colspan="3" style="color: #991b1b;"><strong>{{ $rendicion['motivo_rechazo'] }}</strong></td>
        </tr>
        @endif
    </table>

    <h3 style="font-size: 11px; margin: 12px 0 4px; color: #1e3a8a; text-transform: uppercase;">
        Detalle de Valores y Cobros Rendidos:
    </h3>

    <table class="tabla-cobros">
        <thead>
            <tr>
                <th style="width: 32px;" class="text-center">#</th>
                <th>Cliente / Razón Social</th>
                <th>Factura / Venta</th>
                <th class="text-center">Medio de Pago</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cobros as $idx => $c)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td>{{ $c['cliente_nombre'] ?? ("Cliente #" . ($c['id_cliente'] ?? '—')) }}</td>
                <td>{{ !empty($c['id_factura']) ? "#FAC-" . $c['id_factura'] : 'Cobro a Cta. Cte.' }}</td>
                <td class="text-center" style="text-transform: capitalize;">{{ $c['medio_pago'] ?? 'efectivo' }}</td>
                <td class="text-right"><strong>{{ $dinero($c['monto']) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center" style="padding: 14px; color: #64748b;">
                    No se registraron cobros individuales en esta rendición.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totales-box">
        <tr>
            <td>Total Efectivo a Caja:</td>
            <td class="text-right">{{ $dinero($totalEfectivo) }}</td>
        </tr>
        <tr>
            <td>Total Cheques / Transferencias:</td>
            <td class="text-right">{{ $dinero($totalValores) }}</td>
        </tr>
        <tr class="total-final">
            <td>TOTAL RENDIDO:</td>
            <td class="text-right">{{ $dinero($rendicion['total_rendido'] ?? ($totalEfectivo + $totalValores)) }}</td>
        </tr>
    </table>

    <table class="firmas">
        <tr>
            <td>
                <div class="linea-firma">
                    Firma del Repartidor / Chofer<br>
                    <span style="font-size: 9px; font-weight: normal; color: #64748b;">Entrega de valores</span>
                </div>
            </td>
            <td>
                <div class="linea-firma">
                    Firma de Administración / Tesorería<br>
                    <span style="font-size: 9px; font-weight: normal; color: #64748b;">Recepción y control conforme</span>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

