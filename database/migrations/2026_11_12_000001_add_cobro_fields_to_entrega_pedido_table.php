<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entrega_pedido')) {
            Schema::table('entrega_pedido', function (Blueprint $table) {
                if (!Schema::hasColumn('entrega_pedido', 'monto_cobrado')) {
                    $table->decimal('monto_cobrado', 12, 2)->nullable()->after('estado_pedido_entrega');
                }
                if (!Schema::hasColumn('entrega_pedido', 'medio_pago')) {
                    $table->string('medio_pago', 30)->nullable()->after('monto_cobrado');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entrega_pedido')) {
            Schema::table('entrega_pedido', function (Blueprint $table) {
                if (Schema::hasColumn('entrega_pedido', 'medio_pago')) {
                    $table->dropColumn('medio_pago');
                }
                if (Schema::hasColumn('entrega_pedido', 'monto_cobrado')) {
                    $table->dropColumn('monto_cobrado');
                }
            });
        }
    }
};

