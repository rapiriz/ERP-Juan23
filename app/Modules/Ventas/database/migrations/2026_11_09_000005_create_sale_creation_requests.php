<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g4_sale_requests')) {
            Schema::create('g4_sale_requests', function (Blueprint $t) {
                $t->uuid('request_key')->primary();
                $t->unsignedBigInteger('actor_id');
                $t->char('payload_hash', 64);
                $t->json('respuesta')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Conservar referencias de ventas ya creadas para no duplicarlas al volver atrás.
    }
};
