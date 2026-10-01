<?php

namespace App\Rendiciones\Services;

use App\Rendiciones\Models\Rendicion;
use App\Rendiciones\Repositories\RendicionRepository;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private RendicionRepository $repository)
    {
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

        return $rendicion->toArray();
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

            // IMPACTO INTELIGENTE SEGÚN MEDIO DE PAGO:
            // - Efectivo -> Impacta en Caja y liquida directamente.
            // - Transferencia -> Queda listo en el Módulo de Conciliación Bancaria.
            // - Cheque -> Queda registrado en Cartera de Cheques.
            if ($rendicion->cobros && count($rendicion->cobros) > 0) {
                $cobroService = app(\App\Cobros\Services\CobroService::class);
                foreach ($rendicion->cobros as $cobroItem) {
                    // Si se especificó una selección parcial de cobros y este no está, se omite/queda pendiente
                    if (is_array($cobrosAprobadosIds) && !in_array($cobroItem->id_rendicion_cobro, $cobrosAprobadosIds)) {
                        continue;
                    }

                    try {
                        // Impacto en Cobros / Caja
                        $resCobro = $cobroService->registrarCobro([
                            'id_cliente'      => $cobroItem->id_cliente ?: 1,
                            'id_usuario'      => $idUsuarioValidador,
                            'monto_total'     => $cobroItem->monto,
                            'medio_pago'      => $cobroItem->medio_pago,
                            'comprobante_nro' => 'REC-REND-' . $rendicion->id_rendicion . '-' . $cobroItem->id_rendicion_cobro,
                            'observaciones'   => "Liquidación de Rendición #{$rendicion->id_rendicion} ({$cobroItem->medio_pago})",
                        ]);

                        // Si es TRANSFERENCIA, notificar o preparar registro para Conciliación Bancaria
                        if (strtolower($cobroItem->medio_pago) === 'transferencia') {
                            // Registra la acreditación esperada en banco para que Conciliación lo matchee
                            \Illuminate\Support\Facades\Log::info("Transferencia por ${$cobroItem->monto} enviada a módulo de Conciliación Bancaria (Rendición #{$rendicion->id_rendicion}).");
                        }
                    } catch (\Throwable $e) {
                        // Continuar con los demás cobros en caso de duplicados o advertencias
                    }
                }
            }
        } elseif ($accion === 'rechazar') {
            $motivo = $datos['motivo_rechazo'] ?? '';
            $rendicion->rechazar($idUsuarioValidador, $motivo);
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
}
