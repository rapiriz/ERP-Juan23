<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta') && ! Schema::hasColumn('venta', 'fecha_vencimiento')) {
            Schema::table('venta', fn (Blueprint $table) => $table->date('fecha_vencimiento')->nullable());
        }
    }

    public function down(): void
    {
        // Se conserva el vencimiento informado por el usuario.
    }
};
