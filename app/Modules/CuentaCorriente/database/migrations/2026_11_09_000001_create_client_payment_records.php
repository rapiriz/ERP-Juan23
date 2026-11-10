<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuenta_corriente_pagos')) {
            Schema::create('cuenta_corriente_pagos', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->uuid('request_key')->unique();
                $t->unsignedInteger('cliente_id')->index();
                $t->unsignedInteger('usuario_id');
                $t->decimal('monto', 12, 2);
                $t->string('medio_pago', 30);
                $t->string('comprobante', 150)->nullable();
                $t->unsignedBigInteger('transferencia_id')->nullable()->unique();
                $t->unsignedBigInteger('movimiento_cuenta_id')->unique();
                $t->unsignedInteger('movimiento_caja_id')->unique();
                $t->decimal('saldo_anterior', 12, 2);
                $t->decimal('saldo_restante', 12, 2);
                $t->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('cuenta_corriente_pago_eventos')) {
            Schema::create('cuenta_corriente_pago_eventos', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('pago_id')->unique();
                $t->unsignedInteger('usuario_id');
                $t->string('evento', 40);
                $t->json('datos');
                $t->timestamp('created_at');
            });
        }
    }

    public function down(): void
    { /* Registros financieros: restaurar respaldo para revertir. */
    }
};
