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
            $table->string('tipo_descuento', 20)->default('monto_fijo')->after('total');
            $table->decimal('valor_descuento', 12, 2)->default(0)->after('tipo_descuento');
        });

        $productos = new MockProductoApi;

        foreach (DB::table('promocion')->get() as $promocion) {
            $valor = abs((float) $promocion->total);
            $esPorcentaje = (float) $promocion->total < 0;
            $total = $valor;

            if ($esPorcentaje) {
                $subtotal = 0.0;
                $productosPromo = DB::table('promocion_producto')
                    ->where('id_promocion', $promocion->id_promocion)
                    ->get(['id_producto', 'cantidad']);

                foreach ($productosPromo as $productoPromo) {
                    $producto = $productos->buscar((int) $productoPromo->id_producto);
                    if ($producto !== null) {
                        $subtotal += (float) $producto['precioMin'] * (int) $productoPromo->cantidad;
                    }
                }

                $total = round($subtotal * $valor / 100, 2);
            }

            DB::table('promocion')
                ->where('id_promocion', $promocion->id_promocion)
                ->update([
                    'total' => $total,
                    'tipo_descuento' => $esPorcentaje ? 'porcentaje' : 'monto_fijo',
                    'valor_descuento' => $valor,
                ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('promocion')->get(['id_promocion', 'tipo_descuento', 'valor_descuento']) as $promocion) {
            DB::table('promocion')
                ->where('id_promocion', $promocion->id_promocion)
                ->update([
                    'total' => $promocion->tipo_descuento === 'porcentaje'
                        ? -(float) $promocion->valor_descuento
                        : (float) $promocion->valor_descuento,
                ]);
        }

        Schema::table('promocion', function (Blueprint $table): void {
            $table->dropColumn(['tipo_descuento', 'valor_descuento']);
        });
    }
};
