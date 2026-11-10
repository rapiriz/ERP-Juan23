<?php

namespace App\Modules\Rendiciones\Services;

use App\Modules\Entregas\Integraciones\VentasApiClient;
use App\Modules\Rendiciones\Repositories\RendicionRepository;
use App\Modules\Shared\Services\UsuarioService;
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
    private VentasApiClient $ventasClient;

    public function __construct(
        private RendicionRepository $repository,
        ?VentasApiClient $ventasClient = null
    ) {
        $this->ventasClient = $ventasClient ?? new VentasApiClient;
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
        if (! $this->repository->repartidorEstaActivo($idRepartidor)) {
            throw new InvalidArgumentException(
                "El repartidor con id {$idRepartidor} no existe o no se encuentra activo."
            );
        }

        // Regla: si se informa id_entrega, debe existir
        $idEntrega = ! empty($datos['id_entrega']) ? (int) $datos['id_entrega'] : null;
        if ($idEntrega !== null && ! $this->repository->entregaExiste($idEntrega)) {
            throw new InvalidArgumentException(
                "La entrega con id {$idEntrega} no existe en el sistema."
            );
        }

        $cobros = $datos['cobros'] ?? [];
        $remitos = $datos['remitos'] ?? [];

        // Regla: debe tener al menos un cobro o remito
        if (empty($cobros) && empty($remitos)) {
            throw new InvalidArgumentException(
                'La rendición debe incluir al menos un cobro o un remito entregado.'
            );
        }

        // Validar y sumar cobros usando el Procesador de lógica pura
        $procesadorCobro = new ProcesadorDeCobroRendicion;
        $totalRendido = 0.0;

        foreach ($cobros as $cobro) {
            if (! is_numeric($cobro['monto'] ?? null) || $cobro['monto'] <= 0 || $cobro['monto'] > 99999999.99 || abs($cobro['monto'] * 100 - round($cobro['monto'] * 100)) > 0.0001) {
                throw new InvalidArgumentException(
                    'Se detectó un monto inválido dentro del listado de cobros de la rendición.'
                );
            }
            $totalRendido += (float) $cobro['monto'];
        }

        // Transacción atómica: crear rendición + agregar cobros
        return DB::transaction(function () use ($datos, $idRepartidor, $idEntrega, $cobros, $remitos, $totalRendido) {

            $rendicion = $this->repository->crear([
                'id_repartidor' => $idRepartidor,
                'id_entrega' => $idEntrega,
                'fecha' => $datos['fecha'] ?? now(),
                'total_rendido' => 0.0,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $cobrosRegistrados = [];
            foreach ($cobros as $cobro) {
                $cobrosRegistrados[] = $this->repository->agregarCobro($rendicion->id_rendicion, [
                    'id_cliente' => (int) ($cobro['id_cliente'] ?? 0),
                    'id_factura' => (int) ($cobro['id_factura'] ?? 0),
                    'monto' => (float) $cobro['monto'],
                    'medio_pago' => strtolower(trim($cobro['medio_pago'] ?? 'efectivo')),
                    'datos_medio_pago' => $cobro['datos_medio_pago'] ?? null,
                ]);
            }

            return [
                'id_rendicion' => $rendicion->id_rendicion,
                'id_repartidor' => $rendicion->id_repartidor,
                'id_entrega' => $rendicion->id_entrega,
                'fecha' => $rendicion->fecha,
                'total_rendido' => $totalRendido,
                'estado' => $rendicion->estado,
                'observaciones' => $rendicion->observaciones,
                'cantidad_cobros_asociados' => count($cobrosRegistrados),
                'cantidad_remitos' => count($remitos),
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

        if (! $rendicion) {
            throw new InvalidArgumentException(
                "No se encontró la rendición con id {$idRendicion}."
            );
        }

        return [...$rendicion->toArray(),
            'devoluciones' => DB::table('g2_rendicion_devoluciones')->where('id_rendicion', $idRendicion)->orderBy('id')->get()->toArray(),
            'diferencia' => DB::table('g2_rendicion_diferencias')->where('id_rendicion', $idRendicion)->first()];
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
        return DB::transaction(function () use ($idRendicion, $datos) {
            $rendicion = $this->repository->buscarPorId($idRendicion);

            if (! $rendicion) {
                throw new InvalidArgumentException(
                    "No se encontró la rendición con id {$idRendicion}."
                );
            }

            if (! $rendicion->estaPendiente()) {
                throw new InvalidArgumentException(
                    "Solo se pueden agregar cobros a rendiciones en estado pendiente. Estado actual: {$rendicion->estado}."
                );
            }

            // Delegar validación de datos del cobro al Procesador de lógica pura
            $procesadorCobro = new ProcesadorDeCobroRendicion;
            $resultadoValidacion = $procesadorCobro->registrarCobro(array_merge($datos, [
                'id_rendicion' => $idRendicion,
            ]));

            if ($resultadoValidacion['error']) {
                throw new InvalidArgumentException($resultadoValidacion['mensaje']);
            }

            $cobro = $this->repository->agregarCobro($idRendicion, [
                'id_cliente' => (int) $datos['id_cliente'],
                'id_factura' => (int) $datos['id_factura'],
                'monto' => (float) $datos['monto'],
                'medio_pago' => strtolower(trim($datos['medio_pago'])),
                'datos_medio_pago' => $datos['datos_medio_pago'] ?? null,
            ]);

            return $cobro->toArray();
        });
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
        return app(RenditionIncidentService::class)->returned($idRendicion, $datos);
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
        return app(RenditionIncidentService::class)->difference($idRendicion, $datos);
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
        return app(RenditionReviewService::class)->review($idRendicion, $datos);
    }

    /**
     * Obtiene y estructura los datos de una entrega y sus pedidos entregados para precargar una nueva rendición.
     */
    public function obtenerDatosParaRendir(int $idEntrega): array
    {
        $entrega = DB::table('entrega as e')
            ->where('e.id_entrega', $idEntrega)
            ->first();

        if (! $entrega) {
            throw new InvalidArgumentException("La entrega #{$idEntrega} no existe.");
        }

        $repartidorNombre = UsuarioService::obtenerNombre((int) $entrega->id_repartidor);

        // Consultar entrega_pedido y remito (Tablas propias de G2)
        $pedidosRaw = DB::table('entrega_pedido as ep')
            ->leftJoin('remito as r', function ($join) {
                $join->on('ep.id_entrega', '=', 'r.id_entrega')
                    ->on('ep.id_venta', '=', 'r.id_venta')->where('r.estado', '!=', 'anulado');
            })
            ->where('ep.id_entrega', $idEntrega)
            ->select([
                'ep.id_venta',
                'ep.estado_pedido_entrega',
                'r.id_remito',
                'r.numero_remito',
            ])
            ->get();

        $cobrosSugeridos = [];
        $totalRendidoSugerido = 0.0;

        foreach ($pedidosRaw as $p) {
            $venta = $this->ventasClient->obtenerVentaPorId((int) $p->id_venta);
            if (! $venta) {
                throw new \UnexpectedValueException('Ventas no devolvió los datos reales del pedido para rendir.');
            }
            $monto = (float) ($venta['total'] ?? $venta['monto_total'] ?? 0.0);
            $idCliente = (int) ($venta['id_cliente'] ?? 1);
            $clienteNombre = $venta['cliente_nombre'] ?? $venta['cliente']['nombre'] ?? "Cliente Pedido #{$p->id_venta}";

            $cobrosSugeridos[] = [
                'id_venta' => (int) $p->id_venta,
                'id_factura' => (int) $p->id_venta,
                'id_cliente' => $idCliente,
                'cliente_nombre' => $clienteNombre,
                'monto' => $monto,
                'medio_pago' => 'efectivo',
                'id_remito' => $p->id_remito,
                'numero_remito' => $p->numero_remito,
                'entregado' => $p->estado_pedido_entrega === 'entregado',
            ];
            if ($p->estado_pedido_entrega === 'entregado') {
                $totalRendidoSugerido += $monto;
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
            'cantidad_pedidos' => count($pedidosRaw),
        ];
    }

    /**
     * Precarga o actualiza automáticamente una Rendición en estado 'pendiente' a partir de una Entrega.
     * Permite que el chofer no tenga que iniciar la rendición manualmente, sino que el sistema
     * envíe automáticamente los datos al historial de rendiciones para que el encargado las administre.
     */
    public function precargarOActualizarRendicionDesdeEntrega(int $idEntrega): ?array
    {
        $datos = $this->obtenerDatosParaRendir($idEntrega);
        $pedidosEntregados = array_filter($datos['cobros_sugeridos'], fn ($c) => ! empty($c['entregado']));

        if (empty($pedidosEntregados)) {
            return null;
        }

        // Una entrega puede rendirse por tramos. No reabrir ni reemplazar lotes ya revisados.
        $rendicionExistente = DB::table('RENDICION')->where('id_entrega', $idEntrega)->where('estado', 'pendiente')->orderByDesc('id_rendicion')->first();
        $closedInvoices = DB::table('RENDICION_COBRO as c')->join('RENDICION as r', 'r.id_rendicion', '=', 'c.id_rendicion')->where('r.id_entrega', $idEntrega)->where('r.estado', '!=', 'pendiente')->pluck('c.id_factura')->all();
        $pedidosEntregados = array_filter($pedidosEntregados, fn ($p) => ! in_array($p['id_factura'], $closedInvoices));
        if (! $pedidosEntregados) {
            return $rendicionExistente ? (array) $rendicionExistente : null;
        }

        $totalCalculado = 0.0;
        foreach ($pedidosEntregados as $p) {
            $totalCalculado += (float) $p['monto'];
        }

        if ($rendicionExistente) {
            $idRendicion = (int) $rendicionExistente->id_rendicion;
            DB::table('RENDICION')
                ->where('id_rendicion', $idRendicion)
                ->update([
                    'total_rendido' => $totalCalculado,
                    'fecha' => now(),
                    'observaciones' => "Rendición precargada automáticamente desde Entrega #{$idEntrega}.",
                ]);

            // Conservar cobros declarados, revisados y sus referencias financieras.
        } else {
            $idRendicion = (int) DB::table('RENDICION')->insertGetId([
                'id_repartidor' => $datos['id_repartidor'],
                'id_entrega' => $idEntrega,
                'fecha' => now(),
                'total_rendido' => $totalCalculado,
                'estado' => 'pendiente',
                'observaciones' => "Rendición precargada automáticamente al confirmar reparto #{$idEntrega}.",
            ]);
        }

        // Insertar el desglose de cobros en RENDICION_COBRO
        foreach ($pedidosEntregados as $p) {
            if (DB::table('RENDICION_COBRO')->where('id_rendicion', $idRendicion)->where('id_factura', $p['id_factura'])->exists()) {
                continue;
            }
            DB::table('RENDICION_COBRO')->insert([
                'id_rendicion' => $idRendicion,
                'id_cliente' => $p['id_cliente'],
                'id_factura' => $p['id_factura'],
                'monto' => $p['monto'],
                'medio_pago' => $p['medio_pago'] ?? 'efectivo',
                'fecha_registro' => now(),
            ]);
        }

        $totalCalculado = (float) DB::table('RENDICION_COBRO')->where('id_rendicion', $idRendicion)->sum('monto');
        DB::table('RENDICION')->where('id_rendicion', $idRendicion)->update(['total_rendido' => $totalCalculado]);

        return [
            'id_rendicion' => $idRendicion,
            'id_entrega' => $idEntrega,
            'total_rendido' => $totalCalculado,
            'estado' => 'pendiente',
            'pedidos_count' => count($pedidosEntregados),
        ];
    }
}
