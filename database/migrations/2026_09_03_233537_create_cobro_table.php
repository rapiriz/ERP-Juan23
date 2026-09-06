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
            $table->date('fecha_cobro');
            $table->decimal('monto', 12, 2);
            $table->string('medio_pago', 20); // 'efectivo' | 'transferencia' | 'cheque' | 'otro'
            $table->string('estado', 20);

            $table->foreign('id_cliente')->references('id_cliente')->on('CLIENTE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('COBRO');
    }
};