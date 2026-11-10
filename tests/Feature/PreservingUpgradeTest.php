<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreservingUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'upgrade_test', 'database.connections.upgrade_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
    }

    public function test_historical_cleanup_keeps_lots_ids_and_legacy_tables_on_repeated_execution(): void
    {
        foreach (['producto', 'lote', 'cliente_mock'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('nombre');
            });
            DB::table($name)->insert(['id' => 85, 'nombre' => 'Registro histórico']);
        }
        $migration = require app_path('Modules/Productos/database/migrations/2026_10_28_000001_remove_lotes_vencimientos_and_legacy_mock_tables.php');
        $migration->up();
        $migration->up();
        $migration->down();
        foreach (['producto', 'lote', 'cliente_mock'] as $name) {
            $this->assertSame('Registro histórico', DB::table($name)->where('id', 85)->value('nombre'));
            $this->assertSame(1, DB::table($name)->count());
        }
        $this->assertTrue(Schema::hasColumn('producto', 'imagen'));
    }

    public function test_bank_upgrade_preserves_old_values_and_can_be_repeated(): void
    {
        Schema::create('MOVIMIENTO_BANCARIO', function (Blueprint $table): void {
            $table->increments('id_movimiento_bancario');
            $table->integer('id_periodo');
            $table->date('fecha_movimiento');
            $table->string('descripcion');
            $table->decimal('monto', 15, 2);
            $table->string('tipo');
            $table->string('estado');
            $table->dateTime('fecha_importacion');
            $table->integer('id_usuario');
        });
        $row = ['id_movimiento_bancario' => 73, 'id_periodo' => 8, 'fecha_movimiento' => '2026-11-01',
            'descripcion' => 'Débito histórico', 'monto' => 123.45, 'tipo' => 'debito',
            'estado' => 'conciliado', 'fecha_importacion' => '2026-11-01 12:00:00', 'id_usuario' => 4];
        DB::table('MOVIMIENTO_BANCARIO')->insert($row);
        $before = DB::table('MOVIMIENTO_BANCARIO')->first(array_keys($row));
        $migration = require database_path('migrations/2026_11_04_000002_create_movimiento_bancario_table.php');
        $migration->up();
        $migration->up();
        $migration->down();
        $this->assertEquals($before, DB::table('MOVIMIENTO_BANCARIO')->first(array_keys($row)));
        $this->assertSame(1, DB::table('MOVIMIENTO_BANCARIO')->count());
        $this->assertSame('2026-11-01', DB::table('MOVIMIENTO_BANCARIO')->value('fecha_operacion'));
        $this->assertSame('egreso', DB::table('MOVIMIENTO_BANCARIO')->value('signo_monto'));
        $this->assertSame('matcheado', DB::table('MOVIMIENTO_BANCARIO')->value('estado_conciliacion'));
    }
}
