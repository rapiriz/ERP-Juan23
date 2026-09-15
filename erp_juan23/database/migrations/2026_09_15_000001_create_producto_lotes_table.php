<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->string('numero_lote')->unique();
            $table->date('fecha_vencimiento');
            $table->integer('cantidad_inicial');
            $table->integer('cantidad_actual');
            $table->string('ubicacion_almacen')->nullable();
            $table->enum('estado', ['activo', 'vencido', 'proximos_a_vencer'])->default('activo');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('fecha_vencimiento');
            $table->index('producto_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_lotes');
    }
};
