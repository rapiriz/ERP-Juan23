<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('g1_transferencias')) {
            Schema::create('g1_transferencias', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->uuid('request_key')->unique();
                $t->unsignedInteger('cliente_id')->index();
                $t->unsignedInteger('registrado_por');
                $t->date('fecha');
                $t->decimal('monto', 12, 2);
                $t->string('referencia', 150);
                $t->string('estado', 20)->default('pendiente')->index();
                $t->string('archivo');
                $t->string('nombre_archivo');
                $t->string('mime', 60);
                $t->unsignedInteger('bytes');
                $t->string('sha256', 64);
                $t->unsignedInteger('revisado_por')->nullable();
                $t->text('nota_revision')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('g1_transferencia_eventos')) {
            Schema::create('g1_transferencia_eventos', function (Blueprint $t): void {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('transferencia_id')->index();
                $t->unsignedInteger('usuario_id');
                $t->string('evento', 30);
                $t->json('datos');
                $t->timestamp('created_at');
            });
        }
    }

    public function down(): void
    { /* Comprobantes y auditoría: reversión desde respaldo. */
    }
};
