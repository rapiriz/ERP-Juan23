<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('g2_cliente_coordenadas')) {
            return;
        }

        Schema::create('g2_cliente_coordenadas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_cliente')->unique();
            $table->string('direccion_normalizada', 255);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('fuente', 40)->default('nominatim');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('g2_cliente_coordenadas');
    }
};
