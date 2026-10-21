<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('COBRO_VENTA', function (Blueprint $table) {
            $table->unsignedInteger('id_cobro');
            $table->unsignedInteger('id_venta');
            $table->decimal('monto_aplicado', 10, 2);
            $table->primary(['id_cobro', 'id_venta']);
            $table->foreign('id_cobro')->references('id_cobro')->on('COBRO')->onDelete('cascade');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('COBRO_VENTA');
    }
};