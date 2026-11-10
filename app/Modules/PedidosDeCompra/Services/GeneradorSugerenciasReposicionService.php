<?php

declare(strict_types=1);

namespace App\Modules\PedidosDeCompra\Services;

use App\Modules\PedidosDeCompra\Models\DetalleOrden;
use App\Modules\PedidosDeCompra\Models\OrdenCompra;
use App\Modules\PedidosDeCompra\Models\SugerenciaCompra;
use App\Modules\PedidosDeCompra\Repositories\SugerenciaCompraRepository;
use App\Modules\Productos\Models\Producto;
use App\Modules\Proveedores\Models\ProductoProveedor;
use App\Modules\Stock\Models\MovimientoStock;
use App\Modules\Stock\Services\ProductUnitService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * PC07 - Sugerencias de reposición.
 *
 * Fuentes de datos en el esquema unificado (no existen STOCK ni VENTA aquí):
 *  - stock actual / mínimo : PRODUCTO.stock, PRODUCTO.stock_minimo
 *  - stock máximo          : no existe -> config('reposicion.stock_maximo_default')
 *  - velocidad de venta    : MOVIMIENTO_STOCK (tipo 'venta') de los últimos N días
 *  - proveedor y precio    : PRODUCTO_PROVEEDOR (activo, menor precio_acordado)
 *  - plazo de entrega      : PROVEEDOR.plazo_entrega_dias
 */
class GeneradorSugerenciasReposicionService
{
    public function __construct(
        private SugerenciaCompraRepository $sugerencias
    ) {}

