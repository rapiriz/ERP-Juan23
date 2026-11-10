<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        if (Schema::getConnection()->getDriverName() === 'sqlite') {

            return;

        }

        if (Schema::hasTable('historial_precio_proveedor') && ! Schema::hasColumn('historial_precio_proveedor', 'observaciones')) {

            Schema::table('historial_precio_proveedor', function (Blueprint $table) {

                $table->string('observaciones', 255)->nullable()->after('fecha_actualizacion');

            });

        }

    }

    public function down(): void
    {

        if (Schema::hasTable('historial_precio_proveedor') && Schema::hasColumn('historial_precio_proveedor', 'observaciones')) {

            Schema::table('historial_precio_proveedor', function (Blueprint $table) {

                $table->dropColumn('observaciones');

            });

        }

    }

};
