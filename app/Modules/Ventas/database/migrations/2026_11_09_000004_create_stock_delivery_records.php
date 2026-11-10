<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g4_stock_operations')) {
            Schema::create('g4_stock_operations', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->uuid('request_key')->unique();
                $t->unsignedBigInteger('venta_id')->index();
                $t->unsignedBigInteger('actor_id');
                $t->string('motivo', 40);
                $t->json('cambios');
                $t->string('estado', 20)->default('pendiente')->index();
                $t->unsignedInteger('intentos')->default(0);
                $t->text('ultimo_error')->nullable();
                $t->timestamp('aplicado_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('g3_stock_requests')) {
            Schema::create('g3_stock_requests', function (Blueprint $t) {
                $t->uuid('request_key')->primary();
                $t->char('payload_hash', 64);
                $t->unsignedBigInteger('actor_id');
                $t->json('respuesta')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Historial y solicitudes pendientes requieren revisión; no borrar al revertir código.
    }
};
