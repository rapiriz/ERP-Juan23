<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['id_zona' => fn (Blueprint $t) => $t->unsignedInteger('id_zona')->nullable(),
            'fecha_modificacion' => fn (Blueprint $t) => $t->dateTime('fecha_modificacion')->nullable(),
            'id_usuario_modificacion' => fn (Blueprint $t) => $t->unsignedInteger('id_usuario_modificacion')->nullable()] as $column => $add) {
            if (! Schema::hasColumn('entrega', $column)) {
                Schema::table('entrega', $add);
            }
        }
        foreach (['datos_medio_pago' => fn (Blueprint $t) => $t->json('datos_medio_pago')->nullable(),
            'estado_revision' => fn (Blueprint $t) => $t->string('estado_revision', 20)->default('pendiente'),
            'id_cobro_aplicado' => fn (Blueprint $t) => $t->unsignedInteger('id_cobro_aplicado')->nullable()->unique(),
            'revisado_por' => fn (Blueprint $t) => $t->unsignedInteger('revisado_por')->nullable(),
            'fecha_revision' => fn (Blueprint $t) => $t->dateTime('fecha_revision')->nullable(),
            'nota_revision' => fn (Blueprint $t) => $t->text('nota_revision')->nullable()] as $column => $add) {
            if (! Schema::hasColumn('RENDICION_COBRO', $column)) {
                Schema::table('RENDICION_COBRO', $add);
            }
        }
        if (! Schema::hasColumn('CONCILIACION_DETALLE', 'id_grupo')) {
            Schema::table('CONCILIACION_DETALLE', fn (Blueprint $t) => $t->uuid('id_grupo')->nullable()->index());
        }
        if (! Schema::hasColumn('CONCILIACION_DETALLE', 'monto_aplicado')) {
            Schema::table('CONCILIACION_DETALLE', fn (Blueprint $t) => $t->decimal('monto_aplicado', 12, 2)->nullable());
        }
        if (! Schema::hasTable('g2_cobro_aplicaciones')) {
            Schema::create('g2_cobro_aplicaciones', function (Blueprint $t) {
                $t->unsignedInteger('id_cobro')->primary();
                $t->unsignedBigInteger('pago_id')->unique();
                $t->unsignedBigInteger('reversion_cuenta_id')->nullable()->unique();
                $t->unsignedInteger('reversion_caja_id')->nullable()->unique();
                $t->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('g2_operacion_eventos')) {
            Schema::create('g2_operacion_eventos', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('dominio', 30);
                $t->string('registro', 100)->index();
                $t->string('evento', 50);
                $t->unsignedInteger('usuario_id');
                $t->json('datos');
                $t->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('g2_conciliacion_grupos')) {
            Schema::create('g2_conciliacion_grupos', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('request_key')->unique();
                $t->unsignedInteger('id_periodo');
                $t->string('estado', 20)->default('activo');
                $t->string('metodo', 20);
                $t->decimal('monto', 12, 2);
                $t->unsignedInteger('usuario_id');
                $t->json('seleccion');
                $t->text('nota')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('g2_conciliacion_reservas')) {
            Schema::create('g2_conciliacion_reservas', function (Blueprint $t) {
                $t->string('origen', 20);
                $t->unsignedBigInteger('origen_id');
                $t->uuid('grupo_id')->index();
                $t->primary(['origen', 'origen_id']);
            });
        }
        if (! Schema::hasTable('g2_entrega_reservas')) {
            Schema::create('g2_entrega_reservas', function (Blueprint $t) {
                $t->unsignedBigInteger('id_venta')->primary();
                $t->unsignedInteger('id_entrega')->index();
            });
        }
    }

    public function down(): void
    {
        // Asientos y auditorías no se destruyen: revertir con respaldo documentado.
    }
};
