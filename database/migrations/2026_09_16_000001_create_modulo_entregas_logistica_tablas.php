<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ZONAS
        if (!Schema::hasTable('zona')) {
            Schema::create('zona', function (Blueprint $table) {
                $table->increments('id_zona');
                $table->string('nombre', 100);
                $table->string('descripcion', 255)->nullable();
            });
        }

        // Extender USUARIO con nombre de usuario de login
        Schema::table('USUARIO', function (Blueprint $table) {
            if (!Schema::hasColumn('USUARIO', 'user')) {
                $table->string('user', 50)->nullable()->after('nombre');
            }
        });

        // 2. Extender CLIENTE con columnas de logística/zona
        Schema::table('CLIENTE', function (Blueprint $table) {
            if (!Schema::hasColumn('CLIENTE', 'id_zona')) {
                $table->unsignedInteger('id_zona')->default(1)->after('nombre');
            }
            if (!Schema::hasColumn('CLIENTE', 'razon_social')) {
                $table->string('razon_social', 150)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('CLIENTE', 'cuit')) {
                $table->string('cuit', 20)->nullable()->after('razon_social');
            }
            if (!Schema::hasColumn('CLIENTE', 'direccion')) {
                $table->string('direccion', 255)->default('Domicilio Comercial')->after('cuit');
            }
            if (!Schema::hasColumn('CLIENTE', 'localidad')) {
                $table->string('localidad', 100)->default('Pigüé')->after('direccion');
            }
            if (!Schema::hasColumn('CLIENTE', 'telefono')) {
                $table->string('telefono', 50)->nullable()->after('localidad');
            }
        });

        // 3. PRODUCTOS
        if (!Schema::hasTable('producto')) {
            Schema::create('producto', function (Blueprint $table) {
                $table->increments('id_producto');
                $table->string('codigo', 50)->unique();
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->integer('bultos_por_unidad')->default(1);
                $table->integer('stock')->default(0);
                $table->string('estado', 20)->default('activo');
            });
        }

        // 4. Extender VENTA con datos de logística
        Schema::table('VENTA', function (Blueprint $table) {
            if (!Schema::hasColumn('VENTA', 'total')) {
                $table->decimal('total', 12, 2)->default(0)->after('monto_total');
            }
            if (!Schema::hasColumn('VENTA', 'total_bultos')) {
                $table->integer('total_bultos')->default(0)->after('total');
            }
            if (!Schema::hasColumn('VENTA', 'total_sueltos')) {
                $table->integer('total_sueltos')->default(0)->after('total_bultos');
            }
            if (!Schema::hasColumn('VENTA', 'estado')) {
                $table->string('estado', 30)->default('confirmada')->after('total_sueltos');
            }
            if (!Schema::hasColumn('VENTA', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('VENTA', 'id_usuario')) {
                $table->unsignedInteger('id_usuario')->default(1)->after('id_cliente');
            }
        });

        // 5. DETALLE_VENTA
        if (!Schema::hasTable('detalle_venta')) {
            Schema::create('detalle_venta', function (Blueprint $table) {
                $table->increments('id_detalle_venta');
                $table->unsignedInteger('id_venta');
                $table->unsignedInteger('id_producto');
                $table->integer('cantidad');
                $table->decimal('precio_unitario', 12, 2);
                $table->decimal('subtotal', 12, 2);
                $table->integer('bultos')->default(1);
                $table->integer('unidades_sueltas')->default(0);
            });
        }

        // 6. Extender ENTREGA con columnas de logística
        Schema::table('ENTREGA', function (Blueprint $table) {
            if (!Schema::hasColumn('ENTREGA', 'fecha_creacion')) {
                $table->dateTime('fecha_creacion')->useCurrent()->after('estado');
            }
            if (!Schema::hasColumn('ENTREGA', 'fecha_salida')) {
                $table->date('fecha_salida')->nullable()->after('fecha_creacion');
            }
            if (!Schema::hasColumn('ENTREGA', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('fecha_salida');
            }
            if (!Schema::hasColumn('ENTREGA', 'id_usuario_creacion')) {
                $table->unsignedInteger('id_usuario_creacion')->default(1)->after('observaciones');
            }
        });

        // 7. ENTREGA_PEDIDO
        if (!Schema::hasTable('entrega_pedido')) {
            Schema::create('entrega_pedido', function (Blueprint $table) {
                $table->increments('id_entrega_pedido');
                $table->unsignedInteger('id_entrega');
                $table->unsignedInteger('id_venta');
                $table->string('estado_pedido_entrega', 30)->default('en_preparacion');
                $table->text('motivo_rechazo')->nullable();
                $table->dateTime('fecha_actualizacion')->useCurrent();
            });
        }

        // 8. REMITO
        if (!Schema::hasTable('remito')) {
            Schema::create('remito', function (Blueprint $table) {
                $table->increments('id_remito');
                $table->string('numero_remito', 30)->unique();
                $table->unsignedInteger('id_entrega');
                $table->unsignedInteger('id_venta');
                $table->dateTime('fecha_emision')->useCurrent();
                $table->string('estado', 30)->default('emitido');
                $table->text('observaciones')->nullable();
            });
        }

        // 9. DETALLE_REMITO
        if (!Schema::hasTable('detalle_remito')) {
            Schema::create('detalle_remito', function (Blueprint $table) {
                $table->increments('id_detalle_remito');
                $table->unsignedInteger('id_remito');
                $table->unsignedInteger('id_producto')->nullable();
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
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('producto');
        Schema::dropIfExists('zona');
    }
};
