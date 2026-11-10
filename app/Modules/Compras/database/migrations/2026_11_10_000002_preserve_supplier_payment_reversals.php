<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pago_proveedor')) return;
        foreach (['anulado_en', 'anulado_por', 'motivo_anulacion'] as $column) {
            if (Schema::hasColumn('pago_proveedor', $column)) continue;
            Schema::table('pago_proveedor', function (Blueprint $t) use ($column) {
                match ($column) {
                    'anulado_en' => $t->timestamp($column)->nullable(),
                    'anulado_por' => $t->unsignedInteger($column)->nullable(),
                    default => $t->string($column, 1000)->nullable(),
                };
            });
        }
        if (! Schema::hasIndex('pago_proveedor', 'pago_proveedor_activos_idx')) {
            Schema::table('pago_proveedor', fn (Blueprint $t) => $t->index(['id_compra', 'anulado_en'], 'pago_proveedor_activos_idx'));
        }
    }

    public function down(): void {}
};
