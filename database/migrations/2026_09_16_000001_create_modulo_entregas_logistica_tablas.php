<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ZONAS (Grupo 2)
        if (!Schema::hasTable('zona')) {
            Schema::create('zona', function (Blueprint $table) {
                $table->increments('id_zona');
                $table->string('nombre', 100);
                $table->string('descripcion', 255)->nullable();
            });
        }

        // 2. Asegurar columnas de logística en ENTREGA (Grupo 2)
        $tablaEntrega = Schema::hasTable('entrega') ? 'entrega' : (Schema::hasTable('ENTREGA') ? 'ENTREGA' : null);
        if ($tablaEntrega) {
            Schema::table($tablaEntrega, function (Blueprint $table) use ($tablaEntrega) {
                if (!Schema::hasColumn($tablaEntrega, 'fecha_creacion')) {
                    $table->dateTime('fecha_creacion')->useCurrent()->after('estado');
                }
                if (!Schema::hasColumn($tablaEntrega, 'fecha_salida')) {
                    $table->date('fecha_salida')->nullable()->after('fecha_creacion');
                }
                if (!Schema::hasColumn($tablaEntrega, 'observaciones')) {
                    $table->text('observaciones')->nullable()->after('fecha_salida');
                }
                if (!Schema::hasColumn($tablaEntrega, 'id_usuario_creacion')) {
                    $table->unsignedInteger('id_usuario_creacion')->default(1)->after('observaciones');
                }
            });
        }

        // 3. ENTREGA_PEDIDO (Grupo 2)
        if (!Schema::hasTable('entrega_pedido')) {
            Schema::create('entrega_pedido', function (Blueprint $table) {
                $table->increments('id_entrega_pedido');
                $table->unsignedInteger('id_entrega');
                $table->unsignedInteger('id_venta')->comment('ID lógico de venta provista por API G4');
                $table->string('estado_pedido_entrega', 30)->default('en_preparacion');
                $table->text('motivo_rechazo')->nullable();
                $table->dateTime('fecha_actualizacion')->useCurrent();
            });
        }

        // 4. REMITO (Grupo 2)
        if (!Schema::hasTable('remito')) {
            Schema::create('remito', function (Blueprint $table) {
                $table->increments('id_remito');
                $table->string('numero_remito', 30)->unique();
                $table->unsignedInteger('id_entrega');
                $table->unsignedInteger('id_venta')->comment('ID lógico de venta provista por API G4');
                $table->dateTime('fecha_emision')->useCurrent();
                $table->string('estado', 30)->default('emitido');
                $table->text('observaciones')->nullable();
            });
        }

        // 5. DETALLE_REMITO (Grupo 2)
        if (!Schema::hasTable('detalle_remito')) {
            Schema::create('detalle_remito', function (Blueprint $table) {
                $table->increments('id_detalle_remito');
                $table->unsignedInteger('id_remito');
                $table->unsignedInteger('id_producto')->nullable()->comment('ID lógico de producto provisto por API G3');
                $table->string('descripcion', 255);
                $table->integer('cantidad');
                $table->integer('bultos')->default(1);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_remito');
        Schema::dropIfExists('remito');
        Schema::dropIfExists('entrega_pedido');
        Schema::dropIfExists('zona');
    }
};
