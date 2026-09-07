<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MOVIMIENTO_BANCARIO', function (Blueprint $table) {
            $table->increments('id_movimiento_bancario');
            $table->unsignedInteger('id_periodo');
            $table->date('fecha_movimiento');
            $table->string('descripcion', 255);
            $table->decimal('monto', 12, 2);
            $table->string('tipo', 20); // 'credito' | 'debito'
            $table->string('estado', 20); // 'pendiente' | 'conciliado' | 'rechazado'
            $table->dateTime('fecha_importacion');
            $table->unsignedInteger('id_usuario');

            $table->foreign('id_periodo')->references('id_periodo')->on('PERIODO_CONCILIACION');
            $table->foreign('id_usuario')->references('id_usuario')->on('USUARIO');

            // Riesgo conocido y aceptado: dos movimientos legítimos idénticos
            // (mismo día/descripción/monto) rebotarían como duplicado.
            $table->unique(['id_periodo', 'fecha_movimiento', 'descripcion', 'monto'], 'uq_movbanco_duplicado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MOVIMIENTO_BANCARIO');
    }
};