<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('movimiento_stock')) {
            Schema::table('movimiento_stock', function (Blueprint $table) {
                if (! Schema::hasColumn('movimiento_stock', 'cantidad_base')) {
                    $table->integer('cantidad_base')->nullable();
                }
                if (! Schema::hasColumn('movimiento_stock', 'id_recepcion')) {
                    $table->integer('id_recepcion')->nullable();
                }
            });

            DB::table('movimiento_stock')
                ->whereNull('cantidad_base')
                ->orderBy('id_movimiento')
                ->chunkById(500, function ($movimientos): void {
                    foreach ($movimientos as $movimiento) {
                        $equivalencia = $movimiento->id_unidad
                            ? DB::table('unidad_medida')->where('id_unidad', $movimiento->id_unidad)->value('equivalencia_base')
                            : null;

                        $actualizar = [
                            'cantidad_base' => (int) round((int) $movimiento->cantidad * ($equivalencia !== null ? (float) $equivalencia : 1)),
                        ];

                        DB::table('movimiento_stock')
                            ->where('id_movimiento', $movimiento->id_movimiento)
                            ->update($actualizar);
                    }
                }, 'id_movimiento');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('movimiento_stock')) {
            $columnasMovimiento = array_values(array_filter(
                ['cantidad_base', 'id_recepcion'],
                fn (string $columna): bool => Schema::hasColumn('movimiento_stock', $columna)
            ));

            if ($columnasMovimiento !== []) {
                Schema::table('movimiento_stock', fn (Blueprint $table) => $table->dropColumn($columnasMovimiento));
            }
        }
    }
};
