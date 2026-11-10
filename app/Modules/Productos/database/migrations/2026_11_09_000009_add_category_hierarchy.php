<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categoria') && ! Schema::hasColumn('categoria', 'id_categoria_padre')) {
            Schema::table('categoria', fn (Blueprint $t) => $t->integer('id_categoria_padre')->nullable()->index());
        }
    }

    public function down(): void {}
};
