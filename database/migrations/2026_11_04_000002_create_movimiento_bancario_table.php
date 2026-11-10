<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Amplía el esquema existente y conserva el contrato bancario histórico. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('MOVIMIENTO_BANCARIO')) {
            Schema::create('MOVIMIENTO_BANCARIO', function (Blueprint $table): void {
                $table->increments('id_movimiento_bancario');
                $table->unsignedInteger('id_periodo')->nullable()->index();
                $table->date('fecha_movimiento')->nullable();
                $table->string('descripcion')->nullable();
                $table->decimal('monto', 15, 2);
                $table->string('tipo', 20)->nullable();
                $table->string('estado', 30)->nullable();
                $table->dateTime('fecha_importacion')->nullable();
                $table->unsignedInteger('id_usuario')->nullable();
            });
        }
        $columns = [
            'id_periodo' => fn (Blueprint $t) => $t->unsignedInteger('id_periodo')->nullable()->index(),
            'fecha_movimiento' => fn (Blueprint $t) => $t->date('fecha_movimiento')->nullable(),
            'descripcion' => fn (Blueprint $t) => $t->string('descripcion')->nullable(),
            'tipo' => fn (Blueprint $t) => $t->string('tipo', 20)->nullable(),
            'estado' => fn (Blueprint $t) => $t->string('estado', 30)->nullable(),
            'fecha_importacion' => fn (Blueprint $t) => $t->dateTime('fecha_importacion')->nullable(),
            'id_usuario' => fn (Blueprint $t) => $t->unsignedInteger('id_usuario')->nullable(),
            'id_archivo_importado_log' => fn (Blueprint $t) => $t->unsignedInteger('id_archivo_importado_log')->nullable()->index(),
            'fecha_operacion' => fn (Blueprint $t) => $t->date('fecha_operacion')->nullable(),
            'signo_monto' => fn (Blueprint $t) => $t->string('signo_monto', 20)->nullable(),
            'descripcion_original' => fn (Blueprint $t) => $t->text('descripcion_original')->nullable(),
            'descripcion_normalizada' => fn (Blueprint $t) => $t->string('descripcion_normalizada')->nullable(),
            'nro_comprobante_extraido' => fn (Blueprint $t) => $t->string('nro_comprobante_extraido', 100)->nullable(),
            'cuit_extraido' => fn (Blueprint $t) => $t->string('cuit_extraido', 11)->nullable(),
            'tipo_movimiento' => fn (Blueprint $t) => $t->string('tipo_movimiento', 40)->default('desconocido'),
            'estado_conciliacion' => fn (Blueprint $t) => $t->string('estado_conciliacion', 30)->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ];
        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('MOVIMIENTO_BANCARIO', $column)) {
                Schema::table('MOVIMIENTO_BANCARIO', $definition);
            }
        }
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE MOVIMIENTO_BANCARIO MODIFY id_periodo INT UNSIGNED NULL');
        }
        DB::table('MOVIMIENTO_BANCARIO')->whereNull('fecha_operacion')->update([
            'fecha_operacion' => DB::raw('fecha_movimiento'),
            'descripcion_original' => DB::raw('descripcion'),
            'descripcion_normalizada' => DB::raw('UPPER(descripcion)'),
            'signo_monto' => DB::raw("CASE WHEN tipo IN ('debito','egreso') THEN 'egreso' ELSE 'ingreso' END"),
            'estado_conciliacion' => DB::raw("CASE WHEN estado = 'conciliado' THEN 'matcheado' ELSE estado END"),
            'created_at' => DB::raw('fecha_importacion'),
        ]);
        DB::table('MOVIMIENTO_BANCARIO')->whereNull('fecha_movimiento')->update([
            'fecha_movimiento' => DB::raw('fecha_operacion'),
            'descripcion' => DB::raw('SUBSTR(descripcion_original, 1, 255)'),
            'tipo' => DB::raw("CASE WHEN signo_monto = 'egreso' THEN 'debito' ELSE 'credito' END"),
            'estado' => DB::raw("CASE WHEN estado_conciliacion = 'matcheado' THEN 'conciliado' ELSE estado_conciliacion END"),
            'fecha_importacion' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        // Ambas versiones pueden contener datos reales. Reversión desde respaldo.
    }
};
