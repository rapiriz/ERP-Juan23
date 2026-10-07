<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promocion', function (Blueprint $table) {
            $table->id('id_promocion'); // Clave primaria personalizada
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('tipo_descuento')->nullable(); // 'porcentaje' | 'monto_fijo'
            $table->decimal('valor', 10, 2)->nullable();
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->string('condiciones')->nullable();
            $table->string('estado')->nullable(); // 'activa' | 'pausada' | 'baja'

            // Si tu esquema no usa created_at y updated_at, coméntalos. 
            // Normalmente Laravel los necesita, es recomendable dejarlos.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion');
    }
};
