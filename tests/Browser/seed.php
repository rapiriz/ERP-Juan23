<?php

use App\Modules\Clientes\Models\Cliente;
use App\Modules\Login\Models\User;
use App\Modules\Productos\Models\Producto;
use App\Modules\Stock\Models\UnidadMedida;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$name = getenv('ERP_G3_G4_TEST_DATABASE');
if (! is_string($name) || ! preg_match('/^erp_(qa|test)_[a-z0-9_]+$/D', $name)) {
    throw new RuntimeException('Sólo una base QA explícita en 3315.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3315,
    'database.connections.mysql.database' => $name, 'database.connections.mysql.username' => getenv('ERP_TEST_DB_USERNAME') ?: 'root',
    'database.connections.mysql.password' => getenv('ERP_TEST_DB_PASSWORD') ?: '', 'database.connections.mysql.url' => null]);
DB::purge('mysql');
$credentials = ['password' => bin2hex(random_bytes(16)), 'token' => bin2hex(random_bytes(32))];
foreach (['admin' => 'administrativo', 'counter' => 'contador', 'driver' => 'repartidor', 'service' => 'contador'] as $key => $role) {
    if (User::where('usuario', 'qa.browser.'.$key)->exists()) {
        throw new RuntimeException('Fixture ya existe; usar una base QA nueva.');
    }
    $user = User::factory()->create(['usuario' => 'qa.browser.'.$key, 'rol' => $role,
        'password_hash' => Hash::make($credentials['password'])]);
    $credentials[$key] = $user->id;
}
$category = DB::table('categoria')->insertGetId(['nombre' => 'Categoría navegador', 'fecha_creacion' => today()], 'id_categoria');
$brand = DB::table('marca')->insertGetId(['nombre' => 'Marca navegador', 'fecha_creacion' => today()], 'id_marca');
$imageDirectory = public_path('assets/catalogo');
if (! is_dir($imageDirectory)) mkdir($imageDirectory, 0755, true);
$fixtureImage = imagecreatetruecolor(64, 64);
imagefill($fixtureImage, 0, 0, imagecolorallocate($fixtureImage, 7, 91, 221));
imagewebp($fixtureImage, $imageDirectory.'/qa-browser-fixture.webp');
imagedestroy($fixtureImage);
$product = Producto::create(['codigo' => 'QA-BROWSER', 'nombre' => 'Producto navegador', 'descripcion' => 'Fixture de interfaz', 'imagen' => '/assets/catalogo/qa-browser-fixture.webp', 'precio_unitario' => 10.75, 'stock' => 50, 'stock_minimo' => 5, 'estado' => 'activo', 'id_categoria' => $category, 'id_marca' => $brand, 'id_usuario_carga' => $credentials['admin']]);
$credentials['product_code'] = $product->codigo;
$baseUnit = UnidadMedida::create(['id_producto' => $product->id_producto, 'nombre_unidad' => 'Unidad', 'equivalencia_base' => 1, 'es_base' => 1]);
$supplier = DB::table('proveedor')->insertGetId(['razon_social' => 'Proveedor navegador QA', 'CUIT' => '30999999992',
    'estado' => 'activo', 'fecha_alta' => today(), 'id_usuario_carga' => $credentials['admin']], 'id_proveedor');
$purchase = \App\Modules\Compras\Models\Compra::create(['numero_compra' => 'QA-BROWSER-COMPRA', 'id_proveedor' => $supplier,
    'importe_total' => 10.75, 'saldo_pendiente' => 10.75, 'estado' => 'pendiente', 'fecha_compra' => today(), 'id_usuario' => $credentials['admin']]);
\App\Modules\Compras\Models\DetalleCompra::create(['id_compra' => $purchase->id_compra, 'id_producto' => $product->id_producto,
    'id_unidad' => $baseUnit->id_unidad, 'cantidad' => 1, 'cantidad_recibida' => 0, 'precio_unitario' => 10.75, 'subtotal' => 10.75]);
$credentials['client'] = Cliente::factory()->create(['nombre' => 'Cliente navegador', 'creado_por' => $credentials['admin']])->id;
$directory = storage_path('app/private/qa-browser');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
file_put_contents($directory.'/credentials.json', json_encode($credentials, JSON_THROW_ON_ERROR));
chmod($directory.'/credentials.json', 0600);
echo "Fixtures exclusivos de navegador preparados.\n";
