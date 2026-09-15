<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Stock;
use App\Models\Proveedor;
use App\Repositories\SugerenciaCompraRepository;
use App\Repositories\ProductoLoteRepository;

class GeneradorSugerenciasReposicionService
{
    private SugerenciaCompraRepository $sugerenciaRepo;
    private ProductoLoteRepository $loteRepo;

    public function __construct(
        SugerenciaCompraRepository $sugerenciaRepo,
        ProductoLoteRepository $loteRepo
    ) {
        $this->sugerenciaRepo = $sugerenciaRepo;
        $this->loteRepo = $loteRepo;
    }

    public function generarSugerencias(): array
    {
        $productos = Producto::where('activo', true)->get();
        $sugerenciasGeneradas = [];
        $errores = [];

        foreach ($productos as $producto) {
            try {
                $sugerencias = $this->analizarProducto($producto);

                foreach ($sugerencias as $sugerencia) {
                    $sugerenciaGuardada = $this->sugerenciaRepo->crear($sugerencia);
                    $sugerenciasGeneradas[] = $sugerenciaGuardada->toArray();
                }
            } catch (\Exception $e) {
                $errores[] = "Producto {$producto->id}: {$e->getMessage()}";
            }
        }

        return [
            'exito' => true,
            'total_generadas' => count($sugerenciasGeneradas),
            'sugerencias' => $sugerenciasGeneradas,
            'errores' => $errores,
            'fecha_generacion' => now()->format('Y-m-d H:i:s')
        ];
    }

    private function analizarProducto(Producto $producto): array
    {
        $sugerencias = [];

        $stockActual = Stock::where('producto_id', $producto->id)->first();
        if (!$stockActual) {
            return [];
        }

        $cantidad = $stockActual->cantidad;
        $stockMinimo = $stockActual->stock_minimo ?? 10;
        $stockMaximo = $stockActual->stock_maximo ?? 100;

        // 1. Análisis: Stock bajo
        if ($cantidad <= $stockMinimo) {
            $cantidadSugerida = $this->calcularCantidadOptima($producto, $stockMaximo);
            $sugerencias[] = $this->crearSugerencia(
                $producto,
                $cantidadSugerida,
                'bajo_stock',
                'Stock por debajo del mínimo permitido'
            );
        }

        // 2. Análisis: Proximidad a vencimiento
        $lotesProximosVencer = $this->loteRepo->obtenerProximosAVencer(15);
        $lotesProducto = collect($lotesProximosVencer['data'])->where('producto_id', $producto->id);

        if ($lotesProducto->count() > 0 && $lotesProducto->sum('cantidad_actual') > 0) {
            $cantidadEnRiesgo = $lotesProducto->sum('cantidad_actual');
            $sugerencias[] = $this->crearSugerencia(
                $producto,
                $cantidadEnRiesgo,
                'proximo_vencer',
                "Reemplazar {$cantidadEnRiesgo} unidades próximas a vencer"
            );
        }

        // 3. Análisis: Proyección de agotamiento
        $velocidadVenta = $this->calcularVelocidadVenta($producto);
        $diasHastaAgotamiento = $this->calcularDiasHastaAgotamiento($cantidad, $velocidadVenta);

        if ($diasHastaAgotamiento > 0 && $diasHastaAgotamiento <= 7) {
            $cantidadSugerida = $this->calcularCantidadOptima($producto, $stockMaximo);
            $sugerencias[] = $this->crearSugerencia(
                $producto,
                $cantidadSugerida,
                'agotamiento_inmediato',
                "Agotamiento en {$diasHastaAgotamiento} días al ritmo de venta actual"
            );
        }

        return $sugerencias;
    }

