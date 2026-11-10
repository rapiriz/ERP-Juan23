<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'limite_credito')) {
            Schema::table('clientes', fn (Blueprint $t) => $t->decimal('limite_credito', 12, 2)->nullable());
        }
        if (! Schema::hasTable('g4_credit_events')) {
            Schema::create('g4_credit_events', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('cliente_id')->index();
                $t->unsignedBigInteger('venta_id')->nullable();
                $t->unsignedInteger('usuario_id');
                $t->string('evento', 40);
                $t->json('datos');
                $t->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('g4_devoluciones')) {
            Schema::create('g4_devoluciones', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->uuid('request_key')->unique();
                $t->char('payload_hash', 64);
                $t->unsignedBigInteger('venta_id')->index();
                $t->unsignedInteger('cliente_id')->index();
                $t->unsignedInteger('usuario_id');
                $t->text('motivo');
                $t->decimal('total', 12, 2);
                $t->unsignedBigInteger('stock_operation_id')->nullable();
                $t->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('g4_devolucion_detalles')) {
            Schema::create('g4_devolucion_detalles', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('devolucion_id')->index();
                $t->unsignedBigInteger('detalle_venta_id')->index();
                $t->unsignedInteger('producto_id');
                $t->unsignedInteger('cantidad');
                $t->string('condicion', 20);
                $t->decimal('precio_venta', 12, 2);
                $t->decimal('importe_neto', 12, 2);
                $t->unique(['devolucion_id', 'detalle_venta_id']);
            });
        }
        if (! Schema::hasTable('g4_notas_credito')) {
            Schema::create('g4_notas_credito', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('numero', 50)->unique();
                $t->unsignedBigInteger('devolucion_id')->unique();
                $t->unsignedBigInteger('venta_id')->index();
                $t->unsignedInteger('cliente_id')->index();
                $t->unsignedBigInteger('movimiento_id')->unique();
                $t->decimal('total', 12, 2);
                $t->unsignedInteger('usuario_id');
                $t->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        // Créditos, recepción física y auditoría requieren reversión financiera, no DROP.
    }
};
