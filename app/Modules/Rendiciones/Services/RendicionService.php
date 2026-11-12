<?php

namespace App\Modules\Rendiciones\Services;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Rendiciones\Models\Rendicion;
use App\Modules\Rendiciones\Repositories\RendicionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * RendicionService
 *
 * Orquesta toda la lógica de negocio del módulo de Rendiciones.
 * Recibe datos del Controller (ya validados superficialmente),
 * aplica reglas de dominio, y delega el acceso a datos al Repository.
 *
 * Los Procesadores existentes (ProcesadorDeCobroRendicion, etc.) contienen
 * la lógica de validación pura que usamos internamente como helpers.
 */
class RendicionService
{
    private VentasApiClient $ventasClient;

    public function __construct(
        private RendicionRepository $repository,
        ?VentasApiClient $ventasClient = null
    ) {
        $this->ventasClient = $ventasClient ?? new VentasApiClient();
    }

    // -------------------------------------------------------------------------
    // H.U.: Registrar rendición de reparto
    // -------------------------------------------------------------------------

    /**
     * Registra una nueva rendición de reparto con sus cobros iniciales.
     *
     * Criterios de aceptación:
     * - Seleccionar el reparto realizado (id_entrega opcional si el repartidor no tiene entrega formal).
     * - Registrar fecha y usuario (repartidor).
     * - Ingresar múltiples cobros.
     * - Calcular total rendido.
     * - Guardar observaciones.
     */
    public function registrarRendicion(array $datos): array
    {
        $idRepartidor = (int) ($datos['id_repartidor'] ?? 0);

        // Regla de negocio: el repartidor debe existir y estar activo
        if (!$this->repository->repartidorEstaActivo($idRepartidor)) {
            throw new InvalidArgumentException(
                "El repartidor con id {$idRepartidor} no existe o no se encuentra activo."
            );
        }

        // Regla: si se informa id_entrega, debe existir
        $idEntrega = !empty($datos['id_entrega']) ? (int) $datos['id_entrega'] : null;
        if ($idEntrega !== null && !$this->repository->entregaExiste($idEntrega)) {
            throw new InvalidArgumentException(
                "La entrega con id {$idEntrega} no existe en el sistema."
            );
        }

        $cobros = $datos['cobros'] ?? [];
        $remitos = $datos['remitos'] ?? [];

        // Regla: debe tener al menos un cobro o remito
        if (empty($cobros) && empty($remitos)) {
            throw new InvalidArgumentException(
                "La rendición debe incluir al menos un cobro o un remito entregado."
            );
        }

        // Validar y sumar cobros usando el Procesador de lógica pura
        $procesadorCobro = new ProcesadorDeCobroRendicion();
        $totalRendido    = 0.0;

        foreach ($cobros as $cobro) {
            if (!is_numeric($cobro['monto'] ?? null) || $cobro['monto'] <= 0) {
                throw new InvalidArgumentException(
                    "Se detectó un monto inválido dentro del listado de cobros de la rendición."
                );
            }
            $totalRendido += (float) $cobro['monto'];
        }

        // Transacción atómica: crear rendición + agregar cobros
        return DB::transaction(function () use ($datos, $idRepartidor, $idEntrega, $cobros, $remitos, $totalRendido) {

            $rendicion = $this->repository->crear([
                'id_repartidor' => $idRepartidor,
                'id_entrega'    => $idEntrega,
                'fecha'         => $datos['fecha'] ?? now(),
                'total_rendido' => 0.0,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $cobrosRegistrados = [];
            foreach ($cobros as $cobro) {
                $cobrosRegistrados[] = $this->repository->agregarCobro($rendicion->id_rendicion, [
                    'id_cliente' => (int) ($cobro['id_cliente'] ?? 0),
                    'id_factura' => (int) ($cobro['id_factura'] ?? 0),
                    'monto'      => (float) $cobro['monto'],
                    'medio_pago' => strtolower(trim($cobro['medio_pago'] ?? 'efectivo')),
                ]);
            }

            return [
                'id_rendicion'              => $rendicion->id_rendicion,
                'id_repartidor'             => $rendicion->id_repartidor,
                'id_entrega'                => $rendicion->id_entrega,
                'fecha'                     => $rendicion->fecha,
                'total_rendido'             => $totalRendido,
                'estado'                    => $rendicion->estado,
                'observaciones'             => $rendicion->observaciones,
                'cantidad_cobros_asociados' => count($cobrosRegistrados),
                'cantidad_remitos'          => count($remitos),
            ];
        });
    }

    // -------------------------------------------------------------------------
    // H.U.: Consultar historial de rendiciones
    // -------------------------------------------------------------------------

    /**
     * Lista rendiciones con filtros opcionales (fecha, id_repartidor, estado).
     */
    public function listar(array $filtros): array
    {
        return $this->repository->listarConFiltros($filtros);
    }

    // -------------------------------------------------------------------------
    // H.U.: Obtener detalle de una rendición
    // -------------------------------------------------------------------------

    /**
     * Retorna el detalle completo de una rendición incluyendo sus cobros.
     */
    public function obtenerDetalle(int $idRendicion): array
    {
        $rendicion = $this->repository->buscarPorId($idRendicion);

        if (!$rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        $detalle = $rendicion->toArray();

        $repartidorNombre = \App\Modules\Shared\Services\UsuarioService::obtenerNombre((int) $rendicion->id_repartidor);
        $detalle['repartidor_nombre'] = $repartidorNombre;
        $detalle['repartidor'] = [
            'id_usuario' => (int) $rendicion->id_repartidor,
            'nombre'     => $repartidorNombre,
        ];

        if (!empty($detalle['cobros']) && is_array($detalle['cobros'])) {
            $clientesCache = [];
            foreach ($detalle['cobros'] as &$cobro) {
                $idCliente = (int) ($cobro['id_cliente'] ?? 0);
                if ($idCliente > 0 && !array_key_exists($idCliente, $clientesCache)) {
                    $clientesCache[$idCliente] = \App\Modules\Shared\Services\ClienteService::obtenerCliente($idCliente);
                }

                $clienteInfo = $clientesCache[$idCliente] ?? null;
                $cobro['cliente_nombre'] = !empty($clienteInfo['nombre'])
                    ? $clienteInfo['nombre']
                    : "Cliente #{$idCliente}";
                $cobro['cliente_cuit'] = $clienteInfo['cuit'] ?? '';
            }
            unset($cobro);
        }

        return $detalle;
    }

    // -------------------------------------------------------------------------
    // H.U.: Registrar cobros dentro de una rendición
    // -------------------------------------------------------------------------

    /**
     * Agrega un cobro a una rendición existente en estado pendiente.
     *
     * Criterios de aceptación:
     * - Seleccionar cliente y factura asociada.
     * - Registrar monto abonado.
     * - Permitir múltiples medios de pago.
     * - Validar montos inválidos.
     * - Asociar el cobro a una rendición existente.
     */
    public function agregarCobro(int $idRendicion, array $datos): array
    {
        $rendicion = $this->repository->buscarPorId($idRendicion);

        if (!$rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        if (!$rendicion->estaPendiente()) {
            throw new InvalidArgumentException(
                "Solo se pueden agregar cobros a rendiciones en estado pendiente. Estado actual: {$rendicion->estado}."
            );
        }

        // Delegar validación de datos del cobro al Procesador de lógica pura
        $procesadorCobro  = new ProcesadorDeCobroRendicion();
        $resultadoValidacion = $procesadorCobro->registrarCobro(array_merge($datos, [
            'id_rendicion' => $idRendicion,
        ]));

        if ($resultadoValidacion['error']) {
            throw new InvalidArgumentException($resultadoValidacion['mensaje']);
        }

        $cobro = $this->repository->agregarCobro($idRendicion, [
            'id_cliente' => (int) $datos['id_cliente'],
            'id_factura' => (int) $datos['id_factura'],
            'monto'      => (float) $datos['monto'],
            'medio_pago' => strtolower(trim($datos['medio_pago'])),
        ]);

        return $cobro->toArray();
    }

    // -------------------------------------------------------------------------
    // H.U.: Registrar devoluciones dentro de una rendición
    // -------------------------------------------------------------------------

    /**
     * Registra una devolución (pedido no entregado) dentro de una rendición.
     *
     * Criterios de aceptación:
     * - Seleccionar pedido, ingresar motivo, registrar observaciones.
     * - Asociar devolución a la rendición.
     * - Validar existencia del pedido.
     */
    public function registrarDevolucion(int $idRendicion, array $datos): array
    {
        $rendicion = $this->repository->buscarPorId($idRendicion);

        if (!$rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        if (!$rendicion->estaPendiente()) {
            throw new InvalidArgumentException(
                "Solo se pueden registrar devoluciones en rendiciones en estado pendiente."
            );
        }

        // Delegar a Procesador de lógica pura para validaciones de dominio
        $procesador  = new ProcesadorDeDevolucionRendicion();
        $resultado   = $procesador->registrarDevolucion(array_merge($datos, [
            'id_rendicion' => $idRendicion,
        ]));

        if ($resultado['error']) {
            throw new InvalidArgumentException($resultado['mensaje']);
        }

        return $resultado['data'];
    }

    // -------------------------------------------------------------------------
    // H.U.: Registrar diferencias en una rendición
    // -------------------------------------------------------------------------

    /**
     * Calcula y registra una diferencia entre lo esperado y lo rendido.
     *
     * Criterios de aceptación:
     * - Calcular diferencia automáticamente.
     * - Permitir registrar motivo y observaciones.
     * - Marcar rendición con diferencia pendiente.
     */
    public function registrarDiferencia(int $idRendicion, array $datos): array
    {
        $rendicion = $this->repository->buscarPorId($idRendicion);

        if (!$rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        $procesador = new ProcesadorDeDiferenciaRendicion();
        $resultado  = $procesador->registrarDiferencia(array_merge($datos, [
            'id_rendicion'  => $idRendicion,
            'total_rendido' => $rendicion->total_rendido,
        ]));

        if ($resultado['error']) {
            throw new InvalidArgumentException($resultado['mensaje']);
        }

        return $resultado['data'];
    }

    // -------------------------------------------------------------------------
    // H.U.: Aprobar o rechazar rendiciones
    // -------------------------------------------------------------------------

    /**
     * Cambia el estado de una rendición a 'aprobada' o 'rechazada'.
     *
     * Criterios de aceptación:
     * - Solo admin puede revisar.
     * - Cambiar estado con registro de usuario validador y fecha.
     * - Motivo de rechazo obligatorio si se rechaza.
     * Estados permitidos: pendiente → aprobada | rechazada.
     */
    public function revisarRendicion(int $idRendicion, array $datos): array
    {
        $rendicion = $this->repository->buscarPorId($idRendicion);

        if (!$rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        $accion             = strtolower(trim($datos['accion'] ?? ''));
        $idUsuarioValidador = (int) ($datos['id_usuario_validador'] ?? 0);
        $cobrosAprobadosIds = $datos['cobros_aprobados'] ?? null; // IDs de cobros individuales a aprobar si es revisión parcial

        if ($accion === 'aprobar') {
            $rendicion->aprobar($idUsuarioValidador);
            $this->repository->guardar($rendicion);

            // IMPACTO INTELIGENTE SEGÚN MEDIO DE PAGO:
            // - Efectivo -> Impacta directamente en Caja abierta.
            // - Transferencia -> Queda registrado para Conciliación Bancaria.
            // - Cheque -> Queda registrado en Cartera de Cheques.
            if ($rendicion->cobros && count($rendicion->cobros) > 0) {
                foreach ($rendicion->cobros as $cobroItem) {
                    // Si se especificó una selección parcial de cobros y este no está, se omite/queda pendiente
                    if (is_array($cobrosAprobadosIds) && !in_array($cobroItem->id_rendicion_cobro, $cobrosAprobadosIds)) {
                        continue;
                    }

                    $medioPagoLower = strtolower(trim($cobroItem->medio_pago ?? ''));

                    if ($medioPagoLower === 'efectivo') {
                        try {
                            $cajaService = app(\App\Modules\Caja\Services\CajaService::class);
                            $cajaService->registrarCobroEnEfectivo(
                                $idUsuarioValidador,
                                \App\Modules\Caja\Models\Caja::ROL_ADMIN,
                                (float) $cobroItem->monto,
                                "Liquidación Rendición #{$rendicion->id_rendicion} - Cobro #{$cobroItem->id_rendicion_cobro}",
                                null
                            );
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("No se pudo registrar cobro en Caja para rendición #{$rendicion->id_rendicion}: " . $e->getMessage());
                        }
                    } elseif ($medioPagoLower === 'transferencia') {
                        \Illuminate\Support\Facades\Log::info("Transferencia por \${$cobroItem->monto} registrada para Conciliación Bancaria (Rendición #{$rendicion->id_rendicion}).");
                    } elseif ($medioPagoLower === 'cheque') {
                        \Illuminate\Support\Facades\Log::info("Cheque por \${$cobroItem->monto} registrado en Cartera (Rendición #{$rendicion->id_rendicion}).");
                    }

                    // Imputar automáticamente el cobro en la Cuenta Corriente del cliente vía API de G4
                    if (!empty($cobroItem->id_rendicion_cobro)) {
                        try {
                            $this->ventasClient->registrarCobroCuentaCorriente((int) $cobroItem->id_rendicion_cobro);
                        } catch (\Throwable $e) {
                            Log::warning("G2 - No se pudo imputar cobro #{$cobroItem->id_rendicion_cobro} en cuenta corriente (G4): " . $e->getMessage());
                        }
                    }

                    // Notificación tolerante a la API de Ventas (G4) sobre la imputación del cobro
                    if (!empty($cobroItem->id_factura)) {
                        try {
                            $this->ventasClient->actualizarEstadoVenta((int) $cobroItem->id_factura, 'pagada');
                        } catch (\Throwable $e) {
                            Log::warning("G2 - No se pudo actualizar estado en G4 para venta/factura #{$cobroItem->id_factura}: " . $e->getMessage());
                        }
                    }
                }
            }

            // Si la rendición está vinculada a una entrega de reparto, marcar la entrega como 'finalizada'
            // únicamente si todos los pedidos de ese viaje ya fueron entregados.
            if (!empty($rendicion->id_entrega)) {
                try {
                    $pedidosNoEntregados = DB::table('entrega_pedido')
                        ->where('id_entrega', $rendicion->id_entrega)
                        ->where('estado_pedido_entrega', '!=', 'entregado')
                        ->count();

                    if ($pedidosNoEntregados === 0) {
                        DB::table('entrega')
                            ->where('id_entrega', $rendicion->id_entrega)
                            ->update(['estado' => 'finalizada']);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("No se pudo marcar entrega #{$rendicion->id_entrega} como finalizada: " . $e->getMessage());
                }
            }
        } elseif ($accion === 'rechazar') {
            $motivo = $datos['motivo_rechazo'] ?? '';
            $rendicion->rechazar($idUsuarioValidador, $motivo);

            // Reversión auditada en Caja si alguno de los cobros en efectivo ya había impactado en Caja
            if ($rendicion->cobros && count($rendicion->cobros) > 0) {
                foreach ($rendicion->cobros as $cobroItem) {
                    if (strtolower(trim($cobroItem->medio_pago ?? '')) === 'efectivo') {
                        try {
                            $cajaService = app(\App\Modules\Caja\Services\CajaService::class);
                            $cajaService->registrarReversionDeCobro(
                                $idUsuarioValidador,
                                \App\Modules\Caja\Models\Caja::ROL_ADMIN,
                                (float) $cobroItem->monto,
                                "Reversión por rechazo de Rendición #{$rendicion->id_rendicion} (Cobro #{$cobroItem->id_rendicion_cobro})",
                                (int) $cobroItem->id_rendicion_cobro,
                                \App\Modules\Caja\Models\CajaMovimiento::MEDIO_EFECTIVO
                            );
                        } catch (\Throwable $e) {
                            Log::info("No se requirió reversión inmediata en caja para cobro #{$cobroItem->id_rendicion_cobro}: " . $e->getMessage());
                        }
                    }
                }
            }
        } else {
            throw new InvalidArgumentException(
                "La acción '{$accion}' no es válida. Opciones permitidas: aprobar, rechazar."
            );
        }

        $this->repository->guardar($rendicion);

        return [
            'id_rendicion'         => $rendicion->id_rendicion,
            'estado_actual'        => $rendicion->estado,
            'id_usuario_validador' => $rendicion->id_usuario_validador,
            'fecha_validacion'     => $rendicion->fecha_validacion,
            'motivo_rechazo'       => $rendicion->motivo_rechazo,
        ];
    }

    /**
     * Obtiene y estructura los datos de una entrega y sus pedidos entregados para precargar una nueva rendición.
     */
    public function obtenerDatosParaRendir(int $idEntrega): array
    {
        $entrega = DB::table('entrega as e')
            ->where('e.id_entrega', $idEntrega)
            ->first();

        if (!$entrega) {
            throw new InvalidArgumentException("La entrega #{$idEntrega} no existe.");
        }

        $repartidorNombre = \App\Modules\Shared\Services\UsuarioService::obtenerNombre((int)$entrega->id_repartidor);

        $tieneCobroCols = \Illuminate\Support\Facades\Schema::hasColumn('entrega_pedido', 'monto_cobrado')
            && \Illuminate\Support\Facades\Schema::hasColumn('entrega_pedido', 'medio_pago');

        $columnas = [
            'ep.id_venta',
            'ep.estado_pedido_entrega',
            'r.id_remito',
            'r.numero_remito',
        ];
        if ($tieneCobroCols) {
            $columnas[] = 'ep.monto_cobrado';
            $columnas[] = 'ep.medio_pago';
        }

        // Consultar entrega_pedido y remito (Tablas propias de G2)
        $pedidosRaw = DB::table('entrega_pedido as ep')
            ->leftJoin('remito as r', function ($join) {
                $join->on('ep.id_entrega', '=', 'r.id_entrega')
                     ->on('ep.id_venta', '=', 'r.id_venta');
            })
            ->where('ep.id_entrega', $idEntrega)
            ->select($columnas)
            ->get();

        // Sumar lo ya rendido y aprobado por cada factura/pedido en esta entrega
        $aprobadoPorFactura = DB::table('RENDICION_COBRO as rc')
            ->join('RENDICION as r', 'rc.id_rendicion', '=', 'r.id_rendicion')
            ->where('r.id_entrega', $idEntrega)
            ->where('r.estado', Rendicion::ESTADO_APROBADA)
            ->groupBy('rc.id_factura')
            ->selectRaw('rc.id_factura, SUM(rc.monto) as total_aprobado')
            ->pluck('total_aprobado', 'rc.id_factura')
            ->map(fn ($total) => (float) $total)
            ->all();

        $cobrosSugeridos = [];
        $totalRendidoSugerido = 0.0;

        foreach ($pedidosRaw as $p) {
            $idVenta = (int) $p->id_venta;
            $venta = $this->ventasClient->obtenerVentaPorId($idVenta);
            $montoPedido = (float)($venta['total'] ?? $venta['monto_total'] ?? 0.0);
            $yaAprobado = (float) ($aprobadoPorFactura[$idVenta] ?? 0.0);
            $saldoPendiente = max(0.0, round($montoPedido - $yaAprobado, 2));

            // Si ya se aprobó el 100% del pedido (o más), no volver a sugerirlo
            if ($yaAprobado > 0 && $saldoPendiente <= 0.009) {
                continue;
            }

            if ($yaAprobado > 0) {
                // Hubo un pago parcial previo aprobado: sugerir el saldo pendiente
                $montoSugerido = $saldoPendiente;
            } else {
                $montoSugerido = ($tieneCobroCols && $p->monto_cobrado !== null)
                    ? (float) $p->monto_cobrado
                    : $montoPedido;
            }

            $medioPago = ($tieneCobroCols && !empty($p->medio_pago))
                ? strtolower((string) $p->medio_pago)
                : 'efectivo';
            $idCliente = (int)($venta['id_cliente'] ?? 1);
            $clienteNombre = $venta['cliente_nombre'] ?? $venta['cliente']['nombre'] ?? "Cliente Pedido #{$p->id_venta}";

            $cobrosSugeridos[] = [
                'id_venta' => $idVenta,
                'id_factura' => $idVenta,
                'id_cliente' => $idCliente,
                'cliente_nombre' => $clienteNombre,
                'monto' => $montoSugerido,
                'monto_total_pedido' => $montoPedido,
                'monto_ya_rendido' => $yaAprobado,
                'saldo_pendiente' => $saldoPendiente,
                'medio_pago' => $medioPago,
                'id_remito' => $p->id_remito,
                'numero_remito' => $p->numero_remito,
                'entregado' => $p->estado_pedido_entrega === 'entregado'
            ];
            if ($p->estado_pedido_entrega === 'entregado') {
                $totalRendidoSugerido += $montoSugerido;
            }
        }

        return [
            'id_entrega' => (int) $entrega->id_entrega,
            'id_repartidor' => (int) $entrega->id_repartidor,
            'repartidor_nombre' => $repartidorNombre,
            'fecha_salida' => $entrega->fecha_salida ?? date('Y-m-d'),
            'estado_entrega' => $entrega->estado,
            'total_esperado' => round($totalRendidoSugerido, 2),
            'cobros_sugeridos' => $cobrosSugeridos,
            'cantidad_pedidos' => count($cobrosSugeridos)
        ];
    }

    /**
     * Precarga o actualiza automáticamente una Rendición en estado 'pendiente' a partir de una Entrega.
     * Permite que el chofer no tenga que iniciar la rendición manualmente, sino que el sistema
     * envíe automáticamente los datos al historial de rendiciones para que el encargado las administre.
     *
     * @param array<int, array{monto_cobrado?: float|null, medio_pago?: string|null}> $cobrosOverride
     */
    public function precargarOActualizarRendicionDesdeEntrega(int $idEntrega, array $cobrosOverride = []): ?array
    {
        $datos = $this->obtenerDatosParaRendir($idEntrega);
        $cobrosSugeridos = array_map(function (array $c) use ($cobrosOverride): array {
            $idVenta = (int) $c['id_venta'];
            if (isset($cobrosOverride[$idVenta])) {
                if (array_key_exists('monto_cobrado', $cobrosOverride[$idVenta]) && $cobrosOverride[$idVenta]['monto_cobrado'] !== null) {
                    $c['monto'] = (float) $cobrosOverride[$idVenta]['monto_cobrado'];
                }
                if (!empty($cobrosOverride[$idVenta]['medio_pago'])) {
                    $c['medio_pago'] = strtolower((string) $cobrosOverride[$idVenta]['medio_pago']);
                }
            }

            return $c;
        }, $datos['cobros_sugeridos']);

        $pedidosEntregados = array_values(array_filter(
            $cobrosSugeridos,
            fn($c) => !empty($c['entregado']) && (float) ($c['monto'] ?? 0) > 0
        ));

        if (empty($pedidosEntregados)) {
            return null;
        }

        return DB::transaction(function () use ($idEntrega, $datos, $pedidosEntregados) {
            // 1. Buscar si existe una rendición PENDIENTE para esta entrega.
            // Si las anteriores ya fueron aprobadas o rechazadas, se crea una nueva pendiente para los nuevos pedidos.
            $rendicionPendiente = DB::table('RENDICION')
                ->where('id_entrega', $idEntrega)
                ->where('estado', Rendicion::ESTADO_PENDIENTE)
                ->orderByDesc('id_rendicion')
                ->first();

            if ($rendicionPendiente) {
                $idRendicion = (int) $rendicionPendiente->id_rendicion;
                $facturasExistentes = DB::table('RENDICION_COBRO')
                    ->where('id_rendicion', $idRendicion)
                    ->pluck('id_factura')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($pedidosEntregados as $p) {
                    if (in_array((int) $p['id_factura'], $facturasExistentes, true)) {
                        continue;
                    }

                    DB::table('RENDICION_COBRO')->insert([
                        'id_rendicion'   => $idRendicion,
                        'id_cliente'     => $p['id_cliente'],
                        'id_factura'     => $p['id_factura'],
                        'monto'          => $p['monto'],
                        'medio_pago'     => $p['medio_pago'] ?? 'efectivo',
                        'fecha_registro' => now(),
                    ]);
                }

                $totalCalculado = (float) DB::table('RENDICION_COBRO')
                    ->where('id_rendicion', $idRendicion)
                    ->sum('monto');

                DB::table('RENDICION')
                    ->where('id_rendicion', $idRendicion)
                    ->update([
                        'total_rendido' => $totalCalculado,
                        'fecha'         => now(),
                        'observaciones' => "Rendición precargada automáticamente desde Entrega #{$idEntrega}."
                    ]);
            } else {
                $totalCalculado = 0.0;
                foreach ($pedidosEntregados as $p) {
                    $totalCalculado += (float) $p['monto'];
                }

                $idRendicion = (int) DB::table('RENDICION')->insertGetId([
                    'id_repartidor' => $datos['id_repartidor'],
                    'id_entrega'    => $idEntrega,
                    'fecha'         => now(),
                    'total_rendido' => $totalCalculado,
                    'estado'        => Rendicion::ESTADO_PENDIENTE,
                    'observaciones' => "Rendición precargada automáticamente al confirmar reparto #{$idEntrega}."
                ]);

                foreach ($pedidosEntregados as $p) {
                    DB::table('RENDICION_COBRO')->insert([
                        'id_rendicion'   => $idRendicion,
                        'id_cliente'     => $p['id_cliente'],
                        'id_factura'     => $p['id_factura'],
                        'monto'          => $p['monto'],
                        'medio_pago'     => $p['medio_pago'] ?? 'efectivo',
                        'fecha_registro' => now(),
                    ]);
                }
            }

            return [
                'id_rendicion'   => $idRendicion,
                'id_entrega'     => $idEntrega,
                'total_rendido'  => $totalCalculado,
                'estado'         => Rendicion::ESTADO_PENDIENTE,
                'pedidos_count'  => count($pedidosEntregados)
            ];
        });
    }
}
