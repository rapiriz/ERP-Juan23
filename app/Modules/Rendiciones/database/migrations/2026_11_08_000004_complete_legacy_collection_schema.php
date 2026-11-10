<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Estructuras leídas por Historial de Ventas y por las referencias bancarias. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobro')) {
            Schema::create('cobro', function (Blueprint $table): void {
                $table->increments('id_cobro');
                $table->unsignedInteger('id_cliente');
                $table->unsignedInteger('id_usuario');
                $table->dateTime('fecha')->useCurrent();
                $table->decimal('monto_total', 12, 2);
                $table->string('medio_pago', 30)->default('efectivo');
                $table->string('comprobante_nro', 100)->nullable();
                $table->string('estado', 20)->default('registrado');
                $table->text('observaciones')->nullable();
                $table->text('motivo_anulacion')->nullable();
                $table->dateTime('fecha_anulacion')->nullable();
                $table->unsignedInteger('id_usuario_anulacion')->nullable();
                $table->string('clave_operacion', 100)->nullable()->unique();
                $table->unsignedInteger('rendicion_cobro_id')->nullable()->unique();
            });
        }
        if (! Schema::hasTable('cobro_venta')) {
            Schema::create('cobro_venta', function (Blueprint $table): void {
                $table->unsignedInteger('id_cobro');
                $table->unsignedBigInteger('id_venta');
                $table->decimal('monto_aplicado', 12, 2);
                $table->primary(['id_cobro', 'id_venta']);
            });
        }
    }

    public function down(): void
    {
        // Conserva registros importados o creados después de la instalación.
    }
};
