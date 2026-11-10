<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (require database_path('migrations/2026_11_04_000002_create_movimiento_bancario_table.php'))->up();
        if (! Schema::hasTable('PERIODO_CONCILIACION')) {
            Schema::create('PERIODO_CONCILIACION', function (Blueprint $t): void {
                $t->increments('id_periodo');
                $t->date('fecha_desde');
                $t->date('fecha_hasta');
                $t->string('estado', 20);
                $t->dateTime('fecha_apertura');
                $t->dateTime('fecha_cierre')->nullable();
                $t->unsignedInteger('id_usuario');
            });
        }
        if (! Schema::hasTable('CHEQUE')) {
            Schema::create('CHEQUE', function (Blueprint $t): void {
                $t->increments('id_cheque');
                $t->unsignedInteger('id_cobro');
                $t->string('numero_cheque', 50);
                $t->string('banco_emisor', 100);
                $t->date('fecha_cobro');
                $t->string('estado', 20);
                $t->date('fecha_deposito')->nullable();
                $t->date('fecha_rechazo')->nullable();
            });
        }
        if (! Schema::hasTable('AJUSTE_BANCARIO')) {
            Schema::create('AJUSTE_BANCARIO', function (Blueprint $t): void {
                $t->increments('id_ajuste');
                $t->unsignedInteger('id_movimiento_bancario');
                $t->string('categoria', 30);
                $t->string('observacion')->nullable();
                $t->decimal('monto', 12, 2);
                $t->dateTime('fecha_registro');
                $t->unsignedInteger('id_usuario');
            });
        }
        if (! Schema::hasTable('CONCILIACION_DETALLE')) {
            Schema::create('CONCILIACION_DETALLE', function (Blueprint $t): void {
                $t->increments('id_detalle');
                $t->unsignedInteger('id_movimiento_bancario')->index();
                $t->unsignedInteger('id_cobro')->nullable();
                $t->unsignedInteger('id_movimiento_caja')->nullable();
                $t->unsignedInteger('id_ajuste')->nullable();
                $t->unsignedInteger('id_cheque')->nullable();
                $t->dateTime('fecha_conciliacion');
                $t->string('metodo', 20);
                $t->unsignedInteger('id_usuario');
            });
        }
    }

    public function down(): void
    {
        // Conservar datos preexistentes. La reversión utiliza el respaldo documentado.
    }
};
