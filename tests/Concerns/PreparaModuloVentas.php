<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AYUDA COMPARTIDA PARA LOS TESTS DE VENTAS
 *
 * Un "trait" es un pedazo de código que varias clases pueden reutilizar.
 *
 * Qué hace:
 *  1. Crea las tablas "venta" y "detalle_venta" en la base de prueba
 *     (SQLite en memoria). En el proyecto real esas tablas existen en MySQL
 *     pero no tienen migración, por eso los tests las arman acá, copiando
 *     la estructura real de MySQL.
 *  2. Manda el log de ventas a un archivo temporal (no ensucia storage/logs).
 *  3. Da atajos para crear ventas de prueba y leer el saldo de un cliente.
 *
 * Uso en un test:  use RefreshDatabase, PreparaModuloVentas;
 *                  y en setUp(): $this->prepararModuloVentas();
 */
trait PreparaModuloVentas
{
    // Clientes y productos que existen en los mocks (App\Services\Mock*).
    protected const CLIENTE = 915736;       // María González (sin movimientos previos)

    protected const OTRO_CLIENTE = 638204;  // Almacén Don Pedro

    protected string $archivoLogVentas;

    protected function prepararModuloVentas(): void
    {
        Schema::create('venta', function (Blueprint $table): void {
            $table->increments('id_venta');
            $table->unsignedInteger('id_cliente');
            $table->date('fecha');
            $table->decimal('descuento_global', 10, 2)->nullable();
            $table->decimal('total', 10, 2);
            $table->string('numFactura', 50);
            $table->string('estado');
            $table->string('observaciones', 150);
            $table->unsignedInteger('id_usuario');
        });

        Schema::create('detalle_venta', function (Blueprint $table): void {
            $table->increments('id_detalle');
            $table->unsignedInteger('id_venta');
            $table->unsignedInteger('id_producto');
            $table->unsignedInteger('id_promocion')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('descuento', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2);
        });

        $this->archivoLogVentas = tempnam(sys_get_temp_dir(), 'ventas_test_');
        config(['logging.channels.ventas' => ['driver' => 'single', 'path' => $this->archivoLogVentas]]);

        $this->beforeApplicationDestroyed(fn () => @unlink($this->archivoLogVentas));
    }

    /**
     * Crea una venta de prueba directo en la base (con detalle y movimiento de
     * cuenta corriente, igual que VentaController@store) y devuelve su id.
     *
     * Por defecto: 2 x Coca-Cola (id 1) a $2.500 = $5.000, estado "confirmada".
     *
     * @param  array  $items  [[id_producto, cantidad, precio, descuento], ...]
     */
    protected function crearVenta(string $estado = 'confirmada', array $items = [[1, 2, 2500, 0]], int $cliente = self::CLIENTE, bool $conCuentaCorriente = true): int
    {
        $total = 0;
        foreach ($items as [, $cantidad, $precio, $descuento]) {
            $total += $cantidad * ($precio - $descuento);
        }

        $id = DB::table('venta')->insertGetId([
            'id_cliente' => $cliente,
            'fecha' => '2026-10-13',
            'descuento_global' => 0,
            'total' => $total,
            'numFactura' => 'POS-TEST-'.uniqid(),
            'estado' => $estado,
            'observaciones' => 'Venta de prueba',
            'id_usuario' => 1,
        ], 'id_venta');

        foreach ($items as [$producto, $cantidad, $precio, $descuento]) {
            DB::table('detalle_venta')->insert([
                'id_venta' => $id,
                'id_producto' => $producto,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => $descuento,
                'subtotal' => $cantidad * ($precio - $descuento),
            ]);
        }

        if ($conCuentaCorriente) {
            DB::table('cuenta_corriente_movimientos')->insert([
                'cliente_id' => $cliente,
                'venta_id' => $id,
                'tipo' => 'venta',
                'descripcion' => "Venta POS #{$id}",
                'importe' => -$total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return (int) $id;
    }

    /**
     * Movimiento de cuenta corriente de una venta (o null si no tiene).
     */
    protected function movimientoDe(int $ventaId): ?object
    {
        return DB::table('cuenta_corriente_movimientos')->where('venta_id', $ventaId)->first();
    }

    /**
     * Suma de los movimientos guardados de un cliente (negativo = debe).
     */
    protected function saldoGuardado(int $cliente): float
    {
        return (float) DB::table('cuenta_corriente_movimientos')->where('cliente_id', $cliente)->sum('importe');
    }

    /**
     * Estado guardado de una venta.
     */
    protected function estadoDe(int $ventaId): string
    {
        return DB::table('venta')->where('id_venta', $ventaId)->value('estado');
    }

    /**
     * Contenido del log de ventas escrito durante el test.
     */
    protected function logVentas(): string
    {
        return (string) file_get_contents($this->archivoLogVentas);
    }
}
