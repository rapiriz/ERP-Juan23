<?php

use App\Services\MockProductoApi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocion', function (Blueprint $table): void {
            $table->decimal('valor_descuento', 12, 2)->default(0)->after('tipo_descuento');
        });

        $productos = new MockProductoApi;

        foreach (DB::table('promocion')->get() as $promocion) {
            $subtotal = $this->calcularSubtotal($promocion->id_promocion, $productos);
            $importeDescontado = min(max($subtotal - (float) $promocion->total, 0), $subtotal);
            $valor = $promocion->tipo_descuento === 'porcentaje' && $subtotal > 0
                ? round($importeDescontado / $subtotal * 100, 2)
                : round($importeDescontado, 2);

            DB::table('promocion')
                ->where('id_promocion', $promocion->id_promocion)
                ->update(['valor_descuento' => $valor]);
        }
    }

    public function down(): void
    {
        Schema::table('promocion', function (Blueprint $table): void {
            $table->dropColumn('valor_descuento');
        });
    }

    private function calcularSubtotal(int $idPromocion, MockProductoApi $productos): float
    {
        $subtotal = 0.0;
        $productosPromo = DB::table('promocion_producto')
            ->where('id_promocion', $idPromocion)
            ->get(['id_producto', 'cantidad']);

        foreach ($productosPromo as $productoPromo) {
            $producto = $productos->buscar((int) $productoPromo->id_producto);
            if ($producto !== null) {
                $subtotal += (float) $producto['precioMin'] * (int) $productoPromo->cantidad;
            }
        }

        return $subtotal;
    }
};
