<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Login\Models\User;
use Illuminate\Support\Facades\DB;

/** Opt-in: nunca conecta a la base habitual ni ejecuta migraciones. */
trait UsesG3Database
{
    public function beginDatabaseTransaction(): void
    {
        $name = getenv('ERP_G3_G4_TEST_DATABASE');
        if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name)) {
            $this->markTestSkipped('Requiere una base exclusiva ERP_G3_G4_TEST_DATABASE=erp_qa_... en 3315; ver docs/validacion-y-despliegue.md.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3315, 'database.connections.mysql.database' => $name,
            'database.connections.mysql.username' => getenv('ERP_TEST_DB_USERNAME') ?: 'root',
            'database.connections.mysql.password' => getenv('ERP_TEST_DB_PASSWORD') ?: '',
            'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        $connection = DB::connection('mysql');
        TestDatabaseSafety::check($connection);
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        });
        // Fixtures explícitos dentro de la transacción; no dependen de datos del desarrollador.
        $admin = User::factory()->create(['usuario' => 'qa-fixture-admin', 'rol' => 'administrativo']);
        $category = DB::table('categoria')->insertGetId(['nombre' => 'Categoría QA', 'fecha_creacion' => today()], 'id_categoria');
        $brand = DB::table('marca')->insertGetId(['nombre' => 'Marca QA', 'fecha_creacion' => today()], 'id_marca');
        $product = DB::table('producto')->insertGetId(['codigo' => 'QA-FIXTURE-BASE', 'nombre' => 'Producto QA', 'descripcion' => 'Fixture transaccional',
            'fecha_alta' => today(), 'precio_unitario' => 100, 'stock' => 1000, 'stock_minimo' => 5, 'estado' => 'activo', 'id_categoria' => $category,
            'id_marca' => $brand, 'id_usuario_carga' => $admin->id], 'id_producto');
        DB::table('unidad_medida')->insert(['id_producto' => $product, 'nombre_unidad' => 'Unidad', 'equivalencia_base' => 1, 'es_base' => 1]);
        $this->signInAs($admin);
        DB::table('proveedor')->insert(['razon_social' => 'Proveedor QA', 'CUIT' => '30999999991', 'estado' => 'activo', 'fecha_alta' => today(), 'id_usuario_carga' => $admin->id]);
    }
}
