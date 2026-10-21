<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar residuos de tablas que no pertenecen al Grupo 2 si existieran
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('producto');
        Schema::dropIfExists('VENTA');
        Schema::dropIfExists('CLIENTE');
        Schema::dropIfExists('USUARIO');

        // Tabla propia de Grupo 2: ENTREGA (Hoja de ruta / viaje del repartidor)
        if (!Schema::hasTable('entrega') && !Schema::hasTable('ENTREGA')) {
            Schema::create('entrega', function (Blueprint $table) {
                $table->increments('id_entrega');
                $table->unsignedInteger('id_repartidor')->comment('ID lógico del usuario repartidor');
                $table->dateTime('fecha_creacion')->useCurrent();
                $table->date('fecha_salida')->nullable();
                $table->string('estado', 30)->default('en_preparacion');
                $table->text('observaciones')->nullable();
                $table->unsignedInteger('id_usuario_creacion')->default(1)->comment('ID lógico de usuario creador');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega');
        Schema::dropIfExists('ENTREGA');
    }
};
