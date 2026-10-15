<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta', function (Blueprint $table): void {
            $table->bigIncrements('id_venta');
            $table->unsignedInteger('id_cliente');
            $table->date('fecha');
            $table->decimal('total', 12, 2);
            $table->decimal('descuento_global', 12, 2)->default(0);
            $table->string('numFactura', 50)->unique();
            $table->string('estado', 30);
            $table->string('observaciones', 150);
            $table->unsignedInteger('id_usuario');
        });

        Schema::create('detalle_venta', function (Blueprint $table): void {
            $table->bigIncrements('id_detalle');
            $table->unsignedBigInteger('id_venta');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_promocion')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2);
            $table->decimal('subtotal', 12, 2);

            $table->foreign('id_venta')->references('id_venta')->on('venta')->cascadeOnDelete();
        });

        Schema::create('promocion', function (Blueprint $table): void {
            $table->bigIncrements('id_promocion');
            $table->string('nombre');
            $table->text('descripcion');
            $table->decimal('total', 12, 2);
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta');
            $table->boolean('estado')->default(true);
        });

        Schema::create('promocion_producto', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_promocion');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('cantidad');

            $table->primary(['id_promocion', 'id_producto']);
            $table->foreign('id_promocion')->references('id_promocion')->on('promocion')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion_producto');
        Schema::dropIfExists('promocion');
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('venta');
    }
};
