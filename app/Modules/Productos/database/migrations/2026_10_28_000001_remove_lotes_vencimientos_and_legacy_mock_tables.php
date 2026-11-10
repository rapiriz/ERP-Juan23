<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conserva el nombre histórico y reemplaza la limpieza por una ampliación segura. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('producto') && ! Schema::hasColumn('producto', 'imagen')) {
            Schema::table('producto', fn (Blueprint $table) => $table->string('imagen', 255)->nullable());
        }
    }

    public function down(): void
    {
        // La ampliación puede contener imágenes reales. Su reversión requiere respaldo.
    }
};
