<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CONCILIACION_DETALLE', function (Blueprint $table) {
            $table->increments('id_detalle');
            $table->unsignedInteger('id_movimiento_bancario');
            $table->unsignedInteger('id_cobro')->nullable();
            $table->unsignedInteger('id_movimiento_caja')->nullable();
            $table->unsignedInteger('id_ajuste')->nullable();
            $table->unsignedInteger('id_cheque')->nullable();
            $table->dateTime('fecha_conciliacion');
            $table->string('metodo', 20); // 'automatico' | 'manual'
            $table->unsignedInteger('id_usuario');

            $table->foreign('id_movimiento_bancario')->references('id_movimiento_bancario')->on('MOVIMIENTO_BANCARIO');
            $table->foreign('id_cobro')->references('id_cobro')->on('COBRO');
            $table->foreign('id_movimiento_caja')->references('id_movimiento_caja')->on('CAJA_MOVIMIENTO');
            $table->foreign('id_ajuste')->references('id_ajuste')->on('AJUSTE_BANCARIO');
            $table->foreign('id_cheque')->references('id_cheque')->on('CHEQUE');
            $table->foreign('id_usuario')->references('id_usuario')->on('USUARIO');
        });

        // Regla: exactamente una de las 4 FK opcionales debe estar completa por fila.
        // Ya probado contra MySQL/MariaDB real antes de escribir esto.
        DB::statement('
            ALTER TABLE CONCILIACION_DETALLE
            ADD CONSTRAINT chk_detalle_origen_unico CHECK (
                (id_cobro IS NOT NULL) + (id_movimiento_caja IS NOT NULL) +
                (id_ajuste IS NOT NULL) + (id_cheque IS NOT NULL) = 1
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('CONCILIACION_DETALLE');
    }
};