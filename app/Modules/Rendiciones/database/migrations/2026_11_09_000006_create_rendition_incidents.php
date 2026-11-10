<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g2_rendicion_devoluciones')) {
            Schema::create('g2_rendicion_devoluciones', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('id_rendicion')->index();
                $t->unsignedInteger('id_entrega');
                $t->unsignedBigInteger('id_pedido');
                $t->unsignedInteger('id_cliente');
                $t->unsignedInteger('usuario_id');
                $t->string('motivo', 500);
                $t->text('observaciones')->nullable();
                $t->timestamp('created_at');
                $t->unique(['id_entrega', 'id_pedido']);
            });
        }
        if (! Schema::hasTable('g2_rendicion_diferencias')) {
            Schema::create('g2_rendicion_diferencias', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('id_rendicion')->unique();
                $t->decimal('total_esperado', 12, 2);
                $t->decimal('total_rendido', 12, 2);
                $t->decimal('diferencia', 12, 2);
                $t->string('tipo_diferencia', 20);
                $t->boolean('diferencia_pendiente');
                $t->string('motivo', 500)->nullable();
                $t->text('observaciones')->nullable();
                $t->unsignedInteger('usuario_id');
                $t->unsignedInteger('resuelto_por')->nullable();
                $t->text('resolucion')->nullable();
                $t->timestamp('resuelto_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No eliminar incidentes ni auditorías al retroceder el código.
    }
};
