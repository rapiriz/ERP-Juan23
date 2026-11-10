<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        if (Schema::getConnection()->getDriverName() === 'sqlite') {

            return;

        }

        if (! Schema::hasTable('unidad_medida') || Schema::hasColumn('unidad_medida', 'es_base')) {

            return;

        }

        Schema::table('unidad_medida', function (Blueprint $table) {

            $table->boolean('es_base')->default(false);

        });

        $productos = DB::table('unidad_medida')

            ->whereNotNull('id_producto')

            ->distinct()

            ->pluck('id_producto');

        foreach ($productos as $idProducto) {

            $unidadBase = DB::table('unidad_medida')

                ->where('id_producto', $idProducto)

                ->where('equivalencia_base', 1)

                ->orderBy('id_unidad')

                ->value('id_unidad');

            $unidadBase ??= DB::table('unidad_medida')

                ->where('id_producto', $idProducto)

                ->orderBy('id_unidad')

                ->value('id_unidad');

            if ($unidadBase !== null) {

                DB::table('unidad_medida')

                    ->where('id_unidad', $unidadBase)

                    ->update(['es_base' => true]);

            }

        }

    }

    public function down(): void
    {

        if (Schema::hasTable('unidad_medida') && Schema::hasColumn('unidad_medida', 'es_base')) {

            Schema::table('unidad_medida', function (Blueprint $table) {

                $table->dropColumn('es_base');

            });

        }

    }

};
