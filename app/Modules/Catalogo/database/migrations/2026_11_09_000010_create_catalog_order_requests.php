<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g1_catalog_orders')) {
            Schema::create('g1_catalog_orders', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->uuid('request_key')->unique();
                $t->uuid('sale_request_key')->unique();
                $t->char('payload_hash', 64);
                $t->char('receipt_hash', 64);
                $t->string('nombre', 160);
                $t->string('email', 160);
                $t->string('telefono', 30);
                $t->text('observaciones')->nullable();
                $t->string('estado', 30)->default('solicitado')->index();
                $t->decimal('total_referencia', 12, 2)->nullable();
                $t->unsignedInteger('cliente_id')->nullable();
                $t->unsignedInteger('revisado_por')->nullable();
                $t->unsignedBigInteger('venta_id')->nullable()->unique();
                $t->text('nota_revision')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('g1_catalog_order_items')) {
            Schema::create('g1_catalog_order_items', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('order_id');
                $t->integer('producto_id');
                $t->string('codigo', 100);
                $t->string('nombre', 500);
                $t->unsignedInteger('cantidad');
                $t->decimal('precio_referencia', 12, 2)->nullable();
                $t->unique(['order_id', 'producto_id']);
                $t->foreign('order_id')->references('id')->on('g1_catalog_orders')->restrictOnDelete();
            });
        }
        if (! Schema::hasTable('g1_catalog_order_events')) {
            Schema::create('g1_catalog_order_events', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('order_id');
                $t->unsignedInteger('usuario_id')->nullable();
                $t->string('estado_anterior', 30)->nullable();
                $t->string('estado_nuevo', 30);
                $t->text('nota')->nullable();
                $t->timestamp('created_at');
                $t->foreign('order_id')->references('id')->on('g1_catalog_orders')->restrictOnDelete();
            });
        }
    }

    public function down(): void {}
};
