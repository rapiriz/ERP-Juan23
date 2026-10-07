<?php

namespace App\Rendiciones\Repositories;

use App\Rendiciones\Models\Rendicion;
use App\Rendiciones\Models\RendicionCobro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RendicionRepository
 *
 * Capa de acceso a datos para el módulo de Rendiciones.
 * Encapsula todas las consultas a la BD, siguiendo el patrón del resto del proyecto
 * (ver CobroRepository para referencia de estilo).
 */
class RendicionRepository
{
    /**
     * Crea una nueva rendición en estado 'pendiente'.
     */
    public function crear(array $datos): Rendicion
    {
        return Rendicion::create([
            'id_repartidor' => $datos['id_repartidor'],
            'id_entrega'    => $datos['id_entrega'] ?? null,
            'fecha'         => $datos['fecha'] ?? now(),
            'total_rendido' => $datos['total_rendido'] ?? 0.0,
            'estado'        => Rendicion::ESTADO_PENDIENTE,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);
    }

    /**
     * Busca una rendición por su ID, cargando sus cobros asociados.
     */
    public function buscarPorId(int $idRendicion): ?Rendicion
    {
        return Rendicion::with('cobros')->find($idRendicion);
    }

    /**
     * Lista rendiciones aplicando filtros opcionales:
     *   - fecha       (string Y-m-d)
     *   - id_repartidor (int)
     *   - estado      (pendiente | aprobada | rechazada)
     *
     * Devuelve colección ordenada por fecha descendente.
     */
    public function listarConFiltros(array $filtros): array
    {
        $query = Rendicion::with('cobros');

        if (!empty($filtros['fecha'])) {
            $query->whereDate('fecha', $filtros['fecha']);
        }

        if (!empty($filtros['id_repartidor'])) {
            $query->where('id_repartidor', (int) $filtros['id_repartidor']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', strtolower($filtros['estado']));
        }

        return $query->orderBy('fecha', 'desc')->get()->toArray();
    }

    /**
     * Actualiza el estado de una rendición (aprobar o rechazar).
     */
    public function actualizarEstado(
        int $idRendicion,
        string $nuevoEstado,
        int $idUsuarioValidador,
        ?string $motivoRechazo = null
    ): Rendicion {
        $rendicion = Rendicion::findOrFail($idRendicion);

        $rendicion->estado               = $nuevoEstado;
        $rendicion->id_usuario_validador = $idUsuarioValidador;
        $rendicion->fecha_validacion     = now();
        $rendicion->motivo_rechazo       = $motivoRechazo;
        $rendicion->save();

        return $rendicion->fresh('cobros');
    }

    /**
     * Persiste los cambios de una rendición en la base de datos.
     */
    public function guardar(Rendicion $rendicion): Rendicion
    {
        $rendicion->save();
        return $rendicion;
    }

    /**
     * Agrega un cobro individual a una rendición existente y actualiza total_rendido.
     */
    public function agregarCobro(int $idRendicion, array $datosCobro): RendicionCobro
    {
        $cobro = RendicionCobro::create([
            'id_rendicion'   => $idRendicion,
            'id_cliente'     => $datosCobro['id_cliente'],
            'id_factura'     => $datosCobro['id_factura'],
            'monto'          => $datosCobro['monto'],
            'medio_pago'     => $datosCobro['medio_pago'],
            'fecha_registro' => now(),
        ]);

        // Actualiza el total acumulado de la rendición
        DB::table('RENDICION')
            ->where('id_rendicion', $idRendicion)
            ->increment('total_rendido', $datosCobro['monto']);

        return $cobro;
    }

    /**
     * Verifica si el repartidor dado existe y está activo en la tabla USUARIO.
     */
    public function repartidorEstaActivo(int $idRepartidor): bool
    {
        if (!Schema::hasTable('USUARIO')) {
            return $idRepartidor > 0;
        }

        return DB::table('USUARIO')
            ->where('id_usuario', $idRepartidor)
            ->where('rol', 'repartidor')
            ->where('estado', 'activo')
            ->exists();
    }

    /**
     * Verifica si la entrega dada existe en la tabla ENTREGA.
     */
    public function entregaExiste(int $idEntrega): bool
    {
        if (!Schema::hasTable('ENTREGA')) {
            return $idEntrega > 0;
        }

        return DB::table('ENTREGA')
            ->where('id_entrega', $idEntrega)
            ->exists();
    }

    /**
     * Verifica si una factura/venta tiene saldo pendiente de pago.
     */
    public function facturaEstaPendiente(int $idFactura): bool
    {
        if (!Schema::hasTable('VENTA')) {
            return true;
        }

        return DB::table('VENTA')
            ->where('id_venta', $idFactura)
            ->where('pagado', false)
            ->exists();
    }
}
