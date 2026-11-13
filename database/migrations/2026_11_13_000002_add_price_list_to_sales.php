<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta') && ! Schema::hasColumn('venta', 'lista')) {
            Schema::table('venta', function (Blueprint $table): void {
                $table->string('lista', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        // No se descarta la elección comercial de operaciones existentes.
    }
};
