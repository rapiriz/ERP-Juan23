<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AJUSTE_BANCARIO', function (Blueprint $table) {
            $table->increments('id_ajuste');
            $table->unsignedInteger('id_movimiento_bancario');
            $table->string('categoria', 30); // 'comision' | 'impuesto_cheque' | 'mantenimiento' | 'otro'
            $table->string('observacion', 255)->nullable();
            $table->decimal('monto', 12, 2);
            $table->dateTime('fecha_registro');
            $table->unsignedInteger('id_usuario');

            $table->foreign('id_movimiento_bancario')->references('id_movimiento_bancario')->on('MOVIMIENTO_BANCARIO');
            $table->foreign('id_usuario')->references('id_usuario')->on('USUARIO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('AJUSTE_BANCARIO');
    }
};