@extends('layouts.app')

@section('titulo', 'Caja del dia')

@php
    // Etiquetas de los tipos de movimiento. Solo se muestran los que el rol puede usar
    // ($tiposPermitidos viene del controller).
    $etiquetasTipo = [
        'cobro' => 'Cobro',
        'ingreso_manual' => 'Ingreso manual',
        'egreso' => 'Egreso',
        'extraccion' => 'Extracción',
        'pago_proveedor' => 'Pago a proveedor',
        'impuesto' => 'Impuesto',
        'reversion' => 'Reversión de cobro',
    ];

    $etiquetasMedio = [
        'efectivo' => 'Efectivo',
        'cheque' => 'Cheque',
        'transferencia' => 'Transferencia',
        'otro' => 'Otro',
    ];

    $vencimiento = !empty($caja['fecha_hora_vencimiento'])
        ? \Illuminate\Support\Carbon::parse($caja['fecha_hora_vencimiento'])->timezone(config('app.timezone'))
        : null;
    $vencida = $caja['estado'] === 'abierta' && $vencimiento !== null && $vencimiento->isPast();

    // Recordatorio: faltan menos de 24 horas para el vencimiento y todavía no venció.
    $porVencer = $caja['estado'] === 'abierta'
        && $vencimiento !== null
        && !$vencida
        && $vencimiento->lte(now()->addHours(24));
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/g2-finanzas.css') }}">
@endpush

