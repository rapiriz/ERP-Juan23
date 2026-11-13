<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('entrega_pedido')) {
            return;
        }
        if (! Schema::hasColumn('entrega_pedido', 'monto_cobrado')) {
            Schema::table('entrega_pedido', function (Blueprint $table): void {
                $table->decimal('monto_cobrado', 12, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('entrega_pedido', 'medio_pago')) {
            Schema::table('entrega_pedido', function (Blueprint $table): void {
                $table->string('medio_pago', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Se conservan los cobros informados al confirmar una entrega.
    }
};
