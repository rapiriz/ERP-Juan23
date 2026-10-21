<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El Grupo 2 utiliza CACHE_STORE=file, no requiere tablas en base de datos.
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
