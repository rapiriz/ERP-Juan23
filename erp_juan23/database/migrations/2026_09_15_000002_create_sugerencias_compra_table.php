<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugerencias_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('cascade');
            $table->integer('cantidad_sugerida');
            $table->integer('cantidad_minima');
            $table->integer('cantidad_maxima');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('costo_total', 10, 2);
            $table->decimal('velocidad_venta_diaria', 8, 2);
            $table->integer('plazo_entrega_dias');
            $table->date('fecha_reorden');
            $table->enum('estado', ['pendiente', 'procesada', 'rechazada'])->default('pendiente');
            $table->enum('motivo_generacion', ['bajo_stock', 'proximo_vencer', 'agotamiento_inmediato']);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('fecha_reorden');
            $table->index('producto_id');
            $table->index('proveedor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugerencias_compra');
    }
};
