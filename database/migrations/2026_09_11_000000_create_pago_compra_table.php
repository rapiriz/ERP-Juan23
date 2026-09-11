<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PAGO_COMPRA', function (Blueprint $table) {
            $table->id('id_pago_compra');
            $table->unsignedBigInteger('id_compra');
            $table->string('metodo', 30);
            $table->decimal('importe', 12, 2);
            $table->date('fecha_pago');
            $table->string('referencia', 100)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->index('id_compra');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PAGO_COMPRA');
    }
};
