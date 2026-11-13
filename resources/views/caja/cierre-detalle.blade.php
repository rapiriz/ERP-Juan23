@extends('layouts.app')

@section('titulo', 'Detalle de cierre')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/g2-finanzas.css') }}">
@endpush

@section('contenido')
<div class="g2-page">
    @php
        $etiquetasMedio = ['efectivo' => 'Efectivo', 'cheque' => 'Cheque', 'transferencia' => 'Transferencia', 'otro' => 'Otro'];
        $etiquetasTipo = ['reversion' => 'Reversión de cobro', 'ingreso_manual' => 'Ingreso manual', 'pago_proveedor' => 'Pago a proveedor', 'extraccion' => 'Extracción'];
        // Cierre automático (72 h) sin el conteo cargado todavía.
        $pendiente = !empty($cierre['cierre_automatico']) && $cierre['monto_final'] === null;
    @endphp

    <a href="{{ route('caja.cierres.index') }}" class="clay-btn-secondary link-volver">← Volver al historial</a>

    @if (!empty($puedeExportarPdf))
        <a href="{{ route('caja.cierres.pdf.detalle', $cierre['id_caja']) }}" class="clay-btn-secondary">Exportar PDF</a>
    @endif

    @if (session('mensaje'))
        <div class="alerta">{{ session('mensaje') }}</div>
    @endif
    @if (session('error'))
        <div class="alerta alerta-error">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alerta alerta-error">{{ $errors->first() }}</div>
    @endif

    <div class="clay-card">
        <h2>Cierre del {{ \Illuminate\Support\Carbon::parse($cierre['fecha'])->format('d/m/Y') }}</h2>
        <p class="subtitulo">
            Abierta: {{ \Illuminate\Support\Carbon::parse($cierre['fecha_hora_apertura'])->format('d/m/Y H:i') }}
            @if ($cierre['fecha_hora_cierre'])
                · Cerrada: {{ \Illuminate\Support\Carbon::parse($cierre['fecha_hora_cierre'])->format('d/m/Y H:i') }}
            @endif
            @if (!empty($cierre['cierre_automatico']))
                · <span class="badge badge-cerrado">Cierre automático</span>
            @endif
        </p>

        <table>
            <tbody>
                <tr>
                    <th>Monto inicial</th>
                    <td>${{ number_format($cierre['monto_inicial'], 2, ',', '.') }}</td>
                </tr>
                @if ($cierre['monto_esperado'] !== null)
                    <tr>
                        <th>Monto esperado</th>
                        <td>${{ number_format($cierre['monto_esperado'], 2, ',', '.') }}</td>
                    </tr>
                @endif
                <tr>
                    <th>Monto final</th>
                    <td>
                        @if ($pendiente)
                            Pendiente de conteo
                        @else
                            ${{ number_format($cierre['monto_final'], 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Diferencia</th>
                    <td>
                        @if ($pendiente)
                            Pendiente de conteo
                        @else
                            ${{ number_format($cierre['diferencia'], 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @if (isset($cierre['subtotal_efectivo']))
                    <tr>
                        <th>Movimientos en efectivo</th>
                        <td>${{ number_format($cierre['subtotal_efectivo'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <th>Movimientos en valores</th>
                        <td>${{ number_format($cierre['subtotal_valores'], 2, ',', '.') }}</td>
                    </tr>
                @endif
                @if (!empty($cierre['observaciones']))
                    <tr>
                        <th>Observaciones</th>
                        <td>{{ $cierre['observaciones'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if ($pendiente && !empty($puedeCargarConteo))
        <div class="clay-card">
            <h3>Cargar el conteo</h3>
            <p class="subtitulo">
                Esta caja de repartidor se cerró sola al vencer. Contá el efectivo e indicá el monto final:
                la diferencia se calcula contra el monto esperado.
            </p>

            <form method="POST" action="{{ route('caja.conteo', $cierre['id_caja']) }}">
                @csrf
                @method('PATCH')

                <label for="monto_final">Monto final contado</label>
                <input type="number" step="0.01" min="0" name="monto_final" id="monto_final" class="clay-input" inputmode="decimal" autocomplete="off" data-solo-numeros required>

                <label for="observaciones_conteo">Observaciones (opcional)</label>
                <input type="text" name="observaciones" id="observaciones_conteo" class="clay-input" maxlength="500" autocomplete="off">

                <div class="fila-acciones">
                    <button type="submit" class="clay-btn-primary">Guardar conteo</button>
                </div>
            </form>
        </div>
    @endif

    @if (!empty($cierre['movimientos']))
        <div class="clay-card">
            <div class="encabezado-flex">
                <h3>Movimientos de la caja</h3>
                <span class="subtitulo" style="font-size: 0.85rem; color: #64748B;">
                    💡 Tocá un movimiento para ver su detalle completo
                </span>
            </div>
            <table class="tabla-clickeable">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Medio</th>
                        <th>Concepto</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cierre['movimientos'] as $movimiento)
                        <tr onclick="abrirModalDetalleMovimiento({{ \Illuminate\Support\Js::from($movimiento) }})" title="Ver detalle del movimiento">
                            <td>{{ $etiquetasTipo[$movimiento['tipo']] ?? ucfirst(str_replace('_', ' ', $movimiento['tipo'])) }}</td>
                            <td>{{ $etiquetasMedio[$movimiento['medio_pago'] ?? 'efectivo'] ?? $movimiento['medio_pago'] }}</td>
                            <td>{{ $movimiento['concepto'] }}</td>
                            <td><strong>${{ number_format($movimiento['monto'], 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- MODAL DETALLE DE MOVIMIENTO EN CIERRE -->
        <div id="modal-detalle-movimiento" class="modal-overlay">
            <div class="clay-card modal-card" style="max-width: 520px;">
                <div class="modal-header">
                    <h3>Detalle de movimiento</h3>
                    <button type="button" class="modal-close" onclick="cerrarModalDetalleMovimiento()" aria-label="Cerrar modal">✕</button>
                </div>

                <div class="detalle-grid">
                    <div class="detalle-item">
                        <span class="detalle-label">ID Movimiento</span>
                        <span class="detalle-valor" id="det-mov-id">#--</span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Tipo</span>
                        <span class="detalle-valor" id="det-mov-tipo">--</span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Medio de Pago</span>
                        <span class="detalle-valor" id="det-mov-medio">--</span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Monto</span>
                        <span class="detalle-valor" id="det-mov-monto" style="color: var(--primary); font-size: 1.15rem;">$0,00</span>
                    </div>
                </div>

                <div class="detalle-grid" style="margin-top: 0;">
                    <div class="detalle-item" style="grid-column: 1 / -1;">
                        <span class="detalle-label">Concepto / Motivo</span>
                        <span class="detalle-valor" id="det-mov-concepto" style="font-weight: 500;">--</span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Fecha del Movimiento</span>
                        <span class="detalle-valor" id="det-mov-fecha">--</span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Fecha y Hora de Carga</span>
                        <span class="detalle-valor" id="det-mov-fecha-carga">--</span>
                    </div>
                    <div class="detalle-item" id="item-mov-cobro" style="display:none;">
                        <span class="detalle-label">Cobro Vinculado</span>
                        <span class="detalle-valor" id="det-mov-cobro">#--</span>
                    </div>
                    <div class="detalle-item" id="item-mov-usuario" style="display:none;">
                        <span class="detalle-label">Usuario Carga</span>
                        <span class="detalle-valor" id="det-mov-usuario">--</span>
                    </div>
                </div>

                <div id="seccion-modificacion-movimiento" class="alerta" style="background:#F8FAFC; border:1px solid #E2E8F0; padding:0.75rem; margin-top:0.5rem; display:none; font-size:0.85rem;">
                    <strong>Registro modificado:</strong>
                    <span id="det-mov-modificacion-texto"></span>
                </div>

                <div class="fila-acciones" style="justify-content: flex-end; margin-top: 1.25rem;">
                    <button type="button" class="clay-btn-secondary" onclick="cerrarModalDetalleMovimiento()">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script>
        const etiquetasTipo = @json($etiquetasTipo);
        const etiquetasMedio = @json($etiquetasMedio);

        function formatearMonto(numero) {
            return '$' + Number(numero).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function abrirModalDetalleMovimiento(m) {
            if (!m) return;
            document.getElementById('det-mov-id').textContent = '#' + (m.id_movimiento_caja || '--');
            document.getElementById('det-mov-tipo').textContent = etiquetasTipo[m.tipo] || (m.tipo ? m.tipo.replace(/_/g, ' ') : '--');
            document.getElementById('det-mov-medio').textContent = etiquetasMedio[m.medio_pago] || m.medio_pago || 'Efectivo';
            document.getElementById('det-mov-monto').textContent = formatearMonto(m.monto || 0);
            document.getElementById('det-mov-concepto').textContent = m.concepto || '--';

            const formatoFecha = (val) => {
                if (!val) return '--';
                const d = new Date(val);
                return isNaN(d.getTime()) ? val : d.toLocaleDateString('es-AR') + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
            };
            const formatoSoloFecha = (val) => {
                if (!val) return '--';
                if (typeof val === 'string' && val.includes('T')) val = val.split('T')[0];
                return val;
            };

            document.getElementById('det-mov-fecha').textContent = formatoSoloFecha(m.fecha);
            document.getElementById('det-mov-fecha-carga').textContent = formatoFecha(m.fecha_hora_carga);

            const itemCobro = document.getElementById('item-mov-cobro');
            if (m.id_cobro) {
                document.getElementById('det-mov-cobro').textContent = '#' + m.id_cobro;
                itemCobro.style.display = 'flex';
            } else {
                itemCobro.style.display = 'none';
            }

            const itemUsuario = document.getElementById('item-mov-usuario');
            if (m.id_usuario) {
                document.getElementById('det-mov-usuario').textContent = '#' + m.id_usuario;
                itemUsuario.style.display = 'flex';
            } else {
                itemUsuario.style.display = 'none';
            }

            const seccionMod = document.getElementById('seccion-modificacion-movimiento');
            if (m.fecha_hora_modificacion) {
                let txt = 'Modificado el ' + formatoFecha(m.fecha_hora_modificacion);
                if (m.monto_anterior !== null && m.monto_anterior !== undefined) {
                    txt += ' (monto previo: ' + formatearMonto(m.monto_anterior) + ')';
                }
                if (m.id_usuario_modificacion) {
                    txt += ' por usuario #' + m.id_usuario_modificacion;
                }
                document.getElementById('det-mov-modificacion-texto').textContent = txt;
                seccionMod.style.display = 'block';
            } else {
                seccionMod.style.display = 'none';
            }

            document.getElementById('modal-detalle-movimiento').classList.add('visible');
        }

        function cerrarModalDetalleMovimiento() {
            const modal = document.getElementById('modal-detalle-movimiento');
            if (modal) modal.classList.remove('visible');
        }

        const modalDet = document.getElementById('modal-detalle-movimiento');
        if (modalDet) {
            modalDet.addEventListener('click', function(e) {
                if (e.target === this) cerrarModalDetalleMovimiento();
            });
        }
        // Campos de monto: solo dígitos y separador decimal (se bloquean e, E, + y -).
        document.querySelectorAll('[data-solo-numeros]').forEach((campo) => {
            campo.addEventListener('keydown', (e) => {
                if (['e', 'E', '+', '-'].includes(e.key)) {
                    e.preventDefault();
                }
            });
            campo.addEventListener('paste', (e) => {
                const texto = (e.clipboardData || window.clipboardData).getData('text').trim();
                if (!/^\d*[.,]?\d*$/.test(texto)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</div>
@endsection
