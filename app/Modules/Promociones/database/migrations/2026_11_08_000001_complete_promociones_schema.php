<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promocion')) {
            Schema::create('promocion', function (Blueprint $table): void {
                $table->increments('id_promocion');
                $table->string('nombre', 50);
                $table->decimal('total', 12, 2);
                $table->date('vigencia_desde');
                $table->date('vigencia_hasta');
                $table->string('descripcion', 150)->nullable();
                $table->boolean('estado')->default(true);
            });
        }
        if (! Schema::hasColumn('promocion', 'tipo_descuento')) {
            Schema::table('promocion', fn (Blueprint $table) => $table->string('tipo_descuento', 20)->default('monto_fijo'));
        }
        if (! Schema::hasColumn('promocion', 'valor_descuento')) {
            Schema::table('promocion', fn (Blueprint $table) => $table->decimal('valor_descuento', 12, 2)->default(0));
        }
        if (! Schema::hasTable('promocion_producto')) {
            Schema::create('promocion_producto', function (Blueprint $table): void {
                $table->unsignedInteger('id_promocion');
                $table->unsignedInteger('id_producto');
                $table->unsignedInteger('cantidad');
                $table->index(['id_promocion', 'id_producto']);
            });
        }
    }

    public function down(): void
    {
        // Puede ampliar tablas preexistentes. Restaurar el respaldo para revertir.
    }
};
