<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lote')) {
            Schema::create('lote', function (Blueprint $t) {
                $t->integer('id_lote', true);
                $t->integer('id_producto')->index();
                $t->string('nro_lote', 100);
                $t->integer('cantidad_inicial')->default(0);
                $t->integer('cantidad_actual')->default(0);
                $t->date('fecha_vencimiento');
                $t->unique(['id_producto', 'nro_lote']);
            });
        }
        if (! Schema::hasColumn('lote', 'estado_trazabilidad')) {
            Schema::table('lote', fn (Blueprint $t) => $t->string('estado_trazabilidad', 30)->default('pendiente_revision')->index());
        }
        if (! Schema::hasTable('stock_lote_movimientos')) {
            $lotType = Schema::getColumnType('lote', 'id_lote', true);
            Schema::create('stock_lote_movimientos', function (Blueprint $t) use ($lotType) {
                $t->bigIncrements('id');
                if (str_contains($lotType, 'bigint')) {
                    $t->bigInteger('id_lote', false, str_contains($lotType, 'unsigned'))->index();
                } else {
                    $t->integer('id_lote', false, str_contains($lotType, 'unsigned'))->index();
                }
                $t->integer('id_movimiento')->nullable()->index();
                $t->integer('id_producto')->index();
                $t->integer('id_venta')->nullable()->index();
                $t->integer('delta');
                $t->unsignedInteger('usuario_id');
                $t->string('motivo', 255);
                $t->timestamp('created_at');
                $t->unique(['id_movimiento', 'id_lote']);
                $t->foreign('id_lote')->references('id_lote')->on('lote')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // La trazabilidad puede respaldar ventas reales. Reversión sólo con inventario y respaldo.
    }
};