    public function generarSugerencias(): array
    {
        $generadas = [];
        $errores = [];

        foreach (Producto::where('estado', 'activo')->get() as $producto) {
            try {
                foreach ($this->analizarProducto($producto) as $datos) {
                    // Idempotencia: no volver a crear lo que ya está pendiente.
                    if ($this->sugerencias->existePendiente($producto->id_producto, $datos['motivo_generacion'])) {
                        continue;
                    }
                    $generadas[] = $this->sugerencias->crear($datos);
                }
            } catch (Throwable $e) {
                $errores[] = "Producto {$producto->id_producto}: {$e->getMessage()}";
            }
        }

        return [
            'exito' => true,
            'total_generadas' => count($generadas),
            'sugerencias' => collect($generadas)->map(fn (SugerenciaCompra $s) => $s->load(['producto', 'proveedor']))
                ->map(fn (SugerenciaCompra $s) => self::formatear($s))->all(),
            'errores' => $errores,
            'fecha_generacion' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<int, array<string, mixed>> filas listas para SUGERENCIA_COMPRA */
    private function analizarProducto(Producto $producto): array
    {
        $stock = (int) $producto->stock_disponible;
        $minimo = (int) $producto->stock_minimo;
        $maximo = max($minimo, 1);
        $velocidad = $this->calcularVelocidadVenta($producto);

        // Cada motivo -> [cantidad, observación]
        $motivos = [];

        if ($stock <= $minimo) {
            $motivos['bajo_stock'] = [null, 'Stock por debajo del mínimo permitido'];
        }

        $dias = $this->calcularDiasHastaAgotamiento($stock, $velocidad);
        if ($dias > 0 && $dias <= (int) config('reposicion.dias_agotamiento_alerta', 7)) {
            $motivos['agotamiento_inmediato'] = [null, "Agotamiento en {$dias} días al ritmo de venta actual"];
        }

        if ($motivos === []) {
            return [];
        }

        $asociacion = $this->obtenerMejorProveedor($producto);
        if (!$asociacion) {
            throw new RuntimeException('No hay proveedores activos registrados para el producto');
        }

        $plazo = (int) ($asociacion->proveedor->plazo_entrega_dias ?? config('reposicion.plazo_entrega_default', 5));
        $precio = (float) ($asociacion->precio_acordado ?? 0);

        $filas = [];
        foreach ($motivos as $motivo => [$cantidadFija, $observacion]) {
            $cantidad = $cantidadFija ?? $this->calcularCantidadOptima($stock, $minimo);

            $filas[] = [
                'id_producto' => $producto->id_producto,
                'id_proveedor' => $asociacion->id_proveedor,
                'cantidad_sugerida' => $cantidad,
                'cantidad_minima' => $minimo,
                'cantidad_maxima' => $maximo,
                'precio_unitario' => $precio,
                'costo_total' => round($cantidad * $precio, 2),
                'velocidad_venta_diaria' => $velocidad,
                'plazo_entrega_dias' => $plazo,
                'fecha_reorden' => Carbon::today()->addDays($plazo)->toDateString(),
                'estado' => 'pendiente',
                'motivo_generacion' => $motivo,
                'observaciones' => $observacion,
            ];
        }

        return $filas;
    }

    private function calcularCantidadOptima(int $stock, int $minimo): int
    {
        return max(1, $minimo - $stock);
    }

    private function calcularVelocidadVenta(Producto $producto): float
    {
        $ventana = (int) config('reposicion.ventana_ventas_dias', 30);

        // La velocidad se mide en unidades base: PRODUCTO.stock y las cantidades
        // sugeridas están en base, así que usar 'cantidad' subestimaría la venta
        // cuando el movimiento se registró en una unidad no base (p.ej. cajas).
        $vendidas = (int) MovimientoStock::where('id_producto', $producto->id_producto)
            ->where('tipo', 'venta')
            ->where('fecha', '>=', Carbon::today()->subDays($ventana)->toDateString())
            ->sum('cantidad_base');

        return round($vendidas / max($ventana, 1), 2);
    }

    private function calcularDiasHastaAgotamiento(int $stock, float $velocidadDiaria): int
    {
        if ($velocidadDiaria <= 0) {
            return 365;
        }

        return (int) ($stock / $velocidadDiaria);
    }

    /** Asociación activa más barata (sin precio al final; desempata el proveedor principal). */
    private function obtenerMejorProveedor(Producto $producto): ?ProductoProveedor
    {
        return ProductoProveedor::query()
            ->with('proveedor')
            ->where('id_producto', $producto->id_producto)
            ->where('activo', true)
            ->whereHas('proveedor', fn ($q) => $q->where('estado', 'activo'))
            ->orderByRaw('precio_acordado IS NULL')
            ->orderBy('precio_acordado')
            ->orderByDesc('es_proveedor_principal')
            ->first();
    }

    public function procesarSugerencia(int $id, int $idUsuario): array
    {
        $this->resolver($id, 'procesada', $idUsuario);

        return ['exito' => true, 'mensaje' => 'Sugerencia procesada correctamente', 'sugerencia_id' => $id];
    }

    public function rechazarSugerencia(int $id, int $idUsuario, ?string $motivo = null): array
    {
        $this->resolver($id, 'rechazada', $idUsuario, $motivo);

        return ['exito' => true, 'mensaje' => 'Sugerencia rechazada correctamente', 'sugerencia_id' => $id];
    }

    private function resolver(int $id, string $estado, int $idUsuario, ?string $motivo = null): void
    {
        $sugerencia = $this->sugerencias->obtenerPorId($id);
        if (! $sugerencia) {
            throw new RuntimeException('Sugerencia no encontrada');
        }
        if ($sugerencia->estado !== 'pendiente') {
            throw new RuntimeException("La sugerencia ya fue {$sugerencia->estado}.");
        }
        $this->sugerencias->resolver($id, $estado, $idUsuario, $motivo);
    }

    public function procesarMultiples(array $ids, int $idUsuario): array
    {
        if ($ids === []) {
            throw new RuntimeException('No se recibieron sugerencias para procesar');
        }

        $r = $this->sugerencias->procesarMultiples($ids, $idUsuario);

        return ['exito' => true, 'total_procesadas' => $r['procesadas'], 'fallidas' => $r['fallidas']];
    }

    /**
     * PC07 - Generar una orden de compra a partir de la sugerencia y asociarla
     * al proveedor correspondiente.
     *
     * El proveedor NO se elige en la UI: es el que el generador ya asignado a la
     * sugerencia (el activo de mejor precio, ver obtenerMejorProveedor), que es el
     * criterio de la H.U. "asociar la orden al proveedor correspondiente".
     *
     * Se reutiliza el contrato de PC01 (ORDEN_COMPRA + DETALLE_ORDEN) en vez de
     * inventar uno nuevo: la orden resultante es indistinguible de una creada a
     * mano y entra por los mismos listados y validaciones de compras.
     *
     * Todo en una transacción: si falla el marcado de la sugerencia no debe quedar
     * una orden huérfana.
     */
    public function generarOrdenDesdeSugerencia(int $id, int $idUsuario, bool $agruparProveedor = false, ?int $cantidadOverride = null): array
    {
        if ($cantidadOverride !== null && $cantidadOverride <= 0) throw new RuntimeException('La cantidad debe ser positiva.');
        $supplierId = SugerenciaCompra::whereKey($id)->value('id_proveedor');
        if (! $supplierId) throw new RuntimeException('Sugerencia no encontrada.');
        return DB::transaction(function () use ($id, $idUsuario, $agruparProveedor, $cantidadOverride, $supplierId) {
            // Un proveedor serializa la búsqueda/creación de OC agrupadas, incluso sin OC previa.
            $supplier = \App\Modules\Proveedores\Models\Proveedor::whereKey($supplierId)->lockForUpdate()->firstOrFail();
            if ($supplier->estado !== 'activo') throw new RuntimeException('El proveedor está inactivo.');
            $query = SugerenciaCompra::where('id_proveedor', $supplierId)->where('estado', 'pendiente')->whereNull('id_orden_compra');
            if (! $agruparProveedor) $query->whereKey($id);
            $suggestions = $query->orderBy('id_sugerencia')->lockForUpdate()->get();
            $selected = $suggestions->firstWhere('id_sugerencia', $id);
            if (! $selected) throw new RuntimeException('La sugerencia ya fue procesada o generó una orden.');
            $order = $agruparProveedor ? OrdenCompra::where('id_proveedor', $supplierId)->where('estado', 'pendiente')
                ->whereDate('fecha_creacion', today())->doesntHave('compras')
                ->whereDoesntHave('detalles', fn ($q) => $q->where('origen', '!=', 'sugerencia'))
                ->orderBy('id_orden')->lockForUpdate()->first() : null;
            $products = Producto::whereIn('id_producto', $suggestions->pluck('id_producto'))->orderBy('id_producto')->lockForUpdate()->get()->keyBy('id_producto');
            if (! $order) $order = OrdenCompra::create(['numero_orden' => $this->generarNumero(), 'id_proveedor' => $supplierId,
                'estado' => 'pendiente', 'fecha_creacion' => today(), 'fecha_entrega_estimada' => $supplier->plazo_entrega_dias ? today()->addDays((int) $supplier->plazo_entrega_dias) : null,
                'total_estimado' => 0, 'id_usuario' => $idUsuario]);
            foreach ($suggestions->groupBy('id_producto') as $productId => $group) {
                $product = $products->get($productId);
                if (! $product || $product->estado !== 'activo') throw new RuntimeException('Un producto está inactivo o ya no existe.');
                $association = ProductoProveedor::where('id_producto', $productId)->where('id_proveedor', $supplierId)->where('activo', true)->first();
                if (! $association) throw new RuntimeException('El proveedor ya no está asociado al producto.');
                $quantity = (int) $group->max(fn ($item) => (int) $item->id_sugerencia === $id && $cantidadOverride !== null ? $cantidadOverride : (int) $item->cantidad_sugerida);
                if ($quantity <= 0) throw new RuntimeException('La sugerencia contiene una cantidad inválida.');
                $unit = ProductUnitService::resolve((int) $productId);
                $price = (float) ($association->precio_acordado ?? $group->first()->precio_unitario);
                $detail = $order->detalles()->where('id_producto', $productId)->where('id_unidad', $unit->id_unidad)->lockForUpdate()->first();
                $quantity = max($quantity, (int) ($detail?->cantidad_solicitada ?? 0));
                $values = ['cantidad_solicitada' => $quantity, 'cantidad_sugerida' => $quantity, 'precio_estimado' => $price, 'subtotal' => round($quantity * $price, 2)];
                if ($detail) $detail->update($values);
                else DetalleOrden::create(['id_orden' => $order->id_orden, 'id_producto' => $productId, 'id_unidad' => $unit->id_unidad, 'origen' => 'sugerencia', ...$values]);
                foreach ($group as $item) {
                    if (! $this->sugerencias->resolver((int) $item->id_sugerencia, 'procesada', $idUsuario)) throw new RuntimeException('La sugerencia cambió durante la operación.');
                    $item->update(['id_orden_compra' => $order->id_orden]);
                }
            }
            $order->update(['total_estimado' => round((float) $order->detalles()->sum('subtotal'), 2)]);
            return ['exito' => true, 'mensaje' => 'Orden generada sin duplicar necesidades del mismo producto.', 'sugerencia_id' => $id,
                'sugerencias_agrupadas' => $suggestions->count(), 'orden' => ['id' => $order->id_orden, 'numero_orden' => $order->numero_orden,
                    'id_proveedor' => $supplierId, 'proveedor' => $supplier->razon_social, 'producto' => $products->get($selected->id_producto)->nombre,
                    'cantidad' => $order->detalles()->where('id_producto', $selected->id_producto)->sum('cantidad_solicitada'),
                    'precio_unitario' => $selected->precio_unitario, 'total_estimado' => $order->total_estimado, 'fecha_entrega_estimada' => $order->fecha_entrega_estimada]];
        }, 3);
    }

    /** Genera un número único de orden con el formato de PC01: OC-AAAA-nnnnnn. */
    private function generarNumero(): string
    {
        do {
            $numero = 'OC-'.Carbon::now()->format('Y').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (OrdenCompra::where('numero_orden', $numero)->exists());

        return $numero;
    }

    public function obtenerResumen(): array
    {
        $r = $this->sugerencias->obtenerResumen();
        $stockTotal = (int) Producto::where('estado', 'activo')->sum('stock');
        $porcentaje = $stockTotal > 0 ? ($r['cantidad_total'] / $stockTotal) * 100 : 0;

        return [
            'exito' => true,
            'resumen' => [
                'sugerencias_pendientes' => $r['total_pendientes'],
                'cantidad_total' => $r['cantidad_total'],
                'costo_total_sugerido' => '$'.number_format($r['costo_total_sugerido'], 2),
                'porcentaje_reposicion' => number_format($porcentaje, 2).'%',
            ],
        ];
    }

    /**
     * Mapea al contrato que espera public/js/sugerencias-reposicion.js
     * (id, producto.{codigo,nombre}, proveedor.nombre).
     */
    public static function formatear(SugerenciaCompra $s): array
    {
        return [
            'id' => $s->id_sugerencia,
            'id_sugerencia' => $s->id_sugerencia,
            'id_producto' => $s->id_producto,
            'id_proveedor' => $s->id_proveedor,
            'stock_actual' => (int) ($s->producto?->stock_disponible ?? 0),
            'producto' => [
                'id' => $s->id_producto,
                'codigo' => $s->producto?->codigo,
                'nombre' => $s->producto?->nombre ?? $s->producto?->descripcion,
                'stock' => (int) ($s->producto?->stock_disponible ?? 0),
                'stock_minimo' => (int) ($s->producto?->stock_minimo ?? $s->cantidad_minima ?? 0),
            ],
            'proveedor' => [
                'id' => $s->id_proveedor,
                'nombre' => $s->proveedor?->razon_social,
                'razon_social' => $s->proveedor?->razon_social,
            ],
            'cantidad_sugerida' => $s->cantidad_sugerida,
            'cantidad_minima' => $s->cantidad_minima,
            'cantidad_maxima' => $s->cantidad_maxima,
            'precio_unitario' => $s->precio_unitario,
            'costo_total' => $s->costo_total,
            'velocidad_venta_diaria' => $s->velocidad_venta_diaria,
            'plazo_entrega_dias' => $s->plazo_entrega_dias,
            'fecha_reorden' => $s->fecha_reorden?->format('Y-m-d'),
            'estado' => $s->estado,
            // PC07: referencia a la orden generada, si la hubo.
            'id_orden_compra' => $s->id_orden_compra,
            'motivo_generacion' => $s->motivo_generacion,
            'observaciones' => $s->observaciones,
            'motivo_rechazo' => $s->motivo_rechazo,
        ];
    }
}
