<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PROMOCION_PRODUCTO', function (Blueprint $table) {
            $table->unsignedBigInteger('id_promocion');
            $table->unsignedBigInteger('id_producto');
            $table->integer('cantidad')->default(1);

            // Clave primaria compuesta
            $table->primary(['id_promocion', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PROMOCION_PRODUCTO');
    }
};