@section('contenido')
<div class="g2-page">
    @if ($porVencer)
        <div class="alerta" style="background:#FFF4D6;color:#6B4E00;border:1px solid #F0D98A;margin-bottom:16px;">
            <strong>Recordatorio:</strong> esta caja vence el {{ $vencimiento->format('d/m/Y H:i') }} (faltan menos de 24 horas).
            Cerrala antes de esa hora{{ !empty($cierreAutomatico) ? ': de lo contrario se cerrará automáticamente.' : '.' }}
        </div>
    @endif

    <div class="clay-card">
        <div class="encabezado-flex-inicio">
            <div>
                <h2>Caja del {{ \Illuminate\Support\Carbon::parse($caja['fecha'])->format('d/m/Y') }}</h2>
                <p class="subtitulo">
                    <span class="badge badge-{{ $caja['estado'] === 'abierta' ? 'abierto' : 'cerrado' }}">{{ ucfirst($caja['estado']) }}</span>
                    @if ($vencida)
                        <span class="badge badge-cerrado">Vencida</span>
                    @endif
                    · Monto inicial: ${{ number_format($caja['monto_inicial'], 2, ',', '.') }}
                    @if ($caja['estado'] === 'abierta')
                        · Monto actual: <strong id="monto-actual">${{ number_format($montoActual, 2, ',', '.') }}</strong>
                    @endif
                </p>
                @if ($caja['estado'] === 'abierta' && $vencimiento !== null)
                    <p class="subtitulo">
                        {{ $vencida ? 'Venció el' : 'Vence el' }} {{ $vencimiento->format('d/m/Y H:i') }}.
                        @if ($vencida)
                            @if (!empty($cierreAutomatico))
                                Se cerrará automáticamente en los próximos minutos.
                            @else
                                Cerrala cuando puedas: igual podés seguir cargando movimientos.
                            @endif
                        @endif
                    </p>
                @endif
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
        <div class="encabezado-flex">
            <h3>Movimientos de esta caja</h3>
            <span class="subtitulo" style="font-size: 0.85rem; color: #64748B;">
                💡 Tocá un movimiento para ver su detalle completo
            </span>
        </div>

        <div id="sin-movimientos" class="vacio" @if(!empty($caja['movimientos'])) style="display:none" @endif>
            <p>Todavia no registraste movimientos en esta caja.</p>
        </div>

        <table id="tabla-movimientos" class="tabla-clickeable" @if(empty($caja['movimientos'])) style="display:none" @endif>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Medio</th>
                    <th>Concepto</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody id="tabla-movimientos-body">
                @foreach ($caja['movimientos'] ?? [] as $movimiento)
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

    <!-- MODAL DETALLE DE MOVIMIENTO -->
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

    <div id="modal-movimiento" class="modal-overlay">
        <div class="clay-card modal-card">
            <h3>Registrar movimiento</h3>

            <div id="error-movimiento" class="alerta alerta-error" style="display:none"></div>

            <form id="form-movimiento" method="POST" action="{{ route('caja.movimiento', $caja['id_caja']) }}">
                @csrf

                <label for="tipo">Tipo</label>
                <select name="tipo" id="tipo" class="clay-input" required>
                    @foreach ($tiposPermitidos as $tipo)
                        <option value="{{ $tipo }}">{{ $etiquetasTipo[$tipo] ?? $tipo }}</option>
                    @endforeach
                </select>

                <label for="medio_pago">Medio de pago</label>
                <select name="medio_pago" id="medio_pago" class="clay-input" required>
                    @foreach ($etiquetasMedio as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>

                <label for="concepto">Concepto</label>
                <input type="text" name="concepto" id="concepto" class="clay-input" maxlength="200" autocomplete="off" required>

                <label for="monto">Monto</label>
                <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="clay-input" inputmode="decimal" autocomplete="off" data-solo-numeros required>

                <div class="fila-acciones">
                    <button type="submit" class="clay-btn-primary">Guardar movimiento</button>
                    <button type="button" class="clay-btn-secondary" onclick="cerrarModalMovimiento()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-cerrar" class="modal-overlay">
        <div class="clay-card modal-card">
            <h3>Cerrar caja</h3>
            <p class="subtitulo">Contá el efectivo e indicá el monto final.</p>

            @if ($caja['estado'] === 'abierta')
                <p class="subtitulo">
                    Monto actual según el sistema:
                    <strong id="monto-actual-cierre">${{ number_format($montoActual, 2, ',', '.') }}</strong>
                </p>
            @endif

            <form method="POST" action="{{ route('caja.cerrar', $caja['id_caja']) }}">
                @csrf
                @method('PATCH')

                <label for="monto_final">Monto final</label>
                <input type="number" step="0.01" min="0" name="monto_final" id="monto_final" class="clay-input" inputmode="decimal" autocomplete="off" data-solo-numeros required>

                <label for="observaciones_cierre">Observaciones (opcional)</label>
                <input type="text" name="observaciones" id="observaciones_cierre" class="clay-input" autocomplete="off">

                <div class="fila-acciones">
                    <button type="submit" class="clay-btn-primary">Confirmar cierre</button>
                    <button type="button" class="clay-btn-secondary" onclick="document.getElementById('modal-cerrar').classList.remove('visible')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const etiquetasTipo = @json($etiquetasTipo);
        const etiquetasMedio = @json($etiquetasMedio);

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

        function cerrarModalMovimiento() {
            document.getElementById('modal-movimiento').classList.remove('visible');
            document.getElementById('error-movimiento').style.display = 'none';
            document.getElementById('form-movimiento').reset();
        }

        function abrirModalDetalleMovimiento(m) {
            if (!m) return;
            document.getElementById('det-mov-id').textContent = '#' + (m.id_movimiento_caja || '--');
            document.getElementById('det-mov-tipo').textContent = etiquetasTipo[m.tipo] || (m.tipo ? m.tipo.replace(/_/g, ' ') : '--');
            document.getElementById('det-mov-medio').textContent = etiquetasMedio[m.medio_pago] || m.medio_pago || 'Efectivo';
            document.getElementById('det-mov-monto').textContent = formatearMonto(m.monto || 0);
            document.getElementById('det-mov-concepto').textContent = m.concepto || '--';

            // Fechas
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

            // Cobro vinculado
            const itemCobro = document.getElementById('item-mov-cobro');
            if (m.id_cobro) {
                document.getElementById('det-mov-cobro').textContent = '#' + m.id_cobro;
                itemCobro.style.display = 'flex';
            } else {
                itemCobro.style.display = 'none';
            }

            // Usuario de carga
            const itemUsuario = document.getElementById('item-mov-usuario');
            if (m.id_usuario) {
                document.getElementById('det-mov-usuario').textContent = '#' + m.id_usuario;
                itemUsuario.style.display = 'flex';
            } else {
                itemUsuario.style.display = 'none';
            }

            // Sección de modificación
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
            document.getElementById('modal-detalle-movimiento').classList.remove('visible');
        }

        // Cerrar con Escape o click en backdrop
        document.getElementById('modal-detalle-movimiento').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalDetalleMovimiento();
        });

        function formatearMonto(numero) {
            return '$' + Number(numero).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        document.getElementById('form-movimiento').addEventListener('submit', function (evento) {
            evento.preventDefault();

            const form = evento.target;
            const datos = new FormData(form);
            const token = document.querySelector('meta[name="csrf-token"]').content;
            const errorDiv = document.getElementById('error-movimiento');

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: datos,
            })
                .then(async (respuesta) => {
                    const cuerpo = await respuesta.json();

                    if (!respuesta.ok || cuerpo.error) {
                        errorDiv.textContent = cuerpo.mensaje || cuerpo.message || 'Ocurrio un error al registrar el movimiento.';
                        errorDiv.style.display = 'block';
                        return;
                    }

                    // Actualizar el monto actual (arriba de todo y en el modal de cierre).
                    ['monto-actual', 'monto-actual-cierre'].forEach((id) => {
                        const el = document.getElementById(id);
                        if (el) {
                            el.textContent = formatearMonto(cuerpo.monto_actual);
                        }
                    });

                    // Agregar la fila nueva a la tabla, y mostrar la tabla si estaba oculta.
                    const tabla = document.getElementById('tabla-movimientos');
                    const cuerpoTabla = document.getElementById('tabla-movimientos-body');
                    const sinMovimientos = document.getElementById('sin-movimientos');

                    tabla.style.display = 'table';
                    sinMovimientos.style.display = 'none';

                    const m = cuerpo.movimiento;
                    const fila = document.createElement('tr');
                    fila.title = 'Ver detalle del movimiento';
                    fila.addEventListener('click', () => abrirModalDetalleMovimiento(m));

                    const celdas = [
                        etiquetasTipo[m.tipo] || m.tipo.replace(/_/g, ' '),
                        etiquetasMedio[m.medio_pago] || etiquetasMedio.efectivo,
                        m.concepto,
                        formatearMonto(m.monto),
                    ];

                    celdas.forEach((texto, idx) => {
                        const td = document.createElement('td');
                        if (idx === 3) {
                            const strong = document.createElement('strong');
                            strong.textContent = texto;
                            td.appendChild(strong);
                        } else {
                            td.textContent = texto; // textContent: el concepto no se interpreta como HTML
                        }
                        fila.appendChild(td);
                    });

                    cuerpoTabla.appendChild(fila);

                    cerrarModalMovimiento();
                })
                .catch(() => {
                    errorDiv.textContent = 'No se pudo conectar con el servidor.';
                    errorDiv.style.display = 'block';
                });
        });
    </script>
</div>
@endsection
