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
        Schema::table('MOVIMIENTO_BANCARIO', function (Blueprint $table) {
            if (!Schema::hasColumn('MOVIMIENTO_BANCARIO', 'id_rendicion_cobro')) {
                $table->unsignedBigInteger('id_rendicion_cobro')->nullable()->after('estado_conciliacion');
            }
            if (!Schema::hasColumn('MOVIMIENTO_BANCARIO', 'id_caja_movimiento')) {
                $table->unsignedBigInteger('id_caja_movimiento')->nullable()->after('id_rendicion_cobro');
            }
            if (!Schema::hasColumn('MOVIMIENTO_BANCARIO', 'observaciones_conciliacion')) {
                $table->text('observaciones_conciliacion')->nullable()->after('id_caja_movimiento');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('MOVIMIENTO_BANCARIO', function (Blueprint $table) {
            $table->dropColumn([
                'id_rendicion_cobro',
                'id_caja_movimiento',
                'observaciones_conciliacion',
            ]);
        });
    }
};