    private function crearSugerencia(Producto $producto, int $cantidad, string $motivo, string $observaciones): array
    {
        $proveedor = $this->obtenerMejorProveedor($producto);

        if (!$proveedor) {
            throw new \Exception("No hay proveedores registrados para el producto");
        }

        $precioUnitario = $proveedor->pivot?->precio_venta ?? $producto->precio_costo ?? 0;
        $costoTotal = $cantidad * $precioUnitario;
        $plazoEntrega = $proveedor->pivot?->plazo_entrega_dias ?? 5;

        return [
            'producto_id' => $producto->id,
            'proveedor_id' => $proveedor->id,
            'cantidad_sugerida' => $cantidad,
            'cantidad_minima' => $producto->stock_minimo ?? 10,
            'cantidad_maxima' => $producto->stock_maximo ?? 100,
            'precio_unitario' => $precioUnitario,
            'costo_total' => $costoTotal,
            'velocidad_venta_diaria' => $this->calcularVelocidadVenta($producto),
            'plazo_entrega_dias' => $plazoEntrega,
            'fecha_reorden' => now()->addDays($plazoEntrega),
            'estado' => 'pendiente',
            'motivo_generacion' => $motivo,
            'observaciones' => $observaciones
        ];
    }

    private function calcularCantidadOptima(Producto $producto, int $stockMaximo): int
    {
        $velocidad = $this->calcularVelocidadVenta($producto);
        $cantidadBase = $stockMaximo - ($producto->stock()->first()?->cantidad ?? 0);
        $plazoPromedio = 5;

        $cantidadReposicion = $cantidadBase + (int)($velocidad * $plazoPromedio);

        return max($cantidadReposicion, $stockMaximo * 0.5);
    }

    private function calcularVelocidadVenta(Producto $producto): float
    {
        $ventasUltimos30 = $producto->detalleVentas()
            ->whereHas('venta', function ($query) {
                $query->where('fecha', '>=', now()->subDays(30));
            })
            ->sum('cantidad');

        return round($ventasUltimos30 / 30, 2);
    }

    private function calcularDiasHastaAgotamiento(int $stockActual, float $velocidadDiaria): int
    {
        if ($velocidadDiaria == 0) return 365;
        return (int)($stockActual / $velocidadDiaria);
    }

    private function obtenerMejorProveedor(Producto $producto): ?Proveedor
    {
        return $producto->proveedores()
            ->where('activo', true)
            ->orderBy('pivot_precio_venta', 'asc')
            ->first();
    }

    public function procesarSugerencia(int $idSugerencia): array
    {
        $sugerencia = \App\Models\SugerenciaCompra::find($idSugerencia);

        if (!$sugerencia) {
            throw new \Exception("Sugerencia no encontrada");
        }

        $this->sugerenciaRepo->marcarProcesada($idSugerencia);

        return [
            'exito' => true,
            'mensaje' => 'Sugerencia procesada correctamente',
            'sugerencia_id' => $idSugerencia
        ];
    }

    public function rechazarSugerencia(int $idSugerencia): array
    {
        $sugerencia = \App\Models\SugerenciaCompra::find($idSugerencia);

        if (!$sugerencia) {
            throw new \Exception("Sugerencia no encontrada");
        }

        $this->sugerenciaRepo->marcarRechazada($idSugerencia);

        return [
            'exito' => true,
            'mensaje' => 'Sugerencia rechazada correctamente',
            'sugerencia_id' => $idSugerencia
        ];
    }

    public function procesarMultiples(array $ids): array
    {
        if (empty($ids)) {
            throw new \Exception("No se recibieron sugerencias para procesar");
        }

        $resultado = $this->sugerenciaRepo->procesarMultiples($ids);

        return [
            'exito' => true,
            'total_procesadas' => $resultado['procesadas'],
            'fallidas' => $resultado['fallidas']
        ];
    }

    public function obtenerResumen(): array
    {
        $resumen = $this->sugerenciaRepo->obtenerResumen();

        return [
            'exito' => true,
            'resumen' => [
                'sugerencias_pendientes' => $resumen['total_pendientes'],
                'cantidad_total' => $resumen['cantidad_total'],
                'costo_total_sugerido' => '$' . number_format($resumen['costo_total_sugerido'], 2),
                'porcentaje_reposicion' => $this->calcularPorcentajeReposicion()
            ]
        ];
    }

    private function calcularPorcentajeReposicion(): string
    {
        $resumen = $this->sugerenciaRepo->obtenerResumen();
        $totalStock = Stock::sum('cantidad');

        if ($totalStock == 0) return '0%';

        $porcentaje = ($resumen['cantidad_total'] / $totalStock) * 100;
        return number_format($porcentaje, 2) . '%';
    }
}
