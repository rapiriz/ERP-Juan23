<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('USUARIO')) {
            Schema::create('USUARIO', function (Blueprint $table) {
                $table->increments('id_usuario');
                $table->string('nombre', 100);
                $table->string('email', 100)->nullable();
                $table->string('rol', 50)->default('repartidor');
                $table->string('estado', 20)->default('activo');
            });

            DB::table('USUARIO')->insert([
                ['id_usuario' => 1, 'nombre' => 'Repartidor Juan', 'email' => 'juan@ejemplo.com', 'rol' => 'repartidor', 'estado' => 'activo'],
                ['id_usuario' => 2, 'nombre' => 'Admin Pedro', 'email' => 'pedro@ejemplo.com', 'rol' => 'admin', 'estado' => 'activo'],
                ['id_usuario' => 3, 'nombre' => 'Validadora María', 'email' => 'maria@ejemplo.com', 'rol' => 'admin', 'estado' => 'activo'],
            ]);
        }

        if (!Schema::hasTable('CLIENTE')) {
            Schema::create('CLIENTE', function (Blueprint $table) {
                $table->increments('id_cliente');
                $table->string('nombre', 100);
                $table->string('estado', 20)->default('activo');
            });

            DB::table('CLIENTE')->insert([
                ['id_cliente' => 1, 'nombre' => 'Supermercado Central', 'estado' => 'activo'],
                ['id_cliente' => 10, 'nombre' => 'Comercio El Amigo', 'estado' => 'activo'],
            ]);
        }

        if (!Schema::hasTable('VENTA')) {
            Schema::create('VENTA', function (Blueprint $table) {
                $table->increments('id_venta');
                $table->unsignedInteger('id_cliente');
                $table->dateTime('fecha')->useCurrent();
                $table->decimal('monto_total', 10, 2)->default(0);
                $table->boolean('pagado')->default(false);
            });

            DB::table('VENTA')->insert([
                ['id_venta' => 101, 'id_cliente' => 1, 'fecha' => date('Y-m-d H:i:s'), 'monto_total' => 15000.50, 'pagado' => false],
                ['id_venta' => 201, 'id_cliente' => 10, 'fecha' => date('Y-m-d H:i:s'), 'monto_total' => 25000.00, 'pagado' => false],
            ]);
        }

        if (!Schema::hasTable('ENTREGA')) {
            Schema::create('ENTREGA', function (Blueprint $table) {
                $table->increments('id_entrega');
                $table->unsignedInteger('id_repartidor')->nullable();
                $table->string('estado', 30)->default('en_camino');
            });

            DB::table('ENTREGA')->insert([
                ['id_entrega' => 1, 'id_repartidor' => 1, 'estado' => 'en_camino'],
                ['id_entrega' => 10, 'id_repartidor' => 1, 'estado' => 'en_camino'],
                ['id_entrega' => 15, 'id_repartidor' => 1, 'estado' => 'en_camino'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ENTREGA');
        Schema::dropIfExists('VENTA');
        Schema::dropIfExists('CLIENTE');
        Schema::dropIfExists('USUARIO');
    }
};
