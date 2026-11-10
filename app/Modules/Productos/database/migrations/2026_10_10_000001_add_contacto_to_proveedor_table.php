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

        if (Schema::hasTable('proveedor') && ! Schema::hasColumn('proveedor', 'contacto')) {

            Schema::table('proveedor', function (Blueprint $table) {

                $table->string('contacto', 160)->nullable()->after('razon_social');

            });

        }

    }

    public function down(): void
    {

        if (Schema::hasTable('proveedor') && Schema::hasColumn('proveedor', 'contacto')) {

            Schema::table('proveedor', function (Blueprint $table) {

                $table->dropColumn('contacto');

            });

        }

    }

};
