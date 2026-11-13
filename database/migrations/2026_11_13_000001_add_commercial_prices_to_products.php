<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = Schema::getConnection()->getDriverName() === 'sqlite' ? 'productos' : 'producto';
        if (! Schema::hasTable($name)) {
            return;
        }

        foreach (['precio_minorista', 'precio_mayorista'] as $column) {
            if (! Schema::hasColumn($name, $column)) {
                Schema::table($name, function (Blueprint $table) use ($column): void {
                    $table->decimal($column, 12, 2)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Las tarifas cargadas se conservan al retroceder el código.
    }
};
