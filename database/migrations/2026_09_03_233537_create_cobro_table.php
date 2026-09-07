<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('COBRO', function (Blueprint $table) {
            $table->increments('id_cobro');
            $table->unsignedInteger('id_cliente');
            $table->unsignedInteger('id_usuario');
            $table->dateTime('fecha')->useCurrent();
            $table->decimal('monto_total', 10, 2);
            $table->enum('medio_pago', ['efectivo', 'transferencia', 'cheque'])->default('efectivo');
            $table->string('comprobante_nro', 100)->nullable();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->text('observaciones')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->dateTime('fecha_anulacion')->nullable();
            $table->unsignedInteger('id_usuario_anulacion')->nullable();
            // Foreign keys (se aplican si existen las tablas CLIENTE y USUARIO)
            $table->foreign('id_cliente')->references('id_cliente')->on('CLIENTE');
            $table->foreign('id_usuario')->references('id_usuario')->on('USUARIO');
            $table->foreign('id_usuario_anulacion')->references('id_usuario')->on('USUARIO');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('COBRO');
    }
};