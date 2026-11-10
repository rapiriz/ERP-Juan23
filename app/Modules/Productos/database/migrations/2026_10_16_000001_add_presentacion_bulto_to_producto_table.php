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

        if (Schema::hasTable('producto')) {

            Schema::table('producto', function (Blueprint $table) {

                if (! Schema::hasColumn('producto', 'formato_bulto')) {

                    $table->string('formato_bulto', 50)->nullable()->default('Unidad');

                }

                if (! Schema::hasColumn('producto', 'unidades_por_bulto')) {

                    $table->integer('unidades_por_bulto')->default(1);

                }

            });

            // Si existen unidades registradas en UNIDAD_MEDIDA, migramos los valores activos

            if (Schema::hasTable('unidad_medida')) {

                $unidadesActivas = DB::table('unidad_medida')

                    ->where('es_base', 1)

                    ->whereNotNull('id_producto')

                    ->get();

                foreach ($unidadesActivas as $u) {

                    DB::table('producto')

                        ->where('id_producto', $u->id_producto)

                        ->update([

                            'formato_bulto' => $u->nombre_unidad ?: 'Unidad',

                            'unidades_por_bulto' => max(1, (int) round((float) $u->equivalencia_base)),

                        ]);

                }

            }

        }

    }

    public function down(): void
    {

        if (Schema::hasTable('producto')) {

            $columnas = array_values(array_filter(

                ['formato_bulto', 'unidades_por_bulto'],

                fn (string $c): bool => Schema::hasColumn('producto', $c)

            ));

            if (! empty($columnas)) {

                Schema::table('producto', function (Blueprint $table) use ($columnas) {

                    $table->dropColumn($columnas);

                });

            }

        }

    }

};
