<?php
declare(strict_types=1);

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\MovimientoStock;
use App\Modules\Stock\Models\Lote;
use App\Modules\Stock\Models\UnidadMedida;
use App\Modules\Productos\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio central de lógica de stock (S01 a S08).
 *
 * En el esquema fusionado el stock disponible vive embebido en PRODUCTO.stock
 * (OB3), de modo que las operaciones actualizan esa columna y registran cada
 * cambio en MOVIMIENTO_STOCK dentro de una transacción ACID. El estado de
 * alerta (S07) es un atributo derivado del producto (no se persiste).
 */
class StockService
{
    /**
     * Tipos de movimiento permitidos (coinciden con el CHECK de la BD).
     */
    public const TIPOS = ['ingreso', 'venta', 'devolucion', 'ajuste'];

    /**
     * Recalcula el estado de alerta derivado (S07).
     *
     * Reglas:
     *  - crítico: stock_disponible <= 0
     *  - bajo:    stock_disponible <= stock_minimo (y > 0)
     *  - normal:  stock_disponible > stock_minimo
     *
     * No persiste nada: el estado es accesible vía Producto::estado_alerta.
     */
    public function recalcularAlerta(Producto $producto): Producto
    {
        return $producto;
    }

    /**
     * Aplica un ajuste con signo a la cantidad disponible y registra el movimiento.
     *
     * @param Producto $producto  Producto afectado (debe estar persistido).
     * @param string   $tipo      'ingreso' | 'venta' | 'devolucion' | 'ajuste'
     * @param int      $cantidad  Cantidad positiva. El signo se deduce del tipo.
     * @param string   $motivo    Motivo u observación.
     * @param int      $idUsuario Usuario responsable del movimiento.
     * @param int|null $idUnidad  Unidad de medida usada (opcional, S08).
     * @param int|null $idVenta   Referencia a venta (opcional, grupo externo).
     */
    public function registrarMovimiento(
        Producto $producto,
        string $tipo,
        int $cantidad,
        string $motivo,
        int $idUsuario,
        ?int $idUnidad = null,
        ?int $idVenta = null,
        ?int $idRecepcion = null,
        ?int $idLote = null,
        ?int $signoAjuste = null
    ): MovimientoStock {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new RuntimeException("Tipo de movimiento inválido: {$tipo}");
        }
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }
        if ($tipo === 'ajuste' && trim($motivo) === '') {
            throw new RuntimeException('Debe indicar un motivo para el ajuste.');
        }

        $fecha = Carbon::now()->toDateString();

        // Transacción atómica: actualizar stock embebido + registrar movimiento
        DB::beginTransaction();

        try {
            $disponible = $producto->stock_disponible;

            // PRODUCTO.stock se mantiene en unidades base (OB3), por lo que el delta
            // se aplica sobre la cantidad convertida y no sobre la cantidad cruda:
            // vender 3 cajas de 12 unidades resta 36, no 3.
            $cantidadBase = self::cantidadEnUnidadBase($cantidad, $idUnidad);

            // Determinar delta aplicado al stock
            $delta = match ($tipo) {
                'ingreso', 'devolucion' => $cantidadBase,
                'venta' => -$cantidadBase,
                'ajuste' => ($signoAjuste === 1 ? 1 : -1) * $cantidadBase,
            };

            $nuevoDisponible = $disponible + $delta;

            // S04: impedir stock negativo en ventas. En ajustes negativos también se impide.
            if ($nuevoDisponible < 0) {
                DB::rollBack();
                throw new RuntimeException(
                    "Stock insuficiente para {$producto->codigo}. Disponible: {$disponible}, solicitado: {$cantidadBase}."
                );
            }

            $producto->stock = $nuevoDisponible;
            $producto->save();
            $this->recalcularAlerta($producto);

            // El saldo por lote alimenta las alertas S10. Para salidas se aplica
            // FEFO (primero vence, primero sale), dentro de la misma transacción
            // que actualiza PRODUCTO.stock. Las cantidades no trazadas a lotes
            // anteriores se descuentan del stock general sin inventar un lote.
            $loteMovimientoId = null;
            if ($tipo === 'venta' || ($tipo === 'ajuste' && $signoAjuste !== 1)) {
                $restante = $cantidadBase;
                $lotesAfectados = 0;
                $primerLoteAfectado = null;
                $lotes = Lote::query()
                    ->where('id_producto', $producto->id_producto)
                    ->where('cantidad_actual', '>', 0)
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('id_lote')
                    ->lockForUpdate()
                    ->get();

                foreach ($lotes as $lote) {
                    if ($restante <= 0) {
                        break;
                    }

                    $consumida = min($restante, (int) $lote->cantidad_actual);
                    $lote->cantidad_actual = (int) $lote->cantidad_actual - $consumida;
                    $lote->save();
                    $restante -= $consumida;
                    $lotesAfectados++;
                    $primerLoteAfectado ??= $lote->id_lote;
                }

                // La FK singular se informa sólo cuando la salida consumió un
                // único lote; en salidas repartidas, todos los saldos sí quedan
                // actualizados aunque el kardex no represente el reparto.
                if ($lotesAfectados === 1) {
                    $loteMovimientoId = $primerLoteAfectado;
                }
            } elseif ($tipo === 'devolucion' && $idLote !== null) {
                $lote = Lote::query()
                    ->where('id_producto', $producto->id_producto)
                    ->lockForUpdate()
                    ->find($idLote);

                if (!$lote) {
                    throw new RuntimeException('El lote indicado no pertenece al producto devuelto.');
                }

                $lote->cantidad_actual = (int) $lote->cantidad_actual + $cantidadBase;
                $lote->save();
                $loteMovimientoId = $lote->id_lote;
            }

            $movimiento = MovimientoStock::create([
                'id_producto' => $producto->id_producto,
                'id_unidad' => $idUnidad,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'cantidad_base' => $cantidadBase,
                'fecha' => $fecha,
                'motivo' => $motivo,
                'id_usuario' => $idUsuario,
                'id_venta' => $idVenta,
                'id_recepcion' => $idRecepcion,
                'id_lote' => $loteMovimientoId,
            ]);

            DB::commit();

            return $movimiento;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Expresa una cantidad en la unidad base del producto.
     *
     * MOVIMIENTO_STOCK guarda la cantidad tal como la indica el usuario y, en
     * cantidad_base, la misma cantidad normalizada a la unidad base: sin eso la
     * traza de auditoría queda inutilizable para razonar sobre stock.
     */
    public static function cantidadEnUnidadBase(int $cantidad, ?int $idUnidad): int
    {
        if ($idUnidad === null) {
            return $cantidad;
        }

        $equivalencia = UnidadMedida::where('id_unidad', $idUnidad)->value('equivalencia_base');

        return (int) round($cantidad * ($equivalencia !== null ? (float) $equivalencia : 1.0));
    }

    /**
     * Consulta el stock disponible de un producto (S01 / S02).
     */
    public function stockDisponibleDe(Producto $producto): int
    {
        return $producto->stock_disponible;
    }

    /**
     * Convierte una cantidad entre unidades según sus equivalencias base (S08).
     *
     * $equivalencia_base expresa cuántas unidades de "unidad base" hay en una unidad.
     * Conversión: cantidadDestino = cantidadOrigen * (equivalenciaOrigen / equivalenciaDestino)
     */
    public static function convertir(float|int $cantidad, float|int $equivalenciaOrigen, float|int $equivalenciaDestino): float
    {
        if ($equivalenciaDestino <= 0) {
            throw new RuntimeException('La unidad de destino debe tener una equivalencia mayor a cero.');
        }
        return (float) $cantidad * $equivalenciaOrigen / $equivalenciaDestino;
    }
}
