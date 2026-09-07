<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CHEQUE', function (Blueprint $table) {
            $table->increments('id_cheque');
            $table->unsignedInteger('id_cobro');
            $table->string('numero_cheque', 50);
            $table->string('banco_emisor', 100);
            $table->date('fecha_cobro');
            $table->string('estado', 20); // 'en_cartera' | 'depositado' | 'conciliado' | 'rechazado'
            $table->date('fecha_deposito')->nullable();
            $table->date('fecha_rechazo')->nullable();

            $table->foreign('id_cobro')->references('id_cobro')->on('COBRO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CHEQUE');
    }
};