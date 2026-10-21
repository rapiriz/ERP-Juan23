<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PERIODO_CONCILIACION', function (Blueprint $table) {
            $table->increments('id_periodo');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('estado', 20); // 'abierto' | 'cerrado'
            $table->dateTime('fecha_apertura');
            $table->dateTime('fecha_cierre')->nullable();
            $table->unsignedInteger('id_usuario')->comment('ID lógico del usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PERIODO_CONCILIACION');
    }
};